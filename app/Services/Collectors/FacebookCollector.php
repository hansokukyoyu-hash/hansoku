<?php

namespace App\Services\Collectors;

use App\Exceptions\SnsApiException;
use App\Models\Account;
use App\Services\GraphClient;
use Illuminate\Support\Carbon;

/** Facebookページ。投稿とストーリーズの閲覧数をページトークンで取る */
class FacebookCollector extends Collector
{
    public function collectDaily(Account $account): string
    {
        $graph = GraphClient::meta();
        $posts = $graph->paginate(
            $account->external_id.'/posts',
            ['fields' => 'id,created_time,permalink_url,full_picture,message', 'since' => $this->since()->getTimestamp(), 'limit' => 50],
            $this->token($account),
        );

        $count = 0;
        foreach ($posts as $item) {
            $count += $this->record($graph, $account, $item['id'], 'post', [
                'published_at' => isset($item['created_time']) ? Carbon::parse($item['created_time']) : null,
                'permalink' => $item['permalink_url'] ?? null,
                'thumbnail_url' => $item['full_picture'] ?? null,
                'caption' => $item['message'] ?? null,
            ]) ? 1 : 0;
        }

        return "投稿 {$count} 件を更新、".$this->collectPageViews($graph, $account);
    }

    /**
     * ページ全体の日別閲覧数(page_media_view。Business Suite の「閲覧数」と同じ指標)を保存する。
     * 広告分は is_from_ads の内訳で分け、残りをオーガニックとする。
     */
    private function collectPageViews(GraphClient $graph, Account $account): string
    {
        [$start, $end, $backfill] = $this->accountLevelRange($account, 'ad', 85); // since〜until は1回90日まで
        $params = [
            'metric' => 'page_media_view',
            'period' => 'day',
            'since' => $start->getTimestamp(),
            'until' => $end->addDay()->getTimestamp(),
        ];

        try {
            $totals = self::dailyValues($graph->get($account->external_id.'/insights', $params, $this->token($account)));
        } catch (SnsApiException $e) {
            if ($e->needsReconnect) {
                throw $e;
            }

            if ($backfill) {
                $this->recorder->rebuildDaily($account, now()->subDay());
                $this->recorder->rebuildDaily($account, now());
            }

            return '日別閲覧数は投稿の差分で代用(ページ全体の取得に失敗: '.$e->getMessage().')';
        }

        // 広告の内訳。取れなければ広告0として扱う(合計は正しいまま)
        $ads = [];
        try {
            $ads = self::dailyAdValues($graph->get($account->external_id.'/insights', $params + ['breakdown' => 'is_from_ads'], $this->token($account)));
        } catch (SnsApiException $e) {
            if ($e->needsReconnect) {
                throw $e;
            }
        }

        $byDate = [];
        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $date = $day->toDateString();
            $total = $totals[$date] ?? 0;
            $ad = min($total, $ads[$date] ?? 0);
            $byDate[$date] = ['organic' => $total - $ad, 'ad' => $ad];
        }
        $this->saveAccountLevel($account, $byDate, $backfill);

        return sprintf('日別閲覧数 %d 日分を更新', count($totals));
    }

    /**
     * Page Insights の日別値を日付 => 値 にする。end_time はその日の終わり(太平洋時間の翌0時)なので、
     * 1秒戻して太平洋時間の日付を採る。
     *
     * @return array<string, int>
     */
    public static function dailyValues(array $insights): array
    {
        $values = [];
        foreach ($insights['data'] ?? [] as $row) {
            foreach ($row['values'] ?? [] as $value) {
                if (! isset($value['end_time']) || is_array($value['value'] ?? null)) {
                    continue;
                }
                $date = Carbon::parse($value['end_time'])->subSecond()->setTimezone('America/Los_Angeles')->toDateString();
                $values[$date] = ($values[$date] ?? 0) + (int) ($value['value'] ?? 0);
            }
        }

        return $values;
    }

    /**
     * is_from_ads 内訳つきの応答から、日付 => 広告分 を取り出す。
     * 応答の形が版によって異なるため、value が {"true": n, "false": m} の形と、
     * 内訳ごとに別の行(breakdowns / dimension_values に true)で返る形の両方を受け付ける。
     *
     * @return array<string, int>
     */
    public static function dailyAdValues(array $insights): array
    {
        $ads = [];
        foreach ($insights['data'] ?? [] as $row) {
            $rowIsAd = self::isAdFlag($row['breakdowns']['is_from_ads'] ?? $row['dimension_values'][0] ?? null);

            foreach ($row['values'] ?? [] as $value) {
                if (! isset($value['end_time'])) {
                    continue;
                }
                $date = Carbon::parse($value['end_time'])->subSecond()->setTimezone('America/Los_Angeles')->toDateString();
                $v = $value['value'] ?? 0;

                if (is_array($v)) {
                    foreach ($v as $key => $count) {
                        if (self::isAdFlag($key)) {
                            $ads[$date] = ($ads[$date] ?? 0) + (int) $count;
                        }
                    }
                } elseif ($rowIsAd) {
                    $ads[$date] = ($ads[$date] ?? 0) + (int) $v;
                }
            }
        }

        return $ads;
    }

    private static function isAdFlag(mixed $flag): bool
    {
        if ($flag === true || $flag === 1) {
            return true;
        }

        return in_array(strtolower((string) $flag), ['true', '1'], true);
    }

    public function collectStories(Account $account): string
    {
        $graph = GraphClient::meta();
        $stories = $graph->paginate(
            $account->external_id.'/stories',
            ['fields' => 'post_id,creation_time,url,media_type,status'],
            $this->token($account),
            fn (array $item) => Carbon::createFromTimestamp((int) ($item['creation_time'] ?? 0))->gte(now()->subDays(2)),
        );

        $count = 0;
        foreach ($stories as $item) {
            if (empty($item['post_id'])) {
                continue;
            }
            $count += $this->record($graph, $account, $item['post_id'], 'story', [
                'published_at' => isset($item['creation_time']) ? Carbon::createFromTimestamp((int) $item['creation_time']) : null,
                'permalink' => $item['url'] ?? null,
            ]) ? 1 : 0;
        }

        if (! $this->usesAccountLevel($account, 'ad')) {
            $this->recorder->rebuildDaily($account, now());
        }

        return "ストーリーズ {$count} 件を更新";
    }

    private function record(GraphClient $graph, Account $account, string $id, string $format, array $data): bool
    {
        $metric = config('sns.facebook_post_views_metric');

        try {
            $insights = $graph->get($id.'/insights', ['metric' => $metric], $this->token($account));
        } catch (SnsApiException $e) {
            if ($e->needsReconnect) {
                throw $e;
            }

            return false;
        }

        $this->recorder->recordPost($account, ['external_id' => $id, 'format' => $format] + $data, GraphClient::insightValue($insights, $metric) ?? 0);

        return true;
    }
}
