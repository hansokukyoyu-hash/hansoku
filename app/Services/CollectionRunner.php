<?php

namespace App\Services;

use App\Enums\Platform;
use App\Exceptions\SnsApiException;
use App\Models\Account;
use App\Models\SyncLog;
use App\Services\Collectors\Collector;
use App\Services\Collectors\FacebookCollector;
use App\Services\Collectors\InstagramCollector;
use App\Services\Collectors\ThreadsCollector;
use App\Services\Collectors\TikTokCollector;
use App\Services\Collectors\YouTubeCollector;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * cron から数分おきに呼ばれ、収集時期が来たアカウントを少しずつ処理する。
 * 1回の処理を短く保つことで、レンタルサーバーの実行時間の上限に掛かりにくくする。
 * 途中で打ち切られても、同期時刻が更新されていないアカウントは次の回に再び選ばれる。
 */
class CollectionRunner
{
    public const JOB_DAILY = 'daily';

    public const JOB_STORIES = 'stories';

    /** 途中で打ち切られた処理を、この時間(分)は選ばずに他のアカウントを先に進める */
    public const RETRY_AFTER_MINUTES = 120;

    /** 「実行中」のまま、この時間(分)を過ぎた記録は打ち切られたものとみなす */
    public const STALE_MINUTES = 30;

    /** @return list<array{0: Account, 1: string}> */
    public function dueTasks(int $limit): array
    {
        $now = now();
        $tasks = [];

        if ($now->hour >= config('sns.daily_collect_hour')) {
            $dailyCutoff = $now->copy()->startOfDay()->setHour(config('sns.daily_collect_hour'));
            $this->collectable()
                ->filter(fn (Account $a) => $a->daily_synced_at === null || $a->daily_synced_at->lt($dailyCutoff))
                ->sortBy(fn (Account $a) => $a->daily_synced_at?->getTimestamp() ?? 0)
                ->each(function (Account $a) use (&$tasks) {
                    $tasks[] = [$a, self::JOB_DAILY];
                });
        }

        $storiesCutoff = $now->copy()->subHours(config('sns.stories_interval_hours'));
        $this->collectable()
            ->filter(fn (Account $a) => $a->platform->hasStories())
            ->filter(fn (Account $a) => $a->stories_synced_at === null || $a->stories_synced_at->lt($storiesCutoff))
            ->sortBy(fn (Account $a) => $a->stories_synced_at?->getTimestamp() ?? 0)
            ->each(function (Account $a) use (&$tasks) {
                $tasks[] = [$a, self::JOB_STORIES];
            });

        // 前の回で打ち切られたもの(終わりまで進まなかったもの)は、しばらく後回しにする
        $tasks = array_values(array_filter($tasks, fn ($t) => ! Cache::has(self::attemptKey($t[0], $t[1]))));

        // ストーリーズ(消える前に取りたいので先)と日次を、それぞれ最大 $limit 件ずつ。
        // 件数を共有すると、アカウントが多いときにストーリーズだけで枠が埋まり日次が進まなくなる
        $pick = fn (string $job) => array_slice(array_values(array_filter($tasks, fn ($t) => $t[1] === $job)), 0, $limit);

        return [...$pick(self::JOB_STORIES), ...$pick(self::JOB_DAILY)];
    }

    public function run(Account $account, string $job): SyncLog
    {
        $log = SyncLog::create([
            'account_id' => $account->id,
            'job' => $job,
            'status' => 'running',
            'started_at' => now(),
        ]);

        // 実行時間やメモリの上限で強制終了されると catch に来ないため、目印を残しておき、
        // 次の回はこのアカウントを後回しにする(正常に終われば消す)
        Cache::put(self::attemptKey($account, $job), true, now()->addMinutes(self::RETRY_AFTER_MINUTES));
        register_shutdown_function(function () use ($log) {
            try {
                $this->markInterrupted($log, error_get_last()['message'] ?? null);
            } catch (Throwable) {
                // 終了処理中は DB に繋がらないこともある。その場合は次の回の closeStaleLogs に任せる
            }
        });

        try {
            $collector = $this->collectorFor($account->platform);
            $message = $job === self::JOB_STORIES
                ? $collector->collectStories($account)
                : $collector->collectDaily($account);

            $account->forceFill([
                $job === self::JOB_STORIES ? 'stories_synced_at' : 'daily_synced_at' => now(),
                'status' => 'active',
                'last_error' => null,
            ])->save();

            $log->update(['status' => 'success', 'message' => $message, 'finished_at' => now()]);
        } catch (Throwable $e) {
            $reconnect = $e instanceof SnsApiException && $e->needsReconnect;
            $message = ($reconnect ? '【再連携が必要】' : '').$e->getMessage();

            // 失敗しても同期時刻は進めて、1つのアカウントの失敗で他が止まらないようにする
            $account->forceFill([
                $job === self::JOB_STORIES ? 'stories_synced_at' : 'daily_synced_at' => now(),
                'status' => 'error',
                'last_error' => $message,
            ])->save();

            $log->update(['status' => 'failed', 'message' => $message, 'finished_at' => now()]);
            report($e);
        }

        Cache::forget(self::attemptKey($account, $job));

        return $log;
    }

    /** 「実行中」のまま残った古い記録を、打ち切られたものとして記録し直す */
    public function closeStaleLogs(): int
    {
        $stale = SyncLog::query()
            ->where('status', 'running')
            ->where('started_at', '<', now()->subMinutes(self::STALE_MINUTES))
            ->get();
        $stale->each(fn (SyncLog $log) => $this->markInterrupted($log));

        return $stale->count();
    }

    private function markInterrupted(SyncLog $log, ?string $reason = null): void
    {
        $log->refresh();
        if ($log->status !== 'running') {
            return;
        }

        $message = '処理が途中で打ち切られました(サーバーの実行時間やメモリの上限の可能性)。'
            .self::RETRY_AFTER_MINUTES.'分後に自動で再試行します。'.($reason ? " 詳細: {$reason}" : '');
        $log->update(['status' => 'failed', 'message' => $message, 'finished_at' => now()]);
        $log->account?->forceFill(['last_error' => $message])->save();
    }

    private static function attemptKey(Account $account, string $job): string
    {
        return "sns:attempt:{$account->id}:{$job}";
    }

    public function collectorFor(Platform $platform): Collector
    {
        return match ($platform) {
            Platform::Instagram => app(InstagramCollector::class),
            Platform::Facebook => app(FacebookCollector::class),
            Platform::YouTube => app(YouTubeCollector::class),
            Platform::Threads => app(ThreadsCollector::class),
            Platform::TikTok => app(TikTokCollector::class),
            default => throw new SnsApiException($platform->label().' は手入力のみ対応です。'),
        };
    }

    /** @return Collection<int, Account> */
    private function collectable(): Collection
    {
        return Account::query()->collectable()->get()
            ->filter(fn (Account $a) => $a->platform->supportsApi() && $a->isConnected());
    }
}
