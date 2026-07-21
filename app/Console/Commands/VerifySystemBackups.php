<?php

namespace App\Console\Commands;

use App\Services\SystemBackupService;
use Illuminate\Console\Command;

class VerifySystemBackups extends Command
{
    protected $signature = 'placowka:backup-verify {--limit=10 : Liczba najnowszych kopii}';

    protected $description = 'Weryfikuje sumy SHA-256 i zawartość najnowszych kopii.';

    public function handle(SystemBackupService $backups): int
    {
        $result = $backups->verifyRecent((int) $this->option('limit'));

        $this->info('Zweryfikowano: '.$result['verified']);
        $this->line('Błędy: '.$result['failed']);

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
