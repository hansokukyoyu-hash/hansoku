<?php

namespace Tests\Feature;

use App\Enums\Platform;
use App\Models\Account;
use App\Models\Brand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_can_enter_views_for_assigned_brand(): void
    {
        $account = Account::factory()->platform(Platform::X)->manual()->create();
        $operator = User::factory()->operator()->create();
        $operator->brands()->attach($account->brand);

        $this->actingAs($operator)->post("/manual/{$account->id}", [
            'format' => 'post', 'period_start' => '2026-09-01', 'period_end' => '2026-09-30', 'views' => 12345,
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertDatabaseHas('manual_entries', ['account_id' => $account->id, 'views' => 12345, 'created_by' => $operator->id]);
        $this->actingAs($operator)->get("/manual/{$account->id}")->assertOk()->assertSee('12,345');
    }

    public function test_operator_cannot_enter_for_other_brand(): void
    {
        $account = Account::factory()->platform(Platform::X)->manual()->create();
        $operator = User::factory()->operator()->create();
        $operator->brands()->attach(Brand::factory()->create());

        $this->actingAs($operator)->post("/manual/{$account->id}", [
            'format' => 'post', 'period_start' => '2026-09-01', 'period_end' => '2026-09-30', 'views' => 1,
        ])->assertForbidden();
    }

    public function test_viewer_cannot_enter(): void
    {
        $account = Account::factory()->platform(Platform::TikTok)->manual()->create();

        $viewer = User::factory()->create();
        $this->actingAs($viewer)->post("/manual/{$account->id}", [
            'format' => 'video', 'period_start' => '2026-09-01', 'period_end' => '2026-09-30', 'views' => 1,
        ])->assertForbidden();
        $this->actingAs($viewer)->get('/manual')->assertForbidden();
    }

    public function test_format_must_belong_to_platform(): void
    {
        $account = Account::factory()->platform(Platform::TikTok)->manual()->create();

        $this->actingAs(User::factory()->admin()->create())->post("/manual/{$account->id}", [
            'format' => 'reel', 'period_start' => '2026-09-01', 'period_end' => '2026-09-30', 'views' => 1,
        ])->assertSessionHasErrors('format');
    }
}
