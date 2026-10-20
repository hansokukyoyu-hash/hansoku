<?php

namespace Tests\Feature;

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class BackupTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/sns-backup-test-'.uniqid();
        config([
            'sns.backup_dir' => $this->dir,
            'database.default' => 'mysql',
            'database.connections.mysql.database' => 'sns',
            'database.connections.mysql.password' => 'secret"pw',
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    public function test_dumps_gzips_and_removes_old_backups(): void
    {
        File::ensureDirectoryExists($this->dir);
        touch($old = $this->dir.'/db_20260101_043000.sql.gz', now()->subDays(20)->getTimestamp());

        $command = null;
        Process::fake(function (PendingProcess $process) use (&$command) {
            $command = $process->command;
            $result = collect($command)->first(fn ($arg) => str_starts_with($arg, '--result-file='));
            file_put_contents(substr($result, strlen('--result-file=')), 'CREATE TABLE x;');

            return Process::result();
        });

        $this->artisan('sns:backup')->expectsOutputToContain('バックアップしました')->assertSuccessful();

        // パスワードはコマンドラインに出さない
        $this->assertStringNotContainsString('secret', implode(' ', $command));
        $files = File::glob($this->dir.'/db_*.sql.gz');
        $this->assertCount(1, $files);
        $this->assertFileDoesNotExist($old);
        $this->assertSame('CREATE TABLE x;', gzdecode(file_get_contents($files[0])));
    }

    public function test_reports_failure(): void
    {
        Process::fake(fn () => Process::result(errorOutput: 'Access denied', exitCode: 2));

        $this->artisan('sns:backup')->expectsOutputToContain('Access denied')->assertFailed();
        $this->assertSame([], File::glob($this->dir.'/db_*'));
    }
}
