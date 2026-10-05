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

        $this->recorder->rebuildDaily($account, now()->subDay());
        $this->recorder->rebuildDaily($account, now());

        return "投稿 {$count} 件を更新";
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

        $this->recorder->rebuildDaily($account, now());

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
