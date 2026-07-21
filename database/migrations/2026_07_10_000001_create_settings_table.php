<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type', 30)->default('string');
            $table->string('label');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        $now = now();

        DB::table('settings')->insert([
            [
                'key' => 'admin_email',
                'value' => '',
                'type' => 'email',
                'label' => 'E-mail administratora',
                'description' => 'Adres, na który system może wysyłać informacje administracyjne i alerty.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'email_alerts_enabled',
                'value' => '1',
                'type' => 'boolean',
                'label' => 'Powiadomienia e-mail',
                'description' => 'Włącza lub wyłącza wysyłanie alertów e-mail o awariach i przywróceniu połączenia.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'default_missing_after_minutes',
                'value' => '5',
                'type' => 'integer',
                'label' => 'Czas uznania urządzenia za niedostępne',
                'description' => 'Liczba minut bez heartbeat, po której urządzenie zostaje oznaczone jako niedostępne.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'default_alert_after_minutes',
                'value' => '10',
                'type' => 'integer',
                'label' => 'Opóźnienie wysłania alertu',
                'description' => 'Liczba minut od wykrycia niedostępności, po której system wysyła alert.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'heartbeat_retention_days',
                'value' => '60',
                'type' => 'integer',
                'label' => 'Okres przechowywania heartbeatów',
                'description' => 'Liczba dni, przez które przechowywana jest szczegółowa historia heartbeatów.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'default_check_interval_seconds',
                'value' => '60',
                'type' => 'integer',
                'label' => 'Domyślny interwał agenta',
                'description' => 'Domyślna liczba sekund pomiędzy kolejnymi heartbeatami agenta.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'panel_system_name',
                'value' => 'Placówka Online',
                'type' => 'string',
                'label' => 'Nazwa systemu',
                'description' => 'Nazwa wyświetlana w panelu administratora.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
