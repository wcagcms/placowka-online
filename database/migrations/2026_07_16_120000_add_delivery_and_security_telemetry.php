<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('heartbeats', function (Blueprint $table): void {
            $table->uuid('heartbeat_uuid')->nullable()->after('device_id');
            $table->timestamp('received_at')->nullable()->after('checked_at');
            $table->boolean('is_replayed')->default(false)->after('received_at');
            $table->unsignedInteger('queue_delay_seconds')->nullable()->after('is_replayed');

            $table->unique(['device_id', 'heartbeat_uuid'], 'heartbeats_device_uuid_unique');
            $table->index(['is_replayed', 'received_at']);
        });

        Schema::table('devices', function (Blueprint $table): void {
            $table->json('windows_update')->nullable()->after('network_info_updated_at');
            $table->timestamp('windows_update_updated_at')->nullable()->after('windows_update');
            $table->json('defender_status')->nullable()->after('windows_update_updated_at');
            $table->timestamp('defender_status_updated_at')->nullable()->after('defender_status');
        });
    }

    public function down(): void
    {
        Schema::table('heartbeats', function (Blueprint $table): void {
            $table->dropUnique('heartbeats_device_uuid_unique');
            $table->dropIndex(['is_replayed', 'received_at']);
            $table->dropColumn([
                'heartbeat_uuid',
                'received_at',
                'is_replayed',
                'queue_delay_seconds',
            ]);
        });

        Schema::table('devices', function (Blueprint $table): void {
            $table->dropColumn([
                'windows_update',
                'windows_update_updated_at',
                'defender_status',
                'defender_status_updated_at',
            ]);
        });
    }
};
