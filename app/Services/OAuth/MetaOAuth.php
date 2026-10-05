<?php

namespace App\Services\OAuth;

use App\Exceptions\SnsApiException;
use App\Services\GraphClient;
use Illuminate\Support\Facades\Http;

/** Instagram・Facebookページ連携(Facebookログイン) */
class MetaOAuth
{
    public const SCOPES = [
        'pages_show_list',
        'pages_read_engagement',
        'read_insights',
        'instagram_basic',
        'instagram_manage_insights',
        'business_management',
    ];

    public function authorizeUrl(string $redirectUri, string $state): string
    {
        return 'https://www.facebook.com/'.config('services.meta.graph_version').'/dialog/oauth?'.http_build_query([
            'client_id' => config('services.meta.app_id'),
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'scope' => implode(',', self::SCOPES),
            'response_type' => 'code',
        ]);
    }

    /**
     * 認可コードを長期ユーザートークンに換え、そのユーザーが管理するページと
     * 紐づくInstagramアカウントの一覧を返す。ページトークンは長期トークン由来なので失効しない。
     *
     * @return list<array{page_id: string, page_name: string, page_token: string, instagram_id: ?string, instagram_username: ?string}>
     */
    public function exchange(string $code, string $redirectUri): array
    {
        $base = 'https://graph.facebook.com/'.config('services.meta.graph_version');

        $short = Http::timeout(30)->get($base.'/oauth/access_token', [
            'client_id' => config('services.meta.app_id'),
            'client_secret' => config('services.meta.app_secret'),
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ]);
        if ($short->failed()) {
            throw SnsApiException::fromResponse('Meta', $short);
        }

        $long = Http::timeout(30)->get($base.'/oauth/access_token', [
            'grant_type' => 'fb_exchange_token',
            'client_id' => config('services.meta.app_id'),
            'client_secret' => config('services.meta.app_secret'),
            'fb_exchange_token' => $short->json('access_token'),
        ]);
        if ($long->failed()) {
            throw SnsApiException::fromResponse('Meta', $long);
        }

        $pages = GraphClient::meta()->paginate(
            'me/accounts',
            ['fields' => 'id,name,access_token,instagram_business_account{id,username}', 'limit' => 100],
            $long->json('access_token'),
        );

        return array_map(fn (array $page) => [
            'page_id' => $page['id'],
            'page_name' => $page['name'] ?? $page['id'],
            'page_token' => $page['access_token'],
            'instagram_id' => $page['instagram_business_account']['id'] ?? null,
            'instagram_username' => $page['instagram_business_account']['username'] ?? null,
        ], $pages);
    }
}
