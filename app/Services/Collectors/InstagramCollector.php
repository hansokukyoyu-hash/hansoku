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

        $this->recorder->rebuildDaily($account, now()->subDay());
        $this->recorder->rebuildDaily($account, now());

        return "投稿 {$count} 件を更新";
    }

    public function collectStories(Account $account): string
    {
        $graph = GraphClient::meta();
        $stories = $graph->paginate($account->external_id.'/stories', ['fields' => self::FIELDS], $this->token($account));

        $count = 0;
        foreach ($stories as $item) {
            $count += $this->record($graph, $account, $item, 'story') ? 1 : 0;
        }

        $this->recorder->rebuildDaily($account, now());

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
