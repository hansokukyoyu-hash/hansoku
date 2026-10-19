<?php

namespace App\Services\Collectors;

use App\Models\Account;
use App\Services\MetricsRecorder;
use Carbon\CarbonImmutable;

abstract class Collector
{
    public function __construct(protected readonly MetricsRecorder $recorder) {}

    /** 1日1回:投稿の累計閲覧数(と日次値)を更新する */
    abstract public function collectDaily(Account $account): string;

    /** 数時間おき:ストーリーズの累計閲覧数を更新する(対応SNSのみ) */
    public function collectStories(Account $account): string
    {
        return 'ストーリーズ非対応';
    }

    protected function token(Account $account): string
    {
        return (string) $account->credential('access_token');
    }

    protected function since(): CarbonImmutable
    {
        return now()->toImmutable()->subDays(config('sns.refresh_posts_days'))->startOfDay();
    }

    /**
     * アカウント全体の日別閲覧数を取り直す日付の範囲。
     * まだアカウント全体の値($marker の日次値)を一度も保存していなければ $backfillDays 日さかのぼる(3番目が true)。
     * 以降は直近 $refreshDays 日分を取り直す(Meta 側の数値は確定まで最大48時間遅れるため)。
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: bool}
     */
    protected function accountLevelRange(Account $account, string $marker, int $backfillDays, int $refreshDays = 3): array
    {
        $today = now()->toImmutable()->startOfDay();
        $backfill = ! $this->usesAccountLevel($account, $marker);

        return [$today->subDays($backfill ? $backfillDays : $refreshDays), $today, $backfill];
    }

    protected function usesAccountLevel(Account $account, string $marker): bool
    {
        return $account->dailyMetrics()->where('format', $marker)->exists();
    }

    /**
     * 取得できた日別値を保存する。初回(さかのぼり取得)は、それまでの投稿差分による日次値を消して入れ替える。
     *
     * @param  array<string, array<string, int>>  $byDate  日付 => [フォーマット => 閲覧数]
     */
    protected function saveAccountLevel(Account $account, array $byDate, bool $backfill): void
    {
        if ($backfill) {
            $account->dailyMetrics()->delete();
        }

        foreach ($byDate as $date => $formats) {
            foreach ($formats as $format => $views) {
                $this->recorder->putDaily($account, CarbonImmutable::parse($date), $format, $views);
            }
        }
    }
}
