<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** ログイン中に無効化されたメンバーを締め出す */
class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->is_active) {
            Auth::logout();
            $request->session()->invalidate();

            return redirect()->route('login')->with('error', 'このアカウントは無効になっています。管理者に連絡してください。');
        }

        return $next($request);
    }
}
