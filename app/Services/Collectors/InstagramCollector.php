<?php

namespace App\Services\Collectors;

use App\Exceptions\SnsApiException;
use App\Models\Account;
use App\Services\GraphClient;
use Illuminate\Support\Carbon;

/** Instagram(プロアカウント)。Facebookログインで得たページトークンで Instagram Graph API を呼ぶ */
class InstagramCollector extends Collector
{
    private const FIELDS = 'id,media_type,media_product_type,timestamp,permalink,thumbnail_url,media_url,caption';

    public function collectDaily(Account $account): string
    {
        $graph = GraphClient::meta();
        $since = $this->since();

        $media = $graph->paginate(
            $account->external_id.'/media',
            ['fields' => self::FIELDS, 'limit' => 50],
            $this->token($account),
            fn (array $item) => Carbon::parse($item['timestamp'])->gte($since),
        );

        $count = 0;
        foreach ($media as $item) {
            $format = ($item['media_product_type'] ?? 'FEED') === 'REELS' ? 'reel' : 'feed';
            if (($item['media_product_type'] ?? 'FEED') === 'AD') {
                continue;
            }
            $count += $this->record($graph, $account, $item, $format) ? 1 : 0;
        }

        return "投稿 {$count} 件を更新、".$this->collectAccountViews($graph, $account);
    }

    /**
     * アカウント全体の日別閲覧数(Business Suite の「閲覧数」と同じ指標)を、
     * フィード・リール・ストーリーズ・広告の内訳つきで保存する。
     * total_value は指定範囲の合計を返すので、1日ずつ問い合わせる。
     */
    private function collectAccountViews(GraphClient $graph, Account $account): string
    {
        [$start, $end, $backfill] = $this->accountLevelRange($account, 'ad', 30);

        try {
            $byDate = [];
            for ($day = $start; $day->lte($end); $day = $day->addDay()) {
                $insights = $graph->get($account->external_id.'/insights', [
                    'metric' => 'views',
                    'period' => 'day',
                    'metric_type' => 'total_value',
                    'breakdown' => 'media_product_type',
                    'since' => $day->getTimestamp(),
                    'until' => $day->addDay()->getTimestamp(),
                ], $this->token($account));

                $byDate[$day->toDateString()] = self::splitViews($insights);
            }
        } catch (SnsApiException $e) {
            if ($e->needsReconnect) {
                throw $e;
            }

            // アカウント全体の値が取れないときは、投稿ごとの累計の差分で代用する
            if ($backfill) {
                $this->recorder->rebuildDaily($account, now()->subDay());
                $this->recorder->rebuildDaily($account, now());
            }

            return '日別閲覧数は投稿の差分で代用(アカウント全体の取得に失敗: '.$e->getMessage().')';
        }

        $this->saveAccountLevel($account, $byDate, $backfill);
        $days = count($byDate);

        return "日別閲覧数 {$days} 日分を更新";
    }

    /** @return array<string, int> feed / reel / story / ad => 閲覧数 */
    public static function splitViews(array $insights): array
    {
        $views = ['feed' => 0, 'reel' => 0, 'story' => 0, 'ad' => 0];
        $row = collect($insights['data'] ?? [])->firstWhere('name', 'views') ?? [];
        $total = (int) ($row['total_value']['value'] ?? 0);
        $results = $row['total_value']['breakdowns'][0]['results'] ?? [];

        foreach ($results as $result) {
            $type = strtoupper((string) ($result['dimension_values'][0] ?? ''));
            $format = match ($type) {
                'REEL', 'REELS' => 'reel',
                'STORY' => 'story',
                'AD' => 'ad',
                default => 'feed', // POST / CAROUSEL_CONTAINER / FEED など
            };
            $views[$format] += (int) ($result['value'] ?? 0);
        }

        // 内訳が返らない・合計に届かない分はフィードに含める
        $views['feed'] += max(0, $total - array_sum($views));

        return $views;
    }

    public function collectStories(Account $account): string
    {
        $graph = GraphClient::meta();
        $stories = $graph->paginate($account->external_id.'/stories', ['fields' => self::FIELDS], $this->token($account));

        $count = 0;
        foreach ($stories as $item) {
            $count += $this->record($graph, $account, $item, 'story') ? 1 : 0;
        }

        if (! $this->usesAccountLevel($account, 'ad')) {
            $this->recorder->rebuildDaily($account, now());
        }

        return "ストーリーズ {$count} 件を更新";
    }

    private function record(GraphClient $graph, Account $account, array $item, string $format): bool
    {
        try {
            $insights = $graph->get($item['id'].'/insights', ['metric' => 'views'], $this->token($account));
        } catch (SnsApiException $e) {
            if ($e->needsReconnect) {
                throw $e;
            }

            // プロ化前の投稿など、個別に取れないものは飛ばす
            return false;
        }

        $this->recorder->recordPost($account, [
            'external_id' => $item['id'],
            'format' => $format,
            'published_at' => isset($item['timestamp']) ? Carbon::parse($item['timestamp']) : null,
            'permalink' => $item['permalink'] ?? null,
            'thumbnail_url' => $item['thumbnail_url'] ?? $item['media_url'] ?? null,
            'caption' => $item['caption'] ?? null,
        ], GraphClient::insightValue($insights, 'views') ?? 0);

        return true;
    }
}
