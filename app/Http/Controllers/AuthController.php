<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

/** Googleアカウントでのログイン。管理者が登録したアカウントだけが入れる */
class AuthController extends Controller
{
    public function login(): View|RedirectResponse
    {
        return Auth::check() ? redirect()->route('dashboard') : view('auth.login');
    }

    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')
            ->redirectUrl(route('auth.google.callback'))
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $google = Socialite::driver('google')->redirectUrl(route('auth.google.callback'))->user();
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('login')->with('error', 'Googleでのログインに失敗しました。もう一度お試しください。');
        }

        $email = Str::lower((string) $google->getEmail());
        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user === null && in_array($email, array_map('strtolower', config('sns.admin_emails')), true)) {
            $user = User::create(['email' => $email, 'name' => $google->getName() ?: $email, 'role' => Role::Admin, 'is_active' => true]);
        }

        if ($user === null || ! $user->is_active) {
            return redirect()->route('login')->with('error', "{$email} は登録されていません。管理者にメンバー登録を依頼してください。");
        }

        $user->forceFill([
            'google_id' => $google->getId(),
            'name' => $user->name ?: ($google->getName() ?: $email),
            'avatar' => $google->getAvatar(),
            'last_login_at' => now(),
        ])->save();

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
