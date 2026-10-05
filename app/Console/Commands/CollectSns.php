<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Services\CollectionRunner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

#[Signature('sns:collect {--account= : このアカウントIDだけを今すぐ収集する} {--job=all : daily / stories / all(--account 指定時)} {--limit= : 1回で処理する件数}')]
#[Description('収集時期が来たSNSアカウントの閲覧数を取得する(cron から5分おきに実行)')]
class CollectSns extends Command
{
    public function handle(CollectionRunner $runner): int
    {
        // 前の回がまだ動いていれば何もしない
        $lock = Cache::lock('sns:collect', 30 * 60);
        if (! $lock->get()) {
            $this->info('前回の収集が実行中のためスキップしました。');

            return self::SUCCESS;
        }

        try {
            foreach ($this->tasks($runner) as [$account, $job]) {
                $log = $runner->run($account, $job);
                $this->line(sprintf('[%s] #%d %s %s: %s', $log->status, $account->id, $account->name, $job, $log->message));
            }
        } finally {
            $lock->release();
        }

        return self::SUCCESS;
    }

    /** @return list<array{0: Account, 1: string}> */
    private function tasks(CollectionRunner $runner): array
    {
        if ($id = $this->option('account')) {
            $account = Account::findOrFail($id);
            $job = $this->option('job');
            $jobs = $job === 'all'
                ? array_filter([CollectionRunner::JOB_DAILY, $account->platform->hasStories() ? CollectionRunner::JOB_STORIES : null])
                : [$job];

            return array_map(fn ($j) => [$account, $j], array_values($jobs));
        }

        return $runner->dueTasks((int) ($this->option('limit') ?? config('sns.accounts_per_run')));
    }
}
