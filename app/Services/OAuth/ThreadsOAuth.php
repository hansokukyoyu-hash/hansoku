<?php

namespace App\Services\OAuth;

use App\Exceptions\SnsApiException;
use App\Models\Account;
use Illuminate\Support\Facades\Http;

/** Threads連携。長期トークンは60日で切れるので、残り7日を切ったら更新する */
class ThreadsOAuth
{
    public const SCOPES = ['threads_basic', 'threads_manage_insights'];

    public function authorizeUrl(string $redirectUri, string $state): string
    {
        return 'https://threads.net/oauth/authorize?'.http_build_query([
            'client_id' => config('services.threads.app_id'),
            'redirect_uri' => $redirectUri,
            'scope' => implode(',', self::SCOPES),
            'response_type' => 'code',
            'state' => $state,
        ]);
    }

    /** @return array{user_id: string, username: ?string, access_token: string, expires_at: string} */
    public function exchange(string $code, string $redirectUri): array
    {
        $short = Http::asForm()->timeout(30)->post('https://graph.threads.net/oauth/access_token', [
            'client_id' => config('services.threads.app_id'),
            'client_secret' => config('services.threads.app_secret'),
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ]);
        if ($short->failed()) {
            throw SnsApiException::fromResponse('Threads', $short);
        }

        $long = Http::timeout(30)->get('https://graph.threads.net/access_token', [
            'grant_type' => 'th_exchange_token',
            'client_secret' => config('services.threads.app_secret'),
            'access_token' => $short->json('access_token'),
        ]);
        if ($long->failed()) {
            throw SnsApiException::fromResponse('Threads', $long);
        }

        $token = $long->json('access_token');
        $me = Http::timeout(30)->get('https://graph.threads.net/v1.0/me', ['fields' => 'id,username', 'access_token' => $token]);

        return [
            'user_id' => (string) ($me->json('id') ?? $short->json('user_id')),
            'username' => $me->json('username'),
            'access_token' => $token,
            'expires_at' => now()->addSeconds((int) $long->json('expires_in', 60 * 24 * 3600))->toIso8601String(),
        ];
    }

    public function refreshIfExpiring(Account $account): void
    {
        $expiresAt = $account->credential('expires_at');
        if ($expiresAt !== null && now()->addDays(7)->lt($expiresAt)) {
            return;
        }

        $response = Http::timeout(30)->get('https://graph.threads.net/refresh_access_token', [
            'grant_type' => 'th_refresh_token',
            'access_token' => $account->credential('access_token'),
        ]);
        if ($response->failed()) {
            throw SnsApiException::fromResponse('Threads', $response);
        }

        $account->mergeCredentials([
            'access_token' => $response->json('access_token'),
            'expires_at' => now()->addSeconds((int) $response->json('expires_in', 60 * 24 * 3600))->toIso8601String(),
        ]);
        $account->save();
    }
}
