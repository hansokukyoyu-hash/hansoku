<?php

namespace App\Console\Commands;

use App\Enums\Platform;
use App\Models\Account;
use App\Services\CollectionRunner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sns:fill-profile-urls')]
#[Description('プロフィールURLが空の API 連携アカウント(Instagram・Facebook・YouTube・Threads)の URL だけを取得する')]
class FillProfileUrls extends Command
{
    public function handle(CollectionRunner $runner): int
    {
        $accounts = Account::query()->collectable()->whereNull('profile_url')->with('brand')->get()
            ->filter(fn (Account $a) => $a->isConnected()
                && in_array($a->platform, [Platform::Instagram, Platform::Facebook, Platform::YouTube, Platform::Threads], true));

        if ($accounts->isEmpty()) {
            $this->info('対象のアカウントはありません。');

            return self::SUCCESS;
        }

        $filled = 0;
        foreach ($accounts as $account) {
            $url = $runner->collectorFor($account->platform)->fillProfileUrl($account);
            $filled += $url ? 1 : 0;
            $this->line(sprintf('%s #%d %s / %s / %s', $url ? '[OK]' : '[取得できず]', $account->id, $account->brand->name, $account->platform->label(), $url ?? $account->name));
        }

        $this->info("{$accounts->count()} 件中 {$filled} 件の URL を入れました。");

        return self::SUCCESS;
    }
}
