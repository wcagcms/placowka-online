<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('devices', 'agent_health')) {
            Schema::table('devices', function (Blueprint $table): void {
                $table->json('agent_health')->nullable()->after('agent_version');
                $table->timestamp('agent_health_updated_at')->nullable()->after('agent_health');
                $table->index('agent_health_updated_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('devices', 'agent_health')) {
            Schema::table('devices', function (Blueprint $table): void {
                $table->dropIndex(['agent_health_updated_at']);
                $table->dropColumn(['agent_health', 'agent_health_updated_at']);
            });
        }
    }
};
