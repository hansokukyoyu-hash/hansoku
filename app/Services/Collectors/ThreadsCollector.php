<?php

namespace App\Services\Collectors;

use App\Exceptions\SnsApiException;
use App\Models\Account;
use App\Services\GraphClient;
use App\Services\MetricsRecorder;
use App\Services\OAuth\ThreadsOAuth;
use Illuminate\Support\Carbon;

class ThreadsCollector extends Collector
{
    public function __construct(MetricsRecorder $recorder, private readonly ThreadsOAuth $oauth)
    {
        parent::__construct($recorder);
    }

    public function collectDaily(Account $account): string
    {
        $this->oauth->refreshIfExpiring($account);

        $graph = GraphClient::threads();
        $threads = $graph->paginate(
            $account->external_id.'/threads',
            ['fields' => 'id,media_type,permalink,timestamp,text,thumbnail_url', 'since' => $this->since()->getTimestamp(), 'limit' => 50],
            $this->token($account),
        );

        $count = 0;
        foreach ($threads as $item) {
            if (($item['media_type'] ?? null) === 'REPOST_FACADE') {
                continue; // 他人の投稿の再投稿は対象外
            }

            try {
                $insights = $graph->get($item['id'].'/insights', ['metric' => 'views'], $this->token($account));
            } catch (SnsApiException $e) {
                if ($e->needsReconnect) {
                    throw $e;
                }

                continue;
            }

            $this->recorder->recordPost($account, [
                'external_id' => $item['id'],
                'format' => 'post',
                'published_at' => isset($item['timestamp']) ? Carbon::parse($item['timestamp']) : null,
                'permalink' => $item['permalink'] ?? null,
                'thumbnail_url' => $item['thumbnail_url'] ?? null,
                'caption' => $item['text'] ?? null,
            ], GraphClient::insightValue($insights, 'views') ?? 0);
            $count++;
        }

        $this->recorder->rebuildDaily($account, now()->subDay());
        $this->recorder->rebuildDaily($account, now());

        return "投稿 {$count} 件を更新";
    }
}
