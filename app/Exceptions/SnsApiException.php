<?php

namespace App\Exceptions;

use Illuminate\Http\Client\Response;
use RuntimeException;

class SnsApiException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $needsReconnect = false)
    {
        parent::__construct($message);
    }

    public static function fromResponse(string $service, Response $response): self
    {
        $error = $response->json('error');
        $message = is_array($error)
            ? ($error['message'] ?? json_encode($error, JSON_UNESCAPED_UNICODE))
            : ($response->json('error_description') ?? (is_string($error) ? $error : $response->body()));

        // Meta / Threads: code 190 = トークン失効、Google: invalid_grant = 更新トークン失効
        $needsReconnect = $response->status() === 401
            || (is_array($error) && (int) ($error['code'] ?? 0) === 190)
            || $error === 'invalid_grant';

        return new self(sprintf('%s API エラー (HTTP %d): %s', $service, $response->status(), mb_strimwidth((string) $message, 0, 500, '…')), $needsReconnect);
    }
}
