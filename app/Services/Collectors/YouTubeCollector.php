<?php

namespace App\Services\Collectors;

use App\Exceptions\SnsApiException;
use App\Models\Account;
use App\Services\MetricsRecorder;
use App\Services\OAuth\GoogleOAuth;
use DateInterval;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * YouTubeショート。日次の再生回数は YouTube Analytics API が直接返すのでそのまま保存する。
 * 動画ごとの累計(モード「イ」・投稿別)は動画単位のレポートと Data API で取る。
 *
 * creatorContentType はフィルタには使えない(400 になる)ため、ディメンションとして取得し、
 * SHORTS の行だけを使う。
 */
class YouTubeCollector extends Collector
{
    private const ANALYTICS = 'https://youtubeanalytics.googleapis.com/v2/reports';

    private const SHORTS = 'SHORTS';

    public function __construct(MetricsRecorder $recorder, private readonly GoogleOAuth $oauth)
    {
        parent::__construct($recorder);
    }

    public function collectDaily(Account $account): string
    {
        $token = $this->oauth->freshAccessToken($account);
        $today = now()->toDateString();

        // 初回は過去1年分をさかのぼって取る。2回目以降は直近7日分を毎回取り直す
        // (YouTube側の集計は2〜3日遅れて確定するため)
        // (最古の日次値が1年前に届いていなければ、まださかのぼり取得をしていないとみなす)
        $oldest = $account->dailyMetrics()->where('format', 'short')->min('date');
        $backfill = $oldest === null || $oldest > now()->subDays(300)->toDateString();
        $start = now()->subDays($backfill ? 365 : 7)->startOfDay();
        $daily = $this->report($token, [
            'startDate' => $start->toDateString(),
            'endDate' => $today,
            'metrics' => 'views',
            'dimensions' => 'day,creatorContentType',
        ]);
        $byDay = [];
        $types = [];
        foreach ($daily['rows'] ?? [] as [$day, $type, $views]) {
            $types[$type] = true;
            if (strtoupper((string) $type) === self::SHORTS) {
                $byDay[$day] = (int) $views;
            }
        }
        // ショートの再生が無い日は行が返らないので 0 を入れる
        for ($day = $start->copy(); $day->lte(now()); $day->addDay()) {
            $this->recorder->putDaily($account, $day, 'short', $byDay[$day->toDateString()] ?? 0);
        }

        // ショートごとの累計再生回数(上位200本)。失敗しても日次の合計は保存済みなので処理は続ける
        try {
            $count = $this->collectVideos($account, $token, $today);
        } catch (SnsApiException $e) {
            if ($e->needsReconnect) {
                throw $e;
            }

            return sprintf('日次 %d 日分を更新(ショート別の取得に失敗: %s)', count($byDay), $e->getMessage());
        }

        $note = $byDay === [] ? sprintf('(期間内にショートの再生データなし。返ってきた種類: %s)', $types === [] ? 'なし' : implode(', ', array_keys($types))) : '';

        return sprintf('日次 %d 日分、ショート %d 本を更新%s', count($byDay), $count, $note);
    }

    /**
     * 動画別レポートでは creatorContentType を指定できない("The query is not supported")ため、
     * 再生回数の多い動画を取り、長さ3分以内のものについて youtube.com/shorts/{id} が
     * リダイレクトされずに開けるか(=ショートか)で見分ける。判定結果はキャッシュする。
     */
    private function collectVideos(Account $account, string $token, string $today): int
    {
        $videos = $this->report($token, [
            'startDate' => '2020-09-01', // ショート開始以降
            'endDate' => $today,
            'metrics' => 'views',
            'dimensions' => 'video',
            'sort' => '-views',
            'maxResults' => 200,
        ]);
        $views = [];
        foreach ($videos['rows'] ?? [] as [$videoId, $count]) {
            $views[$videoId] = (int) $count;
        }

        $shorts = 0;
        foreach (array_chunk(array_keys($views), 50) as $ids) {
            $response = Http::withToken($token)->timeout(30)->get('https://www.googleapis.com/youtube/v3/videos', [
                'part' => 'snippet,contentDetails',
                'id' => implode(',', $ids),
            ]);
            if ($response->failed()) {
                throw SnsApiException::fromResponse('YouTube', $response);
            }

            foreach ($response->json('items', []) as $item) {
                if (! $this->isShort($account, $item)) {
                    continue;
                }

                $snippet = $item['snippet'] ?? [];
                $this->recorder->recordPost($account, [
                    'external_id' => $item['id'],
                    'format' => 'short',
                    'published_at' => isset($snippet['publishedAt']) ? Carbon::parse($snippet['publishedAt']) : null,
                    'permalink' => 'https://www.youtube.com/shorts/'.$item['id'],
                    'thumbnail_url' => $snippet['thumbnails']['medium']['url'] ?? $snippet['thumbnails']['default']['url'] ?? null,
                    'caption' => $snippet['title'] ?? null,
                ], $views[$item['id']] ?? 0);
                $shorts++;
            }
        }

        return $shorts;
    }

    private function isShort(Account $account, array $item): bool
    {
        $id = $item['id'];

        // 一度ショートと判定したものは保存済み
        if ($account->posts()->where('external_id', $id)->where('format', 'short')->exists()) {
            return true;
        }

        // ショートは最長3分
        if ($this->durationSeconds($item['contentDetails']['duration'] ?? '') > 180) {
            return false;
        }

        return Cache::remember("youtube:is-short:{$id}", now()->addDays(30), function () use ($id) {
            try {
                $response = Http::withoutRedirecting()->timeout(10)->get("https://www.youtube.com/shorts/{$id}");
            } catch (Throwable) {
                return true; // 判定できないときは長さで判断(3分以内)
            }

            // ショートでない動画は /watch へリダイレクトされる
            return ! $response->redirect();
        });
    }

    private function durationSeconds(string $iso): int
    {
        try {
            $interval = new DateInterval($iso);
        } catch (Throwable) {
            return PHP_INT_MAX;
        }

        return $interval->d * 86400 + $interval->h * 3600 + $interval->i * 60 + $interval->s;
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
