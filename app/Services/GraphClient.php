<?php

namespace App\Services;

use App\Exceptions\SnsApiException;
use Illuminate\Support\Facades\Http;

/** Meta Graph API / Threads API 共通の GET とページ送り */
class GraphClient
{
    public function __construct(private readonly string $baseUrl, private readonly string $service) {}

    public static function meta(): self
    {
        return new self('https://graph.facebook.com/'.config('services.meta.graph_version'), 'Meta');
    }

    public static function threads(): self
    {
        return new self('https://graph.threads.net/v1.0', 'Threads');
    }

    public function get(string $path, array $params, string $token): array
    {
        $response = Http::timeout(30)->retry(2, 1000, throw: false)
            ->get($this->baseUrl.'/'.ltrim($path, '/'), $params + ['access_token' => $token]);

        if ($response->failed()) {
            throw SnsApiException::fromResponse($this->service, $response);
        }

        return $response->json() ?? [];
    }

    /**
     * data 配列を next が無くなるまで(または $until が false を返すまで)たどる。
     *
     * @param  callable(array): bool|null  $continue  1件ごとに呼ばれ、false で打ち切り
     * @return list<array>
     */
    public function paginate(string $path, array $params, string $token, ?callable $continue = null, int $maxPages = 20): array
    {
        $items = [];
        $page = $this->get($path, $params, $token);

        for ($i = 0; $i < $maxPages; $i++) {
            foreach ($page['data'] ?? [] as $item) {
                if ($continue !== null && ! $continue($item)) {
                    return $items;
                }
                $items[] = $item;
            }

            $next = $page['paging']['next'] ?? null;
            if ($next === null) {
                break;
            }

            $response = Http::timeout(30)->retry(2, 1000, throw: false)->get($next);
            if ($response->failed()) {
                throw SnsApiException::fromResponse($this->service, $response);
            }
            $page = $response->json() ?? [];
        }

        return $items;
    }

    /** insights レスポンスから指定指標の値を取り出す(values 形式と total_value 形式の両方に対応) */
    public static function insightValue(array $insights, string $metric): ?int
    {
        foreach ($insights['data'] ?? [] as $row) {
            if (($row['name'] ?? null) !== $metric) {
                continue;
            }
            if (isset($row['total_value']['value'])) {
                return (int) $row['total_value']['value'];
            }
            $values = $row['values'] ?? [];
            if ($values !== []) {
                return (int) (end($values)['value'] ?? 0);
            }
        }

        return null;
    }
}
