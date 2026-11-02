<?php

namespace Tests\Feature;

use App\Enums\Platform;
use App\Models\Account;
use App\Models\Brand;
use App\Models\ManualEntry;
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

    public function test_posts_are_optional_and_shown_in_history(): void
    {
        $account = Account::factory()->platform(Platform::TikTok)->manual()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post("/manual/{$account->id}", [
            'format' => 'video', 'period_start' => '2026-10-01', 'period_end' => '2026-10-31', 'views' => 500, 'posts' => 8,
        ])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post("/manual/{$account->id}", [
            'format' => 'video', 'period_start' => '2026-09-01', 'period_end' => '2026-09-30', 'views' => 400, 'posts' => '',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('manual_entries', ['account_id' => $account->id, 'period_start' => '2026-10-01', 'posts' => 8]);
        $this->assertDatabaseHas('manual_entries', ['account_id' => $account->id, 'period_start' => '2026-09-01', 'posts' => null]);
        $this->actingAs($admin)->get("/manual/{$account->id}")->assertOk()->assertSee('投稿本数')->assertSee('未入力');

        $this->actingAs($admin)->post("/manual/{$account->id}", [
            'format' => 'video', 'period_start' => '2026-08-01', 'period_end' => '2026-08-31', 'views' => 1, 'posts' => -1,
        ])->assertSessionHasErrors('posts');
    }

    public function test_entry_can_be_edited_to_add_posts(): void
    {
        $account = Account::factory()->platform(Platform::X)->manual()->create();
        $operator = User::factory()->operator()->create();
        $operator->brands()->attach($account->brand);
        $entry = ManualEntry::create(['account_id' => $account->id, 'format' => 'post', 'period_start' => '2026-10-01', 'period_end' => '2026-10-31', 'views' => 700, 'created_by' => $operator->id]);

        $this->actingAs($operator)->get("/manual/entries/{$entry->id}/edit")
            ->assertOk()->assertSee('value="700"', false)->assertSee('value="2026-10"', false);

        $this->actingAs($operator)->put("/manual/entries/{$entry->id}", [
            'format' => 'post', 'period_start' => '2026-10-01', 'period_end' => '2026-10-31', 'views' => 750, 'posts' => 12,
        ])->assertRedirect("/manual/{$account->id}")->assertSessionHas('status');

        $entry->refresh();
        $this->assertSame(750, $entry->views);
        $this->assertSame(12, $entry->posts);
        $this->assertSame($operator->id, (int) $entry->created_by);
        $this->assertSame(1, ManualEntry::count());

        $this->actingAs($operator)->put("/manual/entries/{$entry->id}", [
            'format' => 'post', 'period_start' => '2026-10-01', 'period_end' => '2026-10-31', 'views' => 750, 'posts' => -3,
        ])->assertSessionHasErrors('posts');
    }

    public function test_others_cannot_edit_entry(): void
    {
        $account = Account::factory()->platform(Platform::X)->manual()->create();
        $entry = ManualEntry::create(['account_id' => $account->id, 'format' => 'post', 'period_start' => '2026-10-01', 'period_end' => '2026-10-31', 'views' => 700]);
        $operator = User::factory()->operator()->create();
        $operator->brands()->attach(Brand::factory()->create());
        $payload = ['format' => 'post', 'period_start' => '2026-10-01', 'period_end' => '2026-10-31', 'views' => 1, 'posts' => 1];

        $this->actingAs($operator)->get("/manual/entries/{$entry->id}/edit")->assertForbidden();
        $this->actingAs($operator)->put("/manual/entries/{$entry->id}", $payload)->assertForbidden();
        $this->actingAs(User::factory()->create())->put("/manual/entries/{$entry->id}", $payload)->assertForbidden();
        $this->assertSame(700, $entry->fresh()->views);
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
