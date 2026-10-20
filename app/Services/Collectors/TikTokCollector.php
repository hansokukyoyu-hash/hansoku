<?php

namespace App\Services\Collectors;

use App\Models\Account;
use App\Services\MetricsRecorder;
use App\Services\OAuth\TikTokOAuth;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * TikTok Display API。動画ごとの累計再生回数しか取れないため、Threads と同じく
 * 毎日の累計の差を日次の再生回数とする。古い動画も再生され続けるので全件を取り直す。
 */
class TikTokCollector extends Collector
{
    /** 1ページ20件 × 50ページ = 最新1,000本まで */
    private const MAX_PAGES = 50;

    public function __construct(MetricsRecorder $recorder, private readonly TikTokOAuth $oauth)
    {
        parent::__construct($recorder);
    }

    public function collectDaily(Account $account): string
    {
        $token = $this->oauth->freshAccessToken($account);

        $count = 0;
        $cursor = null;
        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $response = Http::withToken($token)->timeout(30)
                ->post('https://open.tiktokapis.com/v2/video/list/?fields=id,title,video_description,create_time,cover_image_url,share_url,view_count', array_filter([
                    'max_count' => 20,
                    'cursor' => $cursor,
                ], fn ($v) => $v !== null));
            TikTokOAuth::check($response);

            foreach ($response->json('data.videos', []) as $video) {
                $this->recorder->recordPost($account, [
                    'external_id' => (string) $video['id'],
                    'format' => 'video',
                    'published_at' => isset($video['create_time']) ? Carbon::createFromTimestamp($video['create_time']) : null,
                    'permalink' => $video['share_url'] ?? null,
                    'thumbnail_url' => $video['cover_image_url'] ?? null,
                    'caption' => ($video['title'] ?? '') ?: ($video['video_description'] ?? null),
                ], (int) ($video['view_count'] ?? 0));
                $count++;
            }

            if (! $response->json('data.has_more')) {
                break;
            }
            $cursor = $response->json('data.cursor');
        }

        $this->recorder->rebuildDaily($account, now()->subDay());
        $this->recorder->rebuildDaily($account, now());

        return "動画 {$count} 件を更新";
    }
}
