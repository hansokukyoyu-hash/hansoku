<?php

namespace Tests\Feature;

use App\Enums\Platform;
use App\Models\Account;
use App\Models\DailyMetric;
use App\Models\Post;
use App\Models\PostSnapshot;
use App\Services\CollectionRunner;
use App\Services\MetricsRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        ]);

        $runner = app(CollectionRunner::class);
        $this->assertSame('success', $runner->run($account, CollectionRunner::JOB_DAILY)->status);
        $this->assertSame('success', $runner->run($account, CollectionRunner::JOB_STORIES)->status);

        // 投稿期間外(m3)は取らない
        $this->assertSame(['m1' => 'reel', 'm2' => 'feed', 's1' => 'story'], Post::orderBy('external_id')->pluck('format', 'external_id')->all());
        // UTC 2026-10-04 23:30 は日本時間 10/5 08:30
        $this->assertSame('2026-10-05 08:30', Post::where('external_id', 's1')->first()->published_at->format('Y-m-d H:i'));
        $this->assertSame(1200, $this->daily($account, '2026-10-05', 'reel'));
        $this->assertSame(80, $this->daily($account, '2026-10-05', 'story'));
        $account->refresh();
        $this->assertNotNull($account->daily_synced_at);
        $this->assertNotNull($account->stories_synced_at);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'access_token=token-17841'));
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
                return str_contains($request->url(), 'dimensions=day')
                    ? Http::response(['rows' => [['2026-10-03', 150], ['2026-10-04', 220]]])
                    : Http::response(['rows' => [['v1', 9000]]]);
            },
            'www.googleapis.com/youtube/v3/videos*' => Http::response(['items' => [
                ['id' => 'v1', 'snippet' => ['publishedAt' => '2026-09-20T09:00:00Z', 'title' => 'ショート1']],
            ]]),
        ]);

        $log = app(CollectionRunner::class)->run($account, CollectionRunner::JOB_DAILY);

        $this->assertSame('success', $log->status, (string) $log->message);
        $this->assertSame(220, $this->daily($account, '2026-10-04', 'short'));
        $this->assertSame(9000, Post::where('external_id', 'v1')->value('views'));
        $this->assertSame('fresh', $account->fresh()->credential('access_token'));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'creatorContentType%3D%3DSHORTS') && $request->hasHeader('Authorization', 'Bearer fresh'));
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

    public function test_collect_command_runs_due_tasks(): void
    {
        Account::factory()->platform(Platform::Threads)->connected('th9')->create();
        Http::fake(['graph.threads.net/v1.0/th9/threads*' => Http::response(['data' => []])]);

        $this->artisan('sns:collect')->expectsOutputToContain('[success]')->assertSuccessful();
    }
}
