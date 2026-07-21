<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Services\AgentPackageService;
use Illuminate\Console\Command;
use Throwable;

class RotateDeviceToken extends Command
{
    protected $signature = 'placowka:rotate-token
        {uuid? : UUID urządzenia}
        {--device-id= : ID urządzenia w bazie}';

    protected $description = 'Bezpiecznie zmienia token i tworzy nową, jednorazową paczkę ZIP agenta.';

    public function handle(AgentPackageService $packages): int
    {
        $uuid = $this->argument('uuid');
        $deviceId = $this->option('device-id');

        if (! $uuid && ! $deviceId) {
            $this->error('Podaj UUID urządzenia albo --device-id=.');

            return self::INVALID;
        }

        $device = Device::query()
            ->with('facility')
            ->when($deviceId, fn ($query) => $query->whereKey($deviceId))
            ->when(! $deviceId, fn ($query) => $query->where('uuid', $uuid))
            ->first();

        if (! $device) {
            $this->error('Nie znaleziono urządzenia.');

            return self::FAILURE;
        }

        try {
            $zipName = $packages->regenerateForDevice($device);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            $this->warn('Poprzedni token pozostał aktywny.');

            return self::FAILURE;
        }

        $this->info('Token został zmieniony dopiero po prawidłowym utworzeniu paczki ZIP.');
        $this->line('Placówka: '.$device->facility->code.' — '.$device->facility->name);
        $this->line('Urządzenie: '.$device->name);
        $this->line('UUID: '.$device->uuid);
        $this->warn('Poprzedni agent przestanie działać. Zainstaluj nową paczkę na właściwym komputerze.');
        $this->line(storage_path('app/agent-packages/'.$zipName));

        return self::SUCCESS;
    }
}
