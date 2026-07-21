<?php

namespace App\Console\Commands;

use App\Services\SystemBackupService;
use Illuminate\Console\Command;
use Throwable;

class CreateSystemBackup extends Command
{
    protected $signature = 'placowka:backup {--retention= : Liczba dni przechowywania kopii}';

    protected $description = 'Tworzy i weryfikuje prywatną kopię bazy Placówka Online.';

    public function handle(SystemBackupService $backups): int
    {
        try {
            $retention = $this->option('retention');
            $run = $backups->create($retention !== null ? (int) $retention : null);

            $this->info('Kopia utworzona i zweryfikowana.');
            $this->line('Plik: '.$run->filename);
            $this->line('SHA-256: '.$run->checksum_sha256);
            $this->line('Rozmiar: '.$run->size_bytes.' B');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Błąd kopii: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
