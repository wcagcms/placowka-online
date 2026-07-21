<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('heartbeats', function (Blueprint $table): void {
            $table->boolean('monitoring_server_ok')->nullable()->after('gateway_ok');
            $table->unsignedInteger('dns_latency_ms')->nullable()->after('latency_ms');
            $table->string('diagnostic_status', 50)->nullable()->after('status');

            $table->index(['diagnostic_status', 'created_at']);
        });

        Schema::table('devices', function (Blueprint $table): void {
            $table->boolean('monitoring_server_ok')->nullable()->after('gateway_ok');
            $table->unsignedInteger('last_dns_latency_ms')->nullable()->after('last_latency_ms');
            $table->string('diagnostic_status', 50)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('heartbeats', function (Blueprint $table): void {
            $table->dropIndex(['diagnostic_status', 'created_at']);
            $table->dropColumn([
                'monitoring_server_ok',
                'dns_latency_ms',
                'diagnostic_status',
            ]);
        });

        Schema::table('devices', function (Blueprint $table): void {
            $table->dropColumn([
                'monitoring_server_ok',
                'last_dns_latency_ms',
                'diagnostic_status',
            ]);
        });
    }
};
