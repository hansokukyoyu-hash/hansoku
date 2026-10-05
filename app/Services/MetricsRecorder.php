<?php

namespace App\Services;

use App\Models\Account;
use App\Models\DailyMetric;
use App\Models\Post;
use App\Models\PostSnapshot;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * 収集した投稿の累計閲覧数を保存し、そこから日次の閲覧数を作る。
 *
 * 日次値 = その日の累計 - 前回保存した累計。
 * システムが初めて見た投稿は、投稿日が前日以降なら累計をそのまま、
 * それより古ければ過去分が一度に乗らないよう 0 として扱う(基準値にする)。
 */
class MetricsRecorder
{
    /**
     * @param  array{external_id: string, format: string, published_at: ?CarbonInterface, permalink?: ?string, thumbnail_url?: ?string, caption?: ?string}  $data
     */
    public function recordPost(Account $account, array $data, int $views, ?CarbonInterface $at = null): Post
    {
        $at = CarbonImmutable::instance($at ?? now());

        $post = Post::updateOrCreate(
            ['account_id' => $account->id, 'external_id' => $data['external_id']],
            [
                'format' => $data['format'],
                // APIはUTCで返すので、日付の境目がずれないよう日本時間に揃えて保存する
                'published_at' => isset($data['published_at'])
                    ? CarbonImmutable::instance($data['published_at'])->setTimezone(config('app.timezone'))
                    : null,
                'permalink' => $data['permalink'] ?? null,
                'thumbnail_url' => $data['thumbnail_url'] ?? null,
                'caption' => isset($data['caption']) ? mb_strimwidth($data['caption'], 0, 250, '…') : null,
                'views' => $views,
                'views_fetched_at' => $at,
            ],
        );

        PostSnapshot::updateOrCreate(
            ['post_id' => $post->id, 'date' => $at->toDateString()],
            ['views' => $views, 'fetched_at' => $at],
        );

        return $post;
    }

    /** 指定日の日次値を投稿ごとの累計の差分から作り直す */
    public function rebuildDaily(Account $account, CarbonInterface $date): void
    {
        $day = CarbonImmutable::instance($date)->startOfDay();
        $totals = array_fill_keys(array_keys($account->platform->formats()), 0);

        $snapshots = PostSnapshot::query()
            ->with('post')
            ->where('date', $day->toDateString())
            ->whereHas('post', fn ($q) => $q->where('account_id', $account->id))
            ->get();

        foreach ($snapshots as $snapshot) {
            $previous = PostSnapshot::query()
                ->where('post_id', $snapshot->post_id)
                ->where('date', '<', $day->toDateString())
                ->orderByDesc('date')
                ->value('views');

            if ($previous !== null) {
                $delta = max(0, $snapshot->views - (int) $previous);
            } else {
                $published = $snapshot->post->published_at;
                $isNew = $published === null || $published->copy()->startOfDay()->gte($day->subDay());
                $delta = $isNew ? $snapshot->views : 0;
            }

            $format = $snapshot->post->format;
            $totals[$format] = ($totals[$format] ?? 0) + $delta;
        }

        foreach ($totals as $format => $views) {
            $this->putDaily($account, $day, $format, $views);
        }
    }

    public function putDaily(Account $account, CarbonInterface $date, string $format, int $views): void
    {
        DailyMetric::updateOrCreate(
            ['account_id' => $account->id, 'date' => $date->toDateString(), 'format' => $format],
            ['views' => $views],
        );
    }
}
