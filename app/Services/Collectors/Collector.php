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
}
