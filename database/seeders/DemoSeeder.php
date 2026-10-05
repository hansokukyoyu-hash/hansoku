<?php

namespace Database\Seeders;

use App\Enums\Platform;
use App\Enums\Role;
use App\Models\Account;
use App\Models\Brand;
use App\Models\DailyMetric;
use App\Models\ManualEntry;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;

/** 画面確認用のサンプルデータ(本番では使わない) */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        mt_srand(42);
        User::firstOrCreate(['email' => 'admin@example.com'], ['name' => 'デモ管理者', 'role' => Role::Admin]);

        foreach (['ブランドA', 'ブランドB', 'ブランドC'] as $i => $name) {
            $brand = Brand::create(['name' => $name, 'sort_order' => $i + 1]);

            foreach (Platform::cases() as $platform) {
                $api = $platform->supportsApi();
                $account = Account::create([
                    'brand_id' => $brand->id,
                    'platform' => $platform,
                    'name' => $name.' 公式',
                    'input_method' => $api ? Account::METHOD_API : Account::METHOD_MANUAL,
                    'external_id' => $api ? (string) mt_rand(1000, 9999) : null,
                    'credentials' => $api ? ['access_token' => 'demo'] : null,
                    'status' => 'active',
                    'daily_synced_at' => $api ? now()->setTime(3, 5) : null,
                    'stories_synced_at' => $api && $platform->hasStories() ? now()->subHour() : null,
                ]);

                if (! $api) {
                    foreach ([2, 1] as $m) {
                        ManualEntry::create([
                            'account_id' => $account->id,
                            'format' => array_key_first($platform->formats()),
                            'period_start' => now()->subMonthsNoOverflow($m)->startOfMonth()->toDateString(),
                            'period_end' => now()->subMonthsNoOverflow($m)->endOfMonth()->toDateString(),
                            'views' => mt_rand(5000, 60000),
                        ]);
                    }

                    continue;
                }

                foreach ($platform->formats() as $format => $label) {
                    $base = mt_rand(200, 3000);
                    for ($d = 70; $d >= 1; $d--) {
                        DailyMetric::create([
                            'account_id' => $account->id,
                            'date' => now()->subDays($d)->toDateString(),
                            'format' => $format,
                            'views' => (int) ($base * (0.6 + mt_rand(0, 80) / 100) * (1 + (70 - $d) / 140)),
                        ]);
                    }
                    for ($p = 0; $p < 6; $p++) {
                        Post::create([
                            'account_id' => $account->id,
                            'external_id' => "{$account->id}-{$format}-{$p}",
                            'format' => $format,
                            'published_at' => now()->subDays(mt_rand(1, 60))->setTime(mt_rand(8, 22), 0),
                            'caption' => "{$label}のサンプル投稿 ".($p + 1),
                            'permalink' => 'https://example.com/',
                            'views' => mt_rand(500, 40000),
                            'views_fetched_at' => now()->setTime(3, 5),
                        ]);
                    }
                }
            }
        }
    }
}
