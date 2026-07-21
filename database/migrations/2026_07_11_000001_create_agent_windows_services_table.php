<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_windows_services', function (Blueprint $table): void {
            $table->id();
            $table->string('system_name', 150)->unique();
            $table->string('label', 190);
            $table->string('expected_status', 50)->default('Running');
            $table->boolean('monitoring_enabled')->default(true);
            $table->boolean('alert_enabled')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        $now = now();

        DB::table('agent_windows_services')->insert([
            [
                'system_name' => 'Dhcp',
                'label' => 'Klient DHCP',
                'expected_status' => 'Running',
                'monitoring_enabled' => true,
                'alert_enabled' => true,
                'sort_order' => 10,
                'description' => 'Odpowiada za automatyczne pobieranie konfiguracji IP z serwera DHCP.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'system_name' => 'Dnscache',
                'label' => 'Klient DNS',
                'expected_status' => 'Running',
                'monitoring_enabled' => true,
                'alert_enabled' => true,
                'sort_order' => 20,
                'description' => 'Odpowiada za rozwiązywanie i buforowanie nazw DNS.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'system_name' => 'EventLog',
                'label' => 'Dziennik zdarzeń Windows',
                'expected_status' => 'Running',
                'monitoring_enabled' => true,
                'alert_enabled' => true,
                'sort_order' => 30,
                'description' => 'Zapisuje zdarzenia systemowe i aplikacyjne Windows.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'system_name' => 'Winmgmt',
                'label' => 'Instrumentacja zarządzania Windows',
                'expected_status' => 'Running',
                'monitoring_enabled' => true,
                'alert_enabled' => true,
                'sort_order' => 40,
                'description' => 'Udostępnia dane WMI wykorzystywane do diagnostyki systemu.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'system_name' => 'LanmanWorkstation',
                'label' => 'Stacja robocza',
                'expected_status' => 'Running',
                'monitoring_enabled' => true,
                'alert_enabled' => true,
                'sort_order' => 50,
                'description' => 'Obsługuje połączenia klienta z udziałami sieciowymi SMB.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'system_name' => 'WinDefend',
                'label' => 'Microsoft Defender',
                'expected_status' => 'Running',
                'monitoring_enabled' => true,
                'alert_enabled' => false,
                'sort_order' => 60,
                'description' => 'Stan wbudowanej ochrony antywirusowej Microsoft Defender.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'system_name' => 'wuauserv',
                'label' => 'Windows Update',
                'expected_status' => 'Running',
                'monitoring_enabled' => true,
                'alert_enabled' => false,
                'sort_order' => 70,
                'description' => 'Usługa wykrywania, pobierania i instalowania aktualizacji Windows.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'system_name' => 'Spooler',
                'label' => 'Bufor wydruku',
                'expected_status' => 'Running',
                'monitoring_enabled' => true,
                'alert_enabled' => false,
                'sort_order' => 80,
                'description' => 'Obsługuje kolejkę wydruków i drukarki zainstalowane w systemie.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_windows_services');
    }
};
