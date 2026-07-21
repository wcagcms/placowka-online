<?php

namespace App\Console\Commands;

use App\Models\Facility;
use App\Services\AgentPackageService;
use Illuminate\Console\Command;
use Throwable;

class CreateMonitoringDevice extends Command
{
    protected $signature = 'placowka:create-device
        {facility_code : Kod placówki, np. PP10}
        {facility_name : Nazwa placówki}
        {device_name : Nazwa urządzenia, np. Sekretariat}
        {--email= : E-mail kontaktowy placówki}';

    protected $description = 'Tworzy urządzenie i bezpieczną, jednorazową paczkę ZIP agenta.';

    public function handle(AgentPackageService $packages): int
    {
        $facilityCode = mb_strtoupper(trim((string) $this->argument('facility_code')));
        $facilityName = trim((string) $this->argument('facility_name'));
        $deviceName = trim((string) $this->argument('device_name'));
        $email = $this->option('email');

        $facility = Facility::query()->firstOrCreate(
            ['code' => $facilityCode],
            [
                'name' => $facilityName,
                'contact_email' => $email,
                'is_active' => true,
            ]
        );

        $facility->forceFill([
            'name' => $facilityName,
            'contact_email' => $email ?: $facility->contact_email,
            'is_active' => true,
        ])->save();

        try {
            [$device, $zipName] = $packages->createForNewDevice($facility, $deviceName);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Utworzono urządzenie i bezpieczną paczkę agenta.');
        $this->line('Placówka: '.$facility->code.' — '.$facility->name);
        $this->line('Urządzenie: '.$device->name);
        $this->line('UUID: '.$device->uuid);
        $this->warn('Token znajduje się wyłącznie w paczce ZIP. Nie jest wypisywany w terminalu.');
        $this->line(storage_path('app/agent-packages/'.$zipName));

        return self::SUCCESS;
    }
}
