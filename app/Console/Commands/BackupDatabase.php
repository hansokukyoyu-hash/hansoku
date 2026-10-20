<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

#[Signature('sns:backup {--keep=14 : 残す日数}')]
#[Description('MySQL のデータベースを gzip で保存し、古いものを消す(cron から1日1回実行)')]
class BackupDatabase extends Command
{
    public function handle(): int
    {
        $db = config('database.connections.'.config('database.default'));
        if (($db['driver'] ?? null) !== 'mysql') {
            $this->error('MySQL 以外のデータベースには対応していません。');

            return self::FAILURE;
        }

        $dir = config('sns.backup_dir');
        File::ensureDirectoryExists($dir, 0700);

        // パスワードをコマンドラインに出さないよう、本人だけが読める一時ファイルで mysqldump に渡す
        $credentials = tempnam(sys_get_temp_dir(), 'mysql');
        chmod($credentials, 0600);
        file_put_contents($credentials, sprintf(
            "[client]\nhost=\"%s\"\nport=%d\nuser=\"%s\"\npassword=\"%s\"\n",
            addcslashes((string) $db['host'], '"\\'),
            (int) $db['port'],
            addcslashes((string) $db['username'], '"\\'),
            addcslashes((string) $db['password'], '"\\'),
        ));

        $sql = $dir.'/db_'.now()->format('Ymd_His').'.sql';
        try {
            $result = Process::timeout(10 * 60)->run([
                'mysqldump',
                '--defaults-extra-file='.$credentials,
                '--single-transaction',
                '--no-tablespaces',
                '--default-character-set=utf8mb4',
                '--result-file='.$sql,
                $db['database'],
            ]);
        } finally {
            @unlink($credentials);
        }

        if ($result->failed()) {
            @unlink($sql);
            $this->error('バックアップに失敗しました: '.trim($result->errorOutput()));

            return self::FAILURE;
        }

        $gz = $this->gzip($sql);
        $this->info('バックアップしました: '.$gz.'('.number_format(filesize($gz) / 1024).' KB)');

        $keepDays = (int) $this->option('keep');
        foreach (File::glob($dir.'/db_*.sql.gz') as $old) {
            if (filemtime($old) < now()->subDays($keepDays)->getTimestamp()) {
                File::delete($old);
                $this->line('古いバックアップを削除しました: '.basename($old));
            }
        }

        return self::SUCCESS;
    }

    private function gzip(string $path): string
    {
        $in = fopen($path, 'rb');
        $out = gzopen($path.'.gz', 'wb6');
        while (! feof($in)) {
            gzwrite($out, fread($in, 1024 * 1024));
        }
        fclose($in);
        gzclose($out);
        chmod($path.'.gz', 0600);
        unlink($path);

        return $path.'.gz';
    }
}
