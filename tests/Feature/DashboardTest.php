<?php

namespace Tests\Feature;

use App\Enums\Platform;
use App\Models\Account;
use App\Models\Brand;
use App\Models\DailyMetric;
use App\Models\ManualEntry;
use App\Models\Post;
use App\Models\User;
use App\Services\Dashboard;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private Account $instagram;

    private Account $tiktok;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-05 10:00'));

        $brand = Brand::factory()->create(['name' => 'ブランドA']);
        $this->instagram = Account::factory()->for($brand)->platform(Platform::Instagram)->connected()->create(['name' => 'IG公式']);
        $this->tiktok = Account::factory()->for($brand)->platform(Platform::TikTok)->manual()->create(['name' => 'TT公式']);

        foreach (['2026-09-01' => 100, '2026-09-15' => 200, '2026-09-30' => 300, '2026-08-20' => 50] as $date => $views) {
            DailyMetric::create(['account_id' => $this->instagram->id, 'date' => $date, 'format' => 'reel', 'views' => $views]);
        }
        DailyMetric::create(['account_id' => $this->instagram->id, 'date' => '2026-09-10', 'format' => 'story', 'views' => 40]);

        // 8/16〜9/14 の30日に 3,000(1日100)→ 9月分は14日 = 1,400
        ManualEntry::create(['account_id' => $this->tiktok->id, 'format' => 'video', 'period_start' => '2026-08-16', 'period_end' => '2026-09-14', 'views' => 3000]);

        Post::create(['account_id' => $this->instagram->id, 'external_id' => 'p1', 'format' => 'reel', 'published_at' => '2026-09-05 12:00', 'views' => 5000, 'caption' => '9月のリール']);
        Post::create(['account_id' => $this->instagram->id, 'external_id' => 'p2', 'format' => 'feed', 'published_at' => '2026-09-20 12:00', 'views' => 700]);
        Post::create(['account_id' => $this->instagram->id, 'external_id' => 'p3', 'format' => 'reel', 'published_at' => '2026-08-05 12:00', 'views' => 9999]);
    }

    private function september(string $mode): array
    {
        $dashboard = new Dashboard(CarbonImmutable::parse('2026-09-01'), CarbonImmutable::parse('2026-09-30'), $mode);

        return $dashboard->build(User::factory()->admin()->create());
    }

    public function test_occurred_mode_sums_daily_values_and_prorated_manual_entries(): void
    {
        $data = $this->september(Dashboard::MODE_OCCURRED);
        [$ig, $tt] = $data['brands'][0]['accounts'];

        $this->assertSame(640, $ig['total']);
        $this->assertSame(['reel' => 600, 'story' => 40], $ig['formats']);
        $this->assertSame(1400, $tt['total']);
        $this->assertSame(2040, $data['total']);
        // 前期間 8/2〜8/31: リール50 + 手入力 8/16〜8/31 の16日 = 1,600
        $this->assertSame(1650, $data['previous']);
    }

    public function test_posted_mode_sums_posts_published_in_period_and_skips_manual(): void
    {
        $data = $this->september(Dashboard::MODE_POSTED);
        [$ig, $tt] = $data['brands'][0]['accounts'];

        $this->assertSame(5700, $ig['total']);
        $this->assertSame(['feed' => 700, 'reel' => 5000], $ig['formats']);
        $this->assertNull($tt['total']);
        $this->assertSame(9999, $data['previous']);
    }

    public function test_prorate_handles_partial_overlap(): void
    {
        $entry = new ManualEntry(['period_start' => '2026-09-01', 'period_end' => '2026-09-30', 'views' => 3000]);

        $this->assertSame(1000, Dashboard::prorate($entry, CarbonImmutable::parse('2026-09-21'), CarbonImmutable::parse('2026-10-31')));
        $this->assertSame(0, Dashboard::prorate($entry, CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-31')));
        $this->assertSame(3000, Dashboard::prorate($entry, CarbonImmutable::parse('2026-08-01'), CarbonImmutable::parse('2026-10-31')));
    }

    public function test_dashboard_page_renders_totals_and_breakdown(): void
    {
        $this->actingAs(User::factory()->operator()->create())
            ->get('/?start=2026-09-01&end=2026-09-30')
            ->assertOk()
            ->assertSee('ブランドA')
            ->assertSee('2,040')
            ->assertSee('9月のリール')
            ->assertSee('手入力');
    }

    public function test_viewer_sees_totals_without_breakdown(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/?start=2026-09-01&end=2026-09-30&expand=1')
            ->assertOk()
            ->assertSee('2,040')
            ->assertDontSee('9月のリール')
            ->assertDontSee('内訳も全表示');
    }

    public function test_operator_only_sees_assigned_brands(): void
    {
        $other = Brand::factory()->create(['name' => '他ブランド']);
        $operator = User::factory()->operator()->create();
        $operator->brands()->attach($other);

        $this->actingAs($operator)->get('/')->assertOk()->assertSee('他ブランド')->assertDontSee('ブランドA');
    }

    public function test_csv_export(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/export.csv?start=2026-09-01&end=2026-09-30');

        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('ブランドA,Instagram,IG公式,API,合計,640', $csv);
        $this->assertStringContainsString('ブランドA,TikTok,TT公式,手入力,動画,1400', $csv);
    }

    public function test_invalid_period_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/?start=2026-09-30&end=2026-09-01')
            ->assertSessionHasErrors('end');
    }
}
