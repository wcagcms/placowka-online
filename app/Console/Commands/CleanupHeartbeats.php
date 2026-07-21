<?php

namespace App\Console\Commands;

use App\Models\Heartbeat;
use Illuminate\Console\Command;

class CleanupHeartbeats extends Command
{
    protected $signature = 'placowka:cleanup-heartbeats
        {--days=60 : Ile dni historii heartbeatów zostawić}
        {--dry-run : Tylko pokaż liczbę rekordów bez kasowania}';

    protected $description = 'Usuwa stare heartbeaty, żeby baza danych nie rosła bez końca.';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $query = Heartbeat::query()
            ->where('created_at', '<', $cutoff);

        $count = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->info('Tryb testowy. Nic nie usunięto.');
            $this->line('Zostawiamy historię z ostatnich dni: ' . $days);
            $this->line('Do usunięcia: ' . $count . ' heartbeatów.');
            return self::SUCCESS;
        }

        $deleted = 0;

        $query->orderBy('id')->chunkById(1000, function ($heartbeats) use (&$deleted) {
            $ids = $heartbeats->pluck('id');

            $deleted += Heartbeat::query()
                ->whereIn('id', $ids)
                ->delete();
        });

        $this->info('Usunięto stare heartbeaty.');
        $this->line('Pozostawiono ostatnie dni: ' . $days);
        $this->line('Usunięto rekordów: ' . $deleted);

        return self::SUCCESS;
    }
}
