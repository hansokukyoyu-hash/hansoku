<?php

namespace App\Services\OAuth;

use App\Exceptions\SnsApiException;
use App\Models\Account;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * TikTok連携(Login Kit)。アクセストークンは24時間、更新トークンは365日で切れる。
 * 収集は1日1回なので、毎回更新トークンでアクセストークンを取り直す(更新トークンも延長される)。
 */
class TikTokOAuth
{
    public const SCOPES = ['user.info.basic', 'video.list'];

    private const TOKEN_URL = 'https://open.tiktokapis.com/v2/oauth/token/';

    public function authorizeUrl(string $redirectUri, string $state): string
    {
        return 'https://www.tiktok.com/v2/auth/authorize/?'.http_build_query([
            'client_key' => config('services.tiktok.client_key'),
            'redirect_uri' => $redirectUri,
            'scope' => implode(',', self::SCOPES),
            'response_type' => 'code',
            'state' => $state,
        ]);
    }

    /** @return array{open_id: string, display_name: ?string, credentials: array<string, string>} */
    public function exchange(string $code, string $redirectUri): array
    {
        $token = $this->tokenRequest([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ]);

        $me = Http::withToken($token['access_token'])->timeout(30)
            ->get('https://open.tiktokapis.com/v2/user/info/', ['fields' => 'open_id,display_name']);
        self::check($me);

        return [
            'open_id' => (string) ($me->json('data.user.open_id') ?? $token['open_id']),
            'display_name' => $me->json('data.user.display_name'),
            'credentials' => self::credentials($token),
        ];
    }

    public function freshAccessToken(Account $account): string
    {
        $expiresAt = $account->credential('expires_at');
        if ($expiresAt !== null && now()->addMinutes(10)->lt($expiresAt)) {
            return (string) $account->credential('access_token');
        }

        $token = $this->tokenRequest([
            'grant_type' => 'refresh_token',
            'refresh_token' => $account->credential('refresh_token'),
        ]);
        $account->mergeCredentials(self::credentials($token));
        $account->save();

        return $token['access_token'];
    }

    /** TikTok はエラーでも HTTP 200 を返すことがあるので、本文の error も確かめる */
    public static function check(Response $response): void
    {
        $code = $response->json('error.code');
        if ($response->successful() && in_array($code, [null, 'ok'], true)) {
            return;
        }

        $message = $response->json('error.message') ?: $response->json('error_description') ?: $response->body();
        $reconnect = $response->status() === 401
            || in_array($code, ['access_token_invalid', 'scope_not_authorized'], true)
            || $response->json('error') === 'invalid_grant';

        throw new SnsApiException(sprintf('TikTok API エラー (HTTP %d %s): %s', $response->status(), is_string($code) ? $code : '', mb_strimwidth((string) $message, 0, 500, '…')), $reconnect);
    }

    private function tokenRequest(array $params): array
    {
        $response = Http::asForm()->timeout(30)->post(self::TOKEN_URL, $params + [
            'client_key' => config('services.tiktok.client_key'),
            'client_secret' => config('services.tiktok.client_secret'),
        ]);
        self::check($response);
        if (! $response->json('access_token')) {
            throw new SnsApiException('TikTok API エラー: '.($response->json('error_description') ?? $response->json('error') ?? 'トークンを取得できませんでした。'), true);
        }

        return $response->json();
    }

    private static function credentials(array $token): array
    {
        return [
            'access_token' => $token['access_token'],
            'refresh_token' => $token['refresh_token'],
            'expires_at' => now()->addSeconds((int) ($token['expires_in'] ?? 86400))->toIso8601String(),
        ];
    }
}
