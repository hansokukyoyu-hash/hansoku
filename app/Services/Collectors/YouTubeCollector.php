<?php

namespace App\Services\Collectors;

use App\Exceptions\SnsApiException;
use App\Models\Account;
use App\Services\MetricsRecorder;
use App\Services\OAuth\GoogleOAuth;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * YouTubeショート。日次の再生回数は YouTube Analytics API が直接返すのでそのまま保存する。
 * 動画ごとの累計(モード「イ」・投稿別)は動画単位のレポートと Data API で取る。
 */
class YouTubeCollector extends Collector
{
    private const ANALYTICS = 'https://youtubeanalytics.googleapis.com/v2/reports';

    private const SHORTS = 'creatorContentType==SHORTS';

    public function __construct(MetricsRecorder $recorder, private readonly GoogleOAuth $oauth)
    {
        parent::__construct($recorder);
    }

    public function collectDaily(Account $account): string
    {
        $token = $this->oauth->freshAccessToken($account);
        $today = now()->toDateString();

        // 直近7日分を毎回取り直す(YouTube側の集計は1〜2日遅れて確定するため)
        $daily = $this->report($token, [
            'startDate' => now()->subDays(7)->toDateString(),
            'endDate' => $today,
            'metrics' => 'views',
            'dimensions' => 'day',
            'filters' => self::SHORTS,
        ]);
        foreach ($daily['rows'] ?? [] as [$day, $views]) {
            $this->recorder->putDaily($account, Carbon::parse($day), 'short', (int) $views);
        }

        // ショートごとの累計再生回数(上位200本)
        $videos = $this->report($token, [
            'startDate' => '2010-01-01',
            'endDate' => $today,
            'metrics' => 'views',
            'dimensions' => 'video',
            'filters' => self::SHORTS,
            'sort' => '-views',
            'maxResults' => 200,
        ]);
        $views = [];
        foreach ($videos['rows'] ?? [] as [$videoId, $count]) {
            $views[$videoId] = (int) $count;
        }

        foreach (array_chunk(array_keys($views), 50) as $ids) {
            $response = Http::withToken($token)->timeout(30)->get('https://www.googleapis.com/youtube/v3/videos', [
                'part' => 'snippet',
                'id' => implode(',', $ids),
            ]);
            if ($response->failed()) {
                throw SnsApiException::fromResponse('YouTube', $response);
            }

            foreach ($response->json('items', []) as $item) {
                $snippet = $item['snippet'] ?? [];
                $this->recorder->recordPost($account, [
                    'external_id' => $item['id'],
                    'format' => 'short',
                    'published_at' => isset($snippet['publishedAt']) ? Carbon::parse($snippet['publishedAt']) : null,
                    'permalink' => 'https://www.youtube.com/shorts/'.$item['id'],
                    'thumbnail_url' => $snippet['thumbnails']['medium']['url'] ?? $snippet['thumbnails']['default']['url'] ?? null,
                    'caption' => $snippet['title'] ?? null,
                ], $views[$item['id']] ?? 0);
            }
        }

        return sprintf('日次 %d 日分、ショート %d 本を更新', count($daily['rows'] ?? []), count($views));
    }

    private function report(string $token, array $params): array
    {
        $response = Http::withToken($token)->timeout(30)->get(self::ANALYTICS, ['ids' => 'channel==MINE'] + $params);
        if ($response->failed()) {
            throw SnsApiException::fromResponse('YouTube Analytics', $response);
        }

        return $response->json() ?? [];
    }
}
