<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Operator = 'operator';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => '管理者',
            self::Operator => '運用担当',
            self::Viewer => '閲覧者',
        };
    }
}
