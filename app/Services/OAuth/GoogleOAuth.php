<?php

namespace App\Services\OAuth;

use App\Exceptions\SnsApiException;
use App\Models\Account;
use Illuminate\Support\Facades\Http;

/** YouTube連携(Googleログインと同じ OAuth クライアントを使う) */
class GoogleOAuth
{
    public const SCOPES = [
        'https://www.googleapis.com/auth/yt-analytics.readonly',
        'https://www.googleapis.com/auth/youtube.readonly',
    ];

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    public function authorizeUrl(string $redirectUri, string $state): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', self::SCOPES),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);
    }

    /** @return array{channel_id: string, channel_title: ?string, access_token: string, refresh_token: ?string, expires_at: string} */
    public function exchange(string $code, string $redirectUri): array
    {
        $response = Http::asForm()->timeout(30)->post(self::TOKEN_URL, [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
            'code' => $code,
        ]);
        if ($response->failed()) {
            throw SnsApiException::fromResponse('Google', $response);
        }

        $token = $response->json('access_token');
        $channel = Http::withToken($token)->timeout(30)->get('https://www.googleapis.com/youtube/v3/channels', [
            'part' => 'snippet',
            'mine' => 'true',
        ]);
        if ($channel->failed()) {
            throw SnsApiException::fromResponse('YouTube', $channel);
        }
        $item = $channel->json('items.0');
        if ($item === null) {
            throw new SnsApiException('このGoogleアカウントにはYouTubeチャンネルがありません。');
        }

        return [
            'channel_id' => $item['id'],
            'channel_title' => $item['snippet']['title'] ?? null,
            'access_token' => $token,
            'refresh_token' => $response->json('refresh_token'),
            'expires_at' => now()->addSeconds((int) $response->json('expires_in', 3600))->toIso8601String(),
        ];
    }

    /** アクセストークンは1時間で切れるので、期限が近ければ更新トークンで取り直す */
    public function freshAccessToken(Account $account): string
    {
        $expiresAt = $account->credential('expires_at');
        if ($expiresAt !== null && now()->addMinutes(5)->lt($expiresAt)) {
            return (string) $account->credential('access_token');
        }

        $refreshToken = $account->credential('refresh_token');
        if (empty($refreshToken)) {
            throw new SnsApiException('更新トークンがありません。YouTubeを再連携してください。', needsReconnect: true);
        }

        $response = Http::asForm()->timeout(30)->post(self::TOKEN_URL, [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);
        if ($response->failed()) {
            throw SnsApiException::fromResponse('Google', $response);
        }

        $account->mergeCredentials([
            'access_token' => $response->json('access_token'),
            'expires_at' => now()->addSeconds((int) $response->json('expires_in', 3600))->toIso8601String(),
        ]);
        $account->save();

        return (string) $response->json('access_token');
    }
}
