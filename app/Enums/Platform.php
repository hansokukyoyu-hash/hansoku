<?php

namespace App\Enums;

enum Platform: string
{
    case Instagram = 'instagram';
    case Facebook = 'facebook';
    case YouTube = 'youtube';
    case Threads = 'threads';
    case TikTok = 'tiktok';
    case X = 'x';

    public function label(): string
    {
        return match ($this) {
            self::Instagram => 'Instagram',
            self::Facebook => 'Facebook',
            self::YouTube => 'YouTube',
            self::Threads => 'Threads',
            self::TikTok => 'TikTok',
            self::X => 'X',
        };
    }

    /**
     * 集計するフォーマット(キー => 表示名)。
     *
     * @return array<string, string>
     */
    public function formats(): array
    {
        return match ($this) {
            self::Instagram => ['feed' => 'フィード', 'reel' => 'リール', 'story' => 'ストーリーズ'],
            self::Facebook => ['post' => '投稿', 'story' => 'ストーリーズ'],
            self::YouTube => ['short' => 'ショート'],
            self::Threads => ['post' => '投稿'],
            self::TikTok => ['video' => '動画'],
            self::X => ['post' => 'ポスト'],
        };
    }

    /**
     * 「期間中に発生した閲覧数」(モード ア)の内訳。
     * Instagram・Facebook・YouTube はアカウント全体の日別値を取るので、投稿の種類とは別に
     * 広告や通常動画も含む(Business Suite / YouTube Studio と同じ範囲)。
     *
     * @return array<string, string>
     */
    public function viewFormats(): array
    {
        return match ($this) {
            self::Instagram => ['feed' => 'フィード', 'reel' => 'リール', 'story' => 'ストーリーズ', 'ad' => '広告'],
            self::Facebook => ['organic' => 'オーガニック', 'ad' => '広告'],
            self::YouTube => ['short' => 'ショート', 'video' => '通常動画', 'live' => 'ライブ'],
            default => $this->formats(),
        };
    }

    /** 広告の閲覧数を内訳に持つか */
    public function hasAdViews(): bool
    {
        return array_key_exists('ad', $this->viewFormats());
    }

    public function formatLabel(string $format): string
    {
        return $this->viewFormats()[$format] ?? $this->formats()[$format] ?? $format;
    }

    /** API連携に対応しているか(TikTokは審査前、Xは有料APIを使わないため手入力のみ) */
    public function supportsApi(): bool
    {
        return in_array($this, [self::Instagram, self::Facebook, self::YouTube, self::Threads], true);
    }

    /** ストーリーズのように数時間おきに収集が必要か */
    public function hasStories(): bool
    {
        return in_array($this, [self::Instagram, self::Facebook], true);
    }

    /** 指標の表示名(Xだけ「表示回数」) */
    public function metricLabel(): string
    {
        return match ($this) {
            self::YouTube, self::TikTok => '再生回数',
            self::X => '表示回数',
            default => '閲覧数',
        };
    }
}
