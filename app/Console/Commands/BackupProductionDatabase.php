<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

class BackupProductionDatabase extends Command
{
    protected $signature = 'tupad:backup-database
        {--path= : Optional output SQL file path}
        {--retention-days= : Override configured retention period}';

    protected $description = 'Create a consistent MySQL database backup using mysqldump and remove expired TUPAD backup files.';

    public function handle(): int
    {
        if (config('database.default') !== 'mysql') {
            $this->error('Database backups through this command require DB_CONNECTION=mysql.');

            return self::FAILURE;
        }

        $connection = (array) config('database.connections.mysql');
        $database = trim((string) ($connection['database'] ?? ''));
        $username = trim((string) ($connection['username'] ?? ''));

        if ($database === '' || $username === '') {
            $this->error('MySQL database name and username must be configured before backup.');

            return self::FAILURE;
        }

        $path = $this->resolveOutputPath();
        File::ensureDirectoryExists(dirname($path));

        $arguments = [
            'mysqldump',
            '--single-transaction',
            '--quick',
            '--routines',
            '--triggers',
            '--events',
            '--no-tablespaces',
            '--hex-blob',
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

        $handle = fopen($path, 'wb');
        if ($handle === false) {
            $this->error("Unable to create backup file: {$path}");

            return self::FAILURE;
        }

        try {
            $process = new Process($arguments, base_path(), $environment, null, 1800);
            $process->run(static function (string $type, string $buffer) use ($handle): void {
                if ($type === Process::OUT) {
                    fwrite($handle, $buffer);
                }
            });
            fclose($handle);

            if (! $process->isSuccessful()) {
                File::delete($path);
                $this->error('mysqldump failed: '.trim($process->getErrorOutput()));
                $this->line('Ensure MySQL client tools are installed and mysqldump is available in PATH.');

                return self::FAILURE;
            }
        } catch (Throwable $exception) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            File::delete($path);
            $this->error('Database backup failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        if (! is_file($path) || filesize($path) === 0) {
            File::delete($path);
            $this->error('mysqldump completed without producing a usable backup file.');

            return self::FAILURE;
        }

        $removed = $this->removeExpiredBackups(dirname($path));
        $this->info('Database backup created successfully.');
        $this->line($path);
        $this->line('Size: '.number_format((int) filesize($path)).' bytes');
        if ($removed > 0) {
            $this->line("Expired backups removed: {$removed}");
        }

        return self::SUCCESS;
    }

    private function resolveOutputPath(): string
    {
        $requested = trim((string) $this->option('path'));
        if ($requested !== '') {
            return $this->absolutePath($requested);
        }

        $directory = $this->absolutePath((string) config('tupad_operations.backup.directory'));
        $database = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) config('database.connections.mysql.database'));

        return $directory.DIRECTORY_SEPARATOR.sprintf(
            '%s-%s.sql',
            $database ?: 'tupad',
            now('Asia/Manila')->format('Ymd-His'),
        );
    }

    private function removeExpiredBackups(string $directory): int
    {
        $retention = $this->option('retention-days');
        $days = $retention !== null
            ? max(1, (int) $retention)
            : max(1, (int) config('tupad_operations.backup.retention_days', 14));
        $cutoff = now()->subDays($days)->getTimestamp();
        $removed = 0;

        foreach (File::glob($directory.DIRECTORY_SEPARATOR.'*.sql') as $file) {
            if ((int) File::lastModified($file) < $cutoff && File::delete($file)) {
                $removed++;
            }
        }

        return $removed;
    }

    private function absolutePath(string $path): string
    {
        if (preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1 || Str::startsWith($path, ['/', '\\'])) {
            return $path;
        }

        return base_path($path);
    }
}
