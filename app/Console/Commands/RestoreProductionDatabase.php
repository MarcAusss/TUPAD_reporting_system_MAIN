<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use Throwable;

class RestoreProductionDatabase extends Command
{
    protected $signature = 'tupad:restore-database
        {file : SQL backup file to restore}
        {--confirm= : Must be exactly RESTORE to authorize the destructive database restore}';

    protected $description = 'Restore a MySQL backup only after explicit RESTORE confirmation. Existing database contents may be overwritten.';

    public function handle(): int
    {
        if ((string) $this->option('confirm') !== 'RESTORE') {
            $this->error('Restore cancelled. Re-run with --confirm=RESTORE only after maintenance mode and a verified pre-restore backup.');

            return self::FAILURE;
        }

        if (config('database.default') !== 'mysql') {
            $this->error('Database restore through this command requires DB_CONNECTION=mysql.');

            return self::FAILURE;
        }

        $file = $this->absoluteFilePath((string) $this->argument('file'));
        if (! is_file($file) || ! is_readable($file)) {
            $this->error("Backup file is not readable: {$file}");

            return self::FAILURE;
        }

        $connection = (array) config('database.connections.mysql');
        $database = trim((string) ($connection['database'] ?? ''));
        $username = trim((string) ($connection['username'] ?? ''));

        if ($database === '' || $username === '') {
            $this->error('MySQL database name and username must be configured before restore.');

            return self::FAILURE;
        }

        $arguments = [
            'mysql',
            '--default-character-set=utf8mb4',
            '--host='.(string) ($connection['host'] ?? '127.0.0.1'),
            '--port='.(string) ($connection['port'] ?? 3306),
            '--user='.$username,
            $database,
        ];

        $environment = [];
        $password = (string) ($connection['password'] ?? '');
        if ($password !== '') {
            $environment['MYSQL_PWD'] = $password;
        }

        $handle = fopen($file, 'rb');
        if ($handle === false) {
            $this->error('Unable to open backup file.');

            return self::FAILURE;
        }

        try {
            $process = new Process($arguments, base_path(), $environment, $handle, 3600);
            $process->run();
            fclose($handle);

            if (! $process->isSuccessful()) {
                $this->error('mysql restore failed: '.trim($process->getErrorOutput()));

                return self::FAILURE;
            }
        } catch (Throwable $exception) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            $this->error('Database restore failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Database restore completed.');
        $this->warn('Run php artisan optimize:clear, tupad:phase5-audit --production, and the post-restore smoke checklist before reopening access.');

        return self::SUCCESS;
    }

    private function absoluteFilePath(string $path): string
    {
        if (preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1 || str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            return $path;
        }

        return base_path($path);
    }
}
