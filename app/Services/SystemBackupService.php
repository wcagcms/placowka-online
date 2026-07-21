<?php

namespace App\Services;

use App\Models\BackupRun;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;
use ZipArchive;

class SystemBackupService
{
    public function create(?int $retentionDays = null): BackupRun
    {
        $run = BackupRun::query()->create([
            'status' => 'running',
            'driver' => (string) config('database.default'),
            'started_at' => now(),
        ]);

        $workingDirectory = null;

        try {
            $backupDirectory = storage_path('app/backups');
            File::ensureDirectoryExists($backupDirectory, 0700, true);
            @chmod($backupDirectory, 0700);

            $stamp = now('Europe/Warsaw')->format('Ymd-His');
            $filename = 'placowka-online-backup-'.$stamp.'.zip';
            $archivePath = $backupDirectory.DIRECTORY_SEPARATOR.$filename;
            $workingDirectory = storage_path('app/backup-tmp/'.$run->id.'-'.$stamp);
            File::ensureDirectoryExists($workingDirectory, 0700, true);
            @chmod($workingDirectory, 0700);

            [$databaseFile, $databaseMeta] = $this->dumpDatabase($workingDirectory);
            $databaseChecksum = hash_file('sha256', $databaseFile);

            $manifest = [
                'format_version' => 1,
                'created_at' => now()->toIso8601String(),
                'application' => [
                    'name' => (string) config('app.name'),
                    'environment' => (string) config('app.env'),
                    'url' => (string) config('app.url'),
                    'laravel_version' => app()->version(),
                    'php_version' => PHP_VERSION,
                ],
                'database' => array_merge($databaseMeta, [
                    'filename' => basename($databaseFile),
                    'sha256' => $databaseChecksum,
                    'size_bytes' => filesize($databaseFile) ?: 0,
                ]),
                'security' => [
                    'contains_env_file' => false,
                    'contains_agent_tokens' => false,
                    'storage' => 'private Laravel storage/app/backups',
                ],
            ];

            $manifestPath = $workingDirectory.DIRECTORY_SEPARATOR.'manifest.json';
            File::put(
                $manifestPath,
                json_encode(
                    $manifest,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
                ).PHP_EOL
            );
            @chmod($manifestPath, 0600);

            $this->createArchive($archivePath, [$databaseFile, $manifestPath]);
            @chmod($archivePath, 0600);

            $checksum = hash_file('sha256', $archivePath);
            $size = filesize($archivePath) ?: 0;

            $run->forceFill([
                'status' => 'success',
                'filename' => $filename,
                'storage_path' => 'app/backups/'.$filename,
                'checksum_sha256' => $checksum,
                'size_bytes' => $size,
                'completed_at' => now(),
                'meta' => [
                    'database_sha256' => $databaseChecksum,
                    'database_filename' => basename($databaseFile),
                ],
            ])->save();

            $this->verify($run);
            $this->cleanup($retentionDays ?? (int) config('placowka.backup_retention_days', 14));

            return $run->fresh();
        } catch (Throwable $exception) {
            $run->forceFill([
                'status' => 'failed',
                'completed_at' => now(),
                'error_message' => mb_substr($exception->getMessage(), 0, 4000),
            ])->save();

            throw $exception;
        } finally {
            if ($workingDirectory && is_dir($workingDirectory)) {
                File::deleteDirectory($workingDirectory);
            }
        }
    }

    public function verify(BackupRun $run): BackupRun
    {
        if (! $run->isAvailable()) {
            throw new RuntimeException('Plik kopii zapasowej nie istnieje w prywatnym magazynie.');
        }

        $path = storage_path((string) $run->storage_path);
        $actualChecksum = hash_file('sha256', $path);

        if (! hash_equals((string) $run->checksum_sha256, $actualChecksum)) {
            $run->forceFill([
                'status' => 'corrupted',
                'error_message' => 'Suma SHA-256 archiwum nie zgadza się z zapisaną wartością.',
            ])->save();

            throw new RuntimeException('Kopia zapasowa ma nieprawidłową sumę SHA-256.');
        }

        $zip = new ZipArchive();
        $opened = $zip->open($path, ZipArchive::RDONLY);

        if ($opened !== true) {
            throw new RuntimeException('Nie można otworzyć archiwum kopii zapasowej.');
        }

        try {
            $manifestRaw = $zip->getFromName('manifest.json');

            if (! is_string($manifestRaw) || trim($manifestRaw) === '') {
                throw new RuntimeException('Archiwum nie zawiera manifest.json.');
            }

            $manifest = json_decode($manifestRaw, true, 512, JSON_THROW_ON_ERROR);
            $databaseFilename = (string) data_get($manifest, 'database.filename');
            $expectedDatabaseChecksum = (string) data_get($manifest, 'database.sha256');

            if ($databaseFilename === '' || $expectedDatabaseChecksum === '') {
                throw new RuntimeException('Manifest nie zawiera danych kontrolnych bazy.');
            }

            $databaseRaw = $zip->getFromName($databaseFilename);

            if (! is_string($databaseRaw)) {
                throw new RuntimeException('Archiwum nie zawiera pliku bazy wskazanego w manifeście.');
            }

            if (! hash_equals($expectedDatabaseChecksum, hash('sha256', $databaseRaw))) {
                throw new RuntimeException('Suma kontrolna pliku bazy w archiwum jest nieprawidłowa.');
            }
        } finally {
            $zip->close();
        }

        $run->forceFill([
            'status' => 'success',
            'verified_at' => now(),
            'error_message' => null,
        ])->save();

        return $run->fresh();
    }

    public function verifyRecent(int $limit = 10): array
    {
        $verified = 0;
        $failed = 0;

        BackupRun::query()
            ->where('status', 'success')
            ->whereNull('deleted_at')
            ->latest('completed_at')
            ->limit(max(1, min(100, $limit)))
            ->get()
            ->each(function (BackupRun $run) use (&$verified, &$failed): void {
                try {
                    $this->verify($run);
                    $verified++;
                } catch (Throwable $exception) {
                    $failed++;
                    $run->forceFill([
                        'status' => 'corrupted',
                        'error_message' => mb_substr($exception->getMessage(), 0, 4000),
                    ])->save();
                }
            });

        return compact('verified', 'failed');
    }

    public function cleanup(int $retentionDays): int
    {
        $retentionDays = max(3, min(365, $retentionDays));
        $deleted = 0;

        BackupRun::query()
            ->whereNull('deleted_at')
            ->where('created_at', '<', now()->subDays($retentionDays))
            ->orderBy('id')
            ->chunkById(100, function ($runs) use (&$deleted): void {
                foreach ($runs as $run) {
                    if (is_string($run->storage_path) && $run->storage_path !== '') {
                        @unlink(storage_path($run->storage_path));
                    }

                    $run->forceFill([
                        'deleted_at' => now(),
                        'storage_path' => null,
                    ])->save();
                    $deleted++;
                }
            });

        return $deleted;
    }

    private function dumpDatabase(string $workingDirectory): array
    {
        $driver = (string) config('database.default');

        return match ($driver) {
            'sqlite' => $this->dumpSqlite($workingDirectory),
            'mysql', 'mariadb' => $this->dumpMysql($workingDirectory, $driver),
            default => throw new RuntimeException('Nieobsługiwany sterownik kopii bazy: '.$driver),
        };
    }

    private function dumpSqlite(string $workingDirectory): array
    {
        $source = (string) config('database.connections.sqlite.database');

        if ($source === '' || ! is_file($source)) {
            throw new RuntimeException('Nie znaleziono pliku bazy SQLite.');
        }

        $target = $workingDirectory.DIRECTORY_SEPARATOR.'database.sqlite';

        if (! copy($source, $target)) {
            throw new RuntimeException('Nie udało się skopiować bazy SQLite.');
        }

        @chmod($target, 0600);

        return [$target, ['driver' => 'sqlite']];
    }

    private function dumpMysql(string $workingDirectory, string $driver): array
    {
        $connection = (array) config('database.connections.'.$driver);
        $database = (string) ($connection['database'] ?? '');
        $username = (string) ($connection['username'] ?? '');
        $password = (string) ($connection['password'] ?? '');
        $host = (string) ($connection['host'] ?? '127.0.0.1');
        $port = (string) ($connection['port'] ?? '3306');
        $socket = (string) ($connection['unix_socket'] ?? '');

        if ($database === '' || $username === '') {
            throw new RuntimeException('Konfiguracja MySQL/MariaDB jest niepełna.');
        }

        $binary = $this->findExecutable(['mysqldump', 'mariadb-dump']);

        if ($binary === null) {
            throw new RuntimeException('Nie znaleziono mysqldump ani mariadb-dump.');
        }

        $defaultsFile = $workingDirectory.DIRECTORY_SEPARATOR.'mysql-client.cnf';
        $defaults = "[client]\n"
            .'user='.$this->escapeOptionFileValue($username)."\n"
            .'password='.$this->escapeOptionFileValue($password)."\n"
            .'host='.$this->escapeOptionFileValue($host)."\n"
            .'port='.$this->escapeOptionFileValue($port)."\n";

        if ($socket !== '') {
            $defaults .= 'socket='.$this->escapeOptionFileValue($socket)."\n";
        }

        File::put($defaultsFile, $defaults);
        @chmod($defaultsFile, 0600);

        $target = $workingDirectory.DIRECTORY_SEPARATOR.'database.sql';
        $command = [
            $binary,
            '--defaults-extra-file='.$defaultsFile,
            '--single-transaction',
            '--quick',
            '--skip-lock-tables',
            '--routines',
            '--triggers',
            '--events',
            '--default-character-set=utf8mb4',
            $database,
        ];

        $this->runProcessToFile($command, $target, 600);
        @chmod($target, 0600);
        @unlink($defaultsFile);

        if (! is_file($target) || filesize($target) < 100) {
            throw new RuntimeException('Zrzut bazy MySQL/MariaDB jest pusty lub niekompletny.');
        }

        return [$target, [
            'driver' => $driver,
            'database' => $database,
        ]];
    }

    private function createArchive(string $archivePath, array $files): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Brak rozszerzenia PHP ZipArchive.');
        }

        $zip = new ZipArchive();
        $opened = $zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($opened !== true) {
            throw new RuntimeException('Nie można utworzyć archiwum kopii zapasowej.');
        }

        try {
            foreach ($files as $file) {
                if (! is_file($file) || ! $zip->addFile($file, basename($file))) {
                    throw new RuntimeException('Nie udało się dodać pliku do archiwum: '.basename($file));
                }
            }
        } finally {
            $zip->close();
        }
    }

    private function runProcessToFile(array $command, string $outputPath, int $timeoutSeconds): void
    {
        $output = fopen($outputPath, 'wb');

        if ($output === false) {
            throw new RuntimeException('Nie można utworzyć pliku zrzutu bazy.');
        }

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => $output,
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes, null, null, ['bypass_shell' => true]);

        if (! is_resource($process)) {
            fclose($output);
            throw new RuntimeException('Nie można uruchomić programu kopii bazy.');
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[2], false);
        $started = microtime(true);
        $stderr = '';

        try {
            do {
                $status = proc_get_status($process);
                $stderr .= (string) stream_get_contents($pipes[2]);

                if (! $status['running']) {
                    break;
                }

                if ((microtime(true) - $started) > $timeoutSeconds) {
                    proc_terminate($process, 9);
                    throw new RuntimeException('Przekroczono limit czasu tworzenia kopii bazy.');
                }

                usleep(100000);
            } while (true);

            $stderr .= (string) stream_get_contents($pipes[2]);
            $reportedExitCode = is_array($status ?? null) ? (int) ($status['exitcode'] ?? -1) : -1;
            fclose($pipes[2]);
            fclose($output);
            $exitCode = proc_close($process);
            if ($exitCode === -1 && $reportedExitCode >= 0) {
                $exitCode = $reportedExitCode;
            }

            if ($exitCode !== 0) {
                throw new RuntimeException(
                    'Program kopii bazy zakończył się błędem: '.mb_substr(trim($stderr), 0, 1000)
                );
            }
        } catch (Throwable $exception) {
            if (is_resource($pipes[2])) {
                fclose($pipes[2]);
            }
            fclose($output);
            @proc_terminate($process, 9);
            @proc_close($process);
            throw $exception;
        }
    }

    private function findExecutable(array $names): ?string
    {
        $directories = array_filter(array_unique(array_merge(
            explode(PATH_SEPARATOR, (string) getenv('PATH')),
            ['/usr/bin', '/usr/local/bin', '/bin']
        )));

        foreach ($names as $name) {
            foreach ($directories as $directory) {
                $candidate = rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$name;

                if (is_file($candidate) && is_executable($candidate)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    private function escapeOptionFileValue(string $value): string
    {
        return '"'.str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '', ''], $value).'"';
    }
}
