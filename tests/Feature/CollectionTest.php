<?php

namespace Tests\Feature;

use App\Enums\Platform;
use App\Models\Account;
use App\Models\DailyMetric;
use App\Models\Post;
use App\Models\PostSnapshot;
use App\Models\SyncLog;
use App\Services\CollectionRunner;
use App\Services\Collectors\FacebookCollector;
use App\Services\MetricsRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CollectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.meta.graph_version' => 'v23.0']);
        $this->travelTo(CarbonImmutable::parse('2026-10-05 04:00'));
    }

    private function daily(Account $account, string $date, string $format): ?int
    {
        return DailyMetric::where(['account_id' => $account->id, 'date' => $date, 'format' => $format])->value('views');
    }

    public function test_daily_values_are_differences_of_cumulative_views(): void
    {
        $account = Account::factory()->platform(Platform::Instagram)->connected()->create();
        $recorder = app(MetricsRecorder::class);
        $new = ['external_id' => 'new', 'format' => 'reel', 'published_at' => CarbonImmutable::parse('2026-10-03 20:00')];
        $old = ['external_id' => 'old', 'format' => 'reel', 'published_at' => CarbonImmutable::parse('2026-06-01 20:00')];

        // 10/3: 新しい投稿は累計がそのまま、古い投稿は初回なので基準値(0)
        $recorder->recordPost($account, $new, 100, CarbonImmutable::parse('2026-10-03 23:00'));
        $recorder->recordPost($account, $old, 5000, CarbonImmutable::parse('2026-10-03 23:00'));
        $recorder->rebuildDaily($account, CarbonImmutable::parse('2026-10-03'));
        $this->assertSame(100, $this->daily($account, '2026-10-03', 'reel'));

        // 10/4: 前日との差分
        $recorder->recordPost($account, $new, 350, CarbonImmutable::parse('2026-10-04 23:00'));
        $recorder->recordPost($account, $old, 5100, CarbonImmutable::parse('2026-10-04 23:00'));
        $recorder->rebuildDaily($account, CarbonImmutable::parse('2026-10-04'));
        $this->assertSame(350, $this->daily($account, '2026-10-04', 'reel'));

        // 同じ日に取り直すと上書き(重複しない)
        $recorder->recordPost($account, $new, 400, CarbonImmutable::parse('2026-10-04 23:30'));
        $recorder->rebuildDaily($account, CarbonImmutable::parse('2026-10-04'));
        $this->assertSame(400, $this->daily($account, '2026-10-04', 'reel'));
        $this->assertSame(4, PostSnapshot::count());
        $this->assertSame(0, $this->daily($account, '2026-10-04', 'feed'));
    }

    public function test_instagram_collects_media_and_stories(): void
    {
        $account = Account::factory()->platform(Platform::Instagram)->connected('17841')->create();

        Http::fake([
            'graph.facebook.com/v23.0/17841/media*' => Http::response(['data' => [
                ['id' => 'm1', 'media_product_type' => 'REELS', 'timestamp' => '2026-10-04T10:00:00+0000', 'permalink' => 'https://instagram.com/p/m1', 'caption' => 'リール'],
                ['id' => 'm2', 'media_product_type' => 'FEED', 'timestamp' => '2026-10-04T11:00:00+0000'],
                ['id' => 'm3', 'media_product_type' => 'FEED', 'timestamp' => '2025-01-01T11:00:00+0000'],
            ]]),
            'graph.facebook.com/v23.0/17841/stories*' => Http::response(['data' => [
                ['id' => 's1', 'media_product_type' => 'STORY', 'timestamp' => '2026-10-04T23:30:00+0000'],
            ]]),
            'graph.facebook.com/v23.0/m1/insights*' => Http::response(['data' => [['name' => 'views', 'values' => [['value' => 1200]]]]]),
            'graph.facebook.com/v23.0/m2/insights*' => Http::response(['data' => [['name' => 'views', 'total_value' => ['value' => 300]]]]),
            'graph.facebook.com/v23.0/s1/insights*' => Http::response(['data' => [['name' => 'views', 'values' => [['value' => 80]]]]]),
            // アカウント全体の日別閲覧数(Business Suite と同じ指標)
            'graph.facebook.com/v23.0/17841/insights*' => Http::response(['data' => [[
                'name' => 'views', 'period' => 'day',
                'total_value' => ['value' => 1000, 'breakdowns' => [[
                    'dimension_keys' => ['media_product_type'],
                    'results' => [
                        ['dimension_values' => ['REEL'], 'value' => 600],
                        ['dimension_values' => ['STORY'], 'value' => 100],
                        ['dimension_values' => ['POST'], 'value' => 150],
                        ['dimension_values' => ['CAROUSEL_CONTAINER'], 'value' => 50],
                        ['dimension_values' => ['AD'], 'value' => 100],
                    ],
                ]]],
            ]]]),
        ]);

        $runner = app(CollectionRunner::class);
        $daily = $runner->run($account, CollectionRunner::JOB_DAILY);
        $this->assertSame('success', $daily->status, (string) $daily->message);
        $this->assertSame('success', $runner->run($account, CollectionRunner::JOB_STORIES)->status);

        // 投稿期間外(m3)は取らない
        $this->assertSame(['m1' => 'reel', 'm2' => 'feed', 's1' => 'story'], Post::orderBy('external_id')->pluck('format', 'external_id')->all());
        // UTC 2026-10-04 23:30 は日本時間 10/5 08:30
        $this->assertSame('2026-10-05 08:30', Post::where('external_id', 's1')->first()->published_at->format('Y-m-d H:i'));
        // 日別値はアカウント全体の値。ストーリーズ収集で投稿差分に上書きされない
        $this->assertSame(600, $this->daily($account, '2026-10-05', 'reel'));
        $this->assertSame(100, $this->daily($account, '2026-10-05', 'story'));
        $this->assertSame(200, $this->daily($account, '2026-10-05', 'feed'));
        $this->assertSame(100, $this->daily($account, '2026-10-05', 'ad'));
        // 初回は30日さかのぼる
        $this->assertSame(600, $this->daily($account, '2026-09-05', 'reel'));
        Http::assertSent(fn ($request) => str_contains($request->url(), '17841/insights') && str_contains($request->url(), 'breakdown=media_product_type') && str_contains($request->url(), 'metric_type=total_value'));
        $account->refresh();
        $this->assertNotNull($account->daily_synced_at);
        $this->assertNotNull($account->stories_synced_at);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'access_token=token-17841'));
    }

    public function test_instagram_falls_back_to_post_differences_when_account_views_fail(): void
    {
        $account = Account::factory()->platform(Platform::Instagram)->connected('17842')->create();

        Http::fake([
            'graph.facebook.com/v23.0/17842/media*' => Http::response(['data' => [
                ['id' => 'm9', 'media_product_type' => 'REELS', 'timestamp' => '2026-10-04T10:00:00+0000'],
            ]]),
            'graph.facebook.com/v23.0/m9/insights*' => Http::response(['data' => [['name' => 'views', 'values' => [['value' => 500]]]]]),
            'graph.facebook.com/v23.0/17842/insights*' => Http::response(['error' => ['message' => '(#100) metric not supported', 'code' => 100]], 400),
        ]);

        $log = app(CollectionRunner::class)->run($account, CollectionRunner::JOB_DAILY);

        $this->assertSame('success', $log->status);
        $this->assertStringContainsString('投稿の差分で代用', (string) $log->message);
        $this->assertSame(500, $this->daily($account, '2026-10-05', 'reel'));
    }

    public function test_facebook_stores_page_views_with_ads_breakdown(): void
    {
        $account = Account::factory()->platform(Platform::Facebook)->connected('page2')->create();
        // 以前の投稿差分による日次値は、初回のさかのぼり取得で入れ替わる
        DailyMetric::create(['account_id' => $account->id, 'date' => '2026-10-04', 'format' => 'post', 'views' => 9]);

        Http::fake([
            'graph.facebook.com/v23.0/page2/posts*' => Http::response(['data' => []]),
            'graph.facebook.com/v23.0/page2/insights*' => function ($request) {
                // end_time は太平洋時間の翌0時(10/4 分 = 2026-10-05T07:00:00+0000)
                if (str_contains($request->url(), 'breakdown=is_from_ads')) {
                    return Http::response(['data' => [['name' => 'page_media_view', 'period' => 'day', 'values' => [
                        ['value' => ['true' => 300, 'false' => 700], 'end_time' => '2026-10-05T07:00:00+0000'],
                    ]]]]);
                }

                return Http::response(['data' => [['name' => 'page_media_view', 'period' => 'day', 'values' => [
                    ['value' => 1000, 'end_time' => '2026-10-05T07:00:00+0000'],
                    ['value' => 400, 'end_time' => '2026-10-04T07:00:00+0000'],
                ]]]]);
            },
        ]);

        $log = app(CollectionRunner::class)->run($account, CollectionRunner::JOB_DAILY);

        $this->assertSame('success', $log->status, (string) $log->message);
        $this->assertSame(700, $this->daily($account, '2026-10-04', 'organic'));
        $this->assertSame(300, $this->daily($account, '2026-10-04', 'ad'));
        $this->assertSame(400, $this->daily($account, '2026-10-03', 'organic'));
        $this->assertSame(0, $this->daily($account, '2026-10-03', 'ad'));
        $this->assertNull($this->daily($account, '2026-10-04', 'post'));
    }

    public function test_facebook_ad_values_as_separate_rows(): void
    {
        $ads = FacebookCollector::dailyAdValues(['data' => [
            ['name' => 'page_media_view', 'breakdowns' => ['is_from_ads' => 'true'], 'values' => [['value' => 40, 'end_time' => '2026-10-05T07:00:00+0000']]],
            ['name' => 'page_media_view', 'breakdowns' => ['is_from_ads' => 'false'], 'values' => [['value' => 60, 'end_time' => '2026-10-05T07:00:00+0000']]],
        ]]);

        $this->assertSame(['2026-10-04' => 40], $ads);
    }

    public function test_expired_token_marks_account_for_reconnect(): void
    {
        $account = Account::factory()->platform(Platform::Facebook)->connected('page1')->create();
        Http::fake(['*' => Http::response(['error' => ['message' => 'Session has expired', 'code' => 190]], 400)]);

        $log = app(CollectionRunner::class)->run($account, CollectionRunner::JOB_DAILY);

        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('再連携が必要', $account->fresh()->last_error);
        $this->assertSame('error', $account->fresh()->status);
    }

    public function test_youtube_stores_daily_shorts_views_and_videos(): void
    {
        $account = Account::factory()->platform(Platform::YouTube)->connected('UC1')->create([
            'credentials' => ['access_token' => 'old', 'refresh_token' => 'r1', 'expires_at' => now()->subMinute()->toIso8601String()],
        ]);

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'fresh', 'expires_in' => 3600]),
            'youtubeanalytics.googleapis.com/*' => function ($request) {
                // creatorContentType はフィルタに使えないのでディメンションで受け取る
                $this->assertStringNotContainsString('filters=', $request->url());

                return str_contains($request->url(), 'dimensions=day')
                    ? Http::response(['rows' => [['2026-10-03', 'SHORTS', 150], ['2026-10-04', 'SHORTS', 220], ['2026-10-04', 'VIDEO_ON_DEMAND', 999]]])
                    : Http::response(['rows' => [['v1', 9000], ['v2', 50000], ['v3', 700]]]);
            },
            'www.googleapis.com/youtube/v3/videos*' => Http::response(['items' => [
                ['id' => 'v1', 'snippet' => ['publishedAt' => '2026-09-20T09:00:00Z', 'title' => 'ショート1'], 'contentDetails' => ['duration' => 'PT45S']],
                ['id' => 'v2', 'snippet' => ['title' => '長い動画'], 'contentDetails' => ['duration' => 'PT12M3S']],
                ['id' => 'v3', 'snippet' => ['title' => '短い普通の動画'], 'contentDetails' => ['duration' => 'PT1M']],
            ]]),
            'www.youtube.com/shorts/v1' => Http::response('', 200),
            'www.youtube.com/shorts/v3' => Http::response('', 303, ['Location' => 'https://www.youtube.com/watch?v=v3']),
        ]);

        $log = app(CollectionRunner::class)->run($account, CollectionRunner::JOB_DAILY);

        $this->assertSame('success', $log->status, (string) $log->message);
        $this->assertSame(220, $this->daily($account, '2026-10-04', 'short'));
        // 通常動画も YouTube Studio と同じく合計に含めるため別に保存する
        $this->assertSame(999, $this->daily($account, '2026-10-04', 'video'));
        $this->assertSame(0, $this->daily($account, '2026-10-04', 'live'));
        $this->assertSame(9000, Post::where('external_id', 'v1')->value('views'));
        $this->assertSame('fresh', $account->fresh()->credential('access_token'));
        $this->assertSame(0, $this->daily($account, '2026-10-02', 'short'));
        // 初回は過去1年分をさかのぼる
        Http::assertSent(fn ($request) => str_contains($request->url(), 'dimensions=day') && str_contains($request->url(), 'startDate=2025-10-05'));
        $this->assertSame(0, $this->daily($account, '2025-10-05', 'short'));
        $this->assertNull(Post::where('external_id', 'v2')->first());
        $this->assertNull(Post::where('external_id', 'v3')->first());
        Http::assertSent(fn ($request) => str_contains($request->url(), 'creatorContentType') && $request->hasHeader('Authorization', 'Bearer fresh'));
    }

    public function test_youtube_keeps_daily_values_when_video_report_fails(): void
    {
        $account = Account::factory()->platform(Platform::YouTube)->connected('UC2')->create();

        Http::fake([
            'youtubeanalytics.googleapis.com/*' => function ($request) {
                if (str_contains($request->url(), 'dimensions=day')) {
                    return Http::response(['rows' => [['2026-10-04', 'SHORTS', 220]]]);
                }
                // 動画別レポートはショート開始(2020-09-01)以降を指定していること
                $this->assertStringContainsString('startDate=2020-09-01', $request->url());

                return Http::response(['error' => ['code' => 400, 'message' => 'The query is not supported.']], 400);
            },
        ]);

        $log = app(CollectionRunner::class)->run($account, CollectionRunner::JOB_DAILY);

        $this->assertSame('success', $log->status);
        $this->assertStringContainsString('ショート別の取得に失敗', (string) $log->message);
        $this->assertSame(220, $this->daily($account, '2026-10-04', 'short'));
    }

    public function test_threads_refreshes_expiring_token(): void
    {
        $account = Account::factory()->platform(Platform::Threads)->connected('th1')->create([
            'credentials' => ['access_token' => 'old', 'expires_at' => now()->addDays(2)->toIso8601String()],
        ]);

        Http::fake([
            'graph.threads.net/refresh_access_token*' => Http::response(['access_token' => 'new', 'expires_in' => 5184000]),
            'graph.threads.net/v1.0/th1/threads*' => Http::response(['data' => [
                ['id' => 't1', 'media_type' => 'TEXT_POST', 'timestamp' => '2026-10-04T10:00:00+0000', 'text' => 'こんにちは'],
                ['id' => 't2', 'media_type' => 'REPOST_FACADE', 'timestamp' => '2026-10-04T10:00:00+0000'],
            ]]),
            'graph.threads.net/v1.0/t1/insights*' => Http::response(['data' => [['name' => 'views', 'values' => [['value' => 42]]]]]),
        ]);

        $this->assertSame('success', app(CollectionRunner::class)->run($account, CollectionRunner::JOB_DAILY)->status);
        $this->assertSame('new', $account->fresh()->credential('access_token'));
        $this->assertSame(['t1'], Post::pluck('external_id')->all());
        $this->assertSame(42, $this->daily($account, '2026-10-05', 'post'));
    }

    public function test_due_tasks_prioritise_stories_and_respect_limit(): void
    {
        $ig = Account::factory()->platform(Platform::Instagram)->connected('1')->create(['daily_synced_at' => now()->subDay()]);
        $yt = Account::factory()->platform(Platform::YouTube)->connected('2')->create();
        Account::factory()->platform(Platform::X)->manual()->create();
        Account::factory()->platform(Platform::Threads)->create(); // 未連携
        Account::factory()->platform(Platform::Facebook)->connected('3')->create([
            'daily_synced_at' => now(), 'stories_synced_at' => now()->subMinutes(30),
        ]);

        $tasks = app(CollectionRunner::class)->dueTasks(10);
        $this->assertSame(
            [[$ig->id, 'stories'], [$yt->id, 'daily'], [$ig->id, 'daily']],
            array_map(fn ($t) => [$t[0]->id, $t[1]], $tasks),
        );
        $this->assertCount(2, app(CollectionRunner::class)->dueTasks(2));

        // 日次の収集開始時刻より前は日次を選ばない
        $this->travelTo(CarbonImmutable::parse('2026-10-05 02:00'));
        $this->assertSame(['stories'], array_column(app(CollectionRunner::class)->dueTasks(10), 1));
    }

    public function test_interrupted_task_is_skipped_and_stale_log_closed(): void
    {
        $runner = app(CollectionRunner::class);
        $a = Account::factory()->platform(Platform::YouTube)->connected('1')->create(['daily_synced_at' => now()->subDays(3)]);
        $b = Account::factory()->platform(Platform::YouTube)->connected('2')->create(['daily_synced_at' => now()->subDays(2)]);

        // a の日次が強制終了された(目印と「実行中」の記録だけが残った)状態
        Cache::put("sns:attempt:{$a->id}:daily", true, now()->addHour());
        $log = SyncLog::create(['account_id' => $a->id, 'job' => 'daily', 'status' => 'running', 'started_at' => now()->subHour()]);

        $this->assertSame([$b->id], array_map(fn ($t) => $t[0]->id, $runner->dueTasks(10)));

        $this->assertSame(1, $runner->closeStaleLogs());
        $this->assertSame('failed', $log->fresh()->status);
        $this->assertStringContainsString('打ち切られました', $a->fresh()->last_error);

        // 時間が経てば再び選ばれる
        $this->travel(CollectionRunner::RETRY_AFTER_MINUTES + 1)->minutes();
        $this->assertSame([$a->id, $b->id], array_map(fn ($t) => $t[0]->id, $runner->dueTasks(10)));
    }

    public function test_collect_command_runs_due_tasks(): void
    {
        Account::factory()->platform(Platform::Threads)->connected('th9')->create();
        Http::fake(['graph.threads.net/v1.0/th9/threads*' => Http::response(['data' => []])]);

        $this->artisan('sns:collect')->expectsOutputToContain('[success]')->assertSuccessful();
    }
}
