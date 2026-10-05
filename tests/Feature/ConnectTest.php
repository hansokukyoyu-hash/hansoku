<?php

namespace Tests\Feature;

use App\Enums\Platform;
use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConnectTest extends TestCase
{
    use RefreshDatabase;

    public function test_instagram_connect_flow(): void
    {
        config(['services.meta.app_id' => 'app', 'services.meta.app_secret' => 'secret', 'services.meta.graph_version' => 'v23.0']);
        $admin = User::factory()->admin()->create();
        $account = Account::factory()->platform(Platform::Instagram)->create();

        $redirect = $this->actingAs($admin)->get("/admin/accounts/{$account->id}/connect");
        $redirect->assertRedirectContains('https://www.facebook.com/v23.0/dialog/oauth');
        $state = session('connect.state');

        Http::fake([
            'graph.facebook.com/v23.0/oauth/access_token*' => Http::sequence()
                ->push(['access_token' => 'short'])
                ->push(['access_token' => 'long']),
            'graph.facebook.com/v23.0/me/accounts*' => Http::response(['data' => [
                ['id' => 'pageNoIg', 'name' => 'IGなしページ', 'access_token' => 'pt0'],
                ['id' => 'page1', 'name' => 'ブランドページ', 'access_token' => 'pt1', 'instagram_business_account' => ['id' => 'ig1', 'username' => 'brand']],
            ]]),
        ]);

        $this->get("/connect/meta/callback?code=abc&state={$state}")->assertRedirect(route('connect.meta.choose'));
        $this->get('/connect/meta/choose')->assertOk()->assertSee('@brand')->assertDontSee('IGなしページ');
        $this->post('/connect/meta/choose', ['page_id' => 'page1'])->assertRedirect(route('admin.accounts.index'));

        $account->refresh();
        $this->assertSame('ig1', $account->external_id);
        $this->assertSame('pt1', $account->credential('access_token'));
        $this->assertTrue($account->isConnected());
    }

    public function test_state_mismatch_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $account = Account::factory()->platform(Platform::Threads)->create();

        $this->actingAs($admin)->get("/admin/accounts/{$account->id}/connect");
        $this->get('/connect/threads/callback?code=abc&state=wrong')->assertStatus(419);
    }

    public function test_youtube_connect_stores_refresh_token(): void
    {
        config(['services.google.client_id' => 'cid', 'services.google.client_secret' => 'cs']);
        $admin = User::factory()->admin()->create();
        $account = Account::factory()->platform(Platform::YouTube)->create();

        $this->actingAs($admin)->get("/admin/accounts/{$account->id}/connect")->assertRedirectContains('access_type=offline');
        $state = session('connect.state');

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'at', 'refresh_token' => 'rt', 'expires_in' => 3600]),
            'www.googleapis.com/youtube/v3/channels*' => Http::response(['items' => [['id' => 'UC123', 'snippet' => ['title' => 'ブランドch']]]]),
        ]);

        $this->get("/connect/google/callback?code=abc&state={$state}")->assertRedirect(route('admin.accounts.index'))->assertSessionHas('status');
        $account->refresh();
        $this->assertSame('UC123', $account->external_id);
        $this->assertSame('rt', $account->credential('refresh_token'));
    }

    public function test_manual_platform_cannot_connect(): void
    {
        $account = Account::factory()->platform(Platform::X)->manual()->create();

        $this->actingAs(User::factory()->admin()->create())->get("/admin/accounts/{$account->id}/connect")->assertStatus(422);
    }

    public function test_api_method_falls_back_to_manual_for_unsupported_platform(): void
    {
        $account = Account::factory()->create();

        $this->actingAs(User::factory()->admin()->create())->post('/admin/accounts', [
            'brand_id' => $account->brand_id, 'platform' => 'tiktok', 'name' => 'TT', 'input_method' => 'api',
        ])->assertRedirect();

        $this->assertSame(Account::METHOD_MANUAL, Account::where('name', 'TT')->value('input_method'));
    }
}
