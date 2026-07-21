<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table): void {
            $table->json('network_info')->nullable()->after('agent_version');
            $table->timestamp('network_info_updated_at')->nullable()->after('network_info');
        });

        Schema::table('heartbeats', function (Blueprint $table): void {
            $table->json('network_info')->nullable()->after('agent_version');
        });
    }

    public function down(): void
    {
        Schema::table('heartbeats', function (Blueprint $table): void {
            $table->dropColumn('network_info');
        });

        Schema::table('devices', function (Blueprint $table): void {
            $table->dropColumn(['network_info', 'network_info_updated_at']);
        });
    }
};
