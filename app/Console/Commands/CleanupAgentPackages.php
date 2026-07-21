<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CleanupAgentPackages extends Command
{
    protected $signature = 'placowka:cleanup-agent-packages {--hours=24 : Maksymalny wiek paczek i katalogów roboczych}';

    protected $description = 'Usuwa przeterminowane paczki agentów i katalogi robocze zawierające tokeny.';

    public function handle(): int
    {
        $hours = max(1, min(168, (int) $this->option('hours')));
        $cutoff = now()->subHours($hours)->getTimestamp();
        $removed = 0;

        foreach ([storage_path('app/agent-packages'), storage_path('app/agent-builds')] as $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            foreach (File::allFiles($directory) as $file) {
                if ($file->getMTime() < $cutoff) {
                    File::delete($file->getPathname());
                    $removed++;
                }
            }

            foreach (array_reverse(File::directories($directory)) as $subdirectory) {
                if (is_dir($subdirectory) && count(scandir($subdirectory) ?: []) <= 2) {
                    File::deleteDirectory($subdirectory);
                }
            }
        }

        $this->info('Usunięto plików: '.$removed);

        return self::SUCCESS;
    }
}
