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
use App\Services\Collectors\YouTubeCollector;
use Illuminate\Support\Collection;
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

        // ストーリーズは消える前に取りたいので先に処理する
        usort($tasks, fn ($x, $y) => ($x[1] === self::JOB_STORIES ? 0 : 1) <=> ($y[1] === self::JOB_STORIES ? 0 : 1));

        return array_slice($tasks, 0, $limit);
    }

    public function run(Account $account, string $job): SyncLog
    {
        $log = SyncLog::create([
            'account_id' => $account->id,
            'job' => $job,
            'status' => 'running',
            'started_at' => now(),
        ]);

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

        return $log;
    }

    public function collectorFor(Platform $platform): Collector
    {
        return match ($platform) {
            Platform::Instagram => app(InstagramCollector::class),
            Platform::Facebook => app(FacebookCollector::class),
            Platform::YouTube => app(YouTubeCollector::class),
            Platform::Threads => app(ThreadsCollector::class),
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
