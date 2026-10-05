<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGoogle(string $email): void
    {
        $googleUser = (new GoogleUser)->map(['id' => 'g-1', 'name' => 'テスト', 'email' => $email, 'avatar' => null]);
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('redirectUrl')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($googleUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('Googleでログイン');
    }

    public function test_registered_member_can_log_in(): void
    {
        $user = User::factory()->create(['email' => 'member@example.com']);
        $this->fakeGoogle('Member@Example.com');

        $this->get('/auth/google/callback')->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame('g-1', $user->fresh()->google_id);
    }

    public function test_unregistered_account_is_rejected(): void
    {
        $this->fakeGoogle('stranger@example.com');

        $this->get('/auth/google/callback')->assertRedirect('/login')->assertSessionHas('error');
        $this->assertGuest();
    }

    public function test_inactive_member_is_rejected(): void
    {
        User::factory()->create(['email' => 'off@example.com', 'is_active' => false]);
        $this->fakeGoogle('off@example.com');

        $this->get('/auth/google/callback')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_admin_email_is_registered_on_first_login(): void
    {
        config(['sns.admin_emails' => ['boss@example.com']]);
        $this->fakeGoogle('boss@example.com');

        $this->get('/auth/google/callback')->assertRedirect(route('dashboard'));
        $this->assertSame(Role::Admin, User::where('email', 'boss@example.com')->first()->role);
    }

    public function test_deactivated_member_is_logged_out(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->actingAs($user)->get('/')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_pages_are_noindex(): void
    {
        $this->get('/login')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->assertSee('<meta name="robots" content="noindex, nofollow, noarchive">', false);
    }

    public function test_non_admin_cannot_open_admin_pages(): void
    {
        $this->actingAs(User::factory()->operator()->create())->get('/admin/accounts')->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get('/admin/accounts')->assertOk();
    }
}
