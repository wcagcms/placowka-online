<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();

            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('token_hash', 64);

            $table->string('status', 30)->default('unknown')->index();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->unsignedInteger('last_latency_ms')->nullable();
            $table->string('last_ip', 45)->nullable();

            $table->boolean('internet_ok')->nullable();
            $table->boolean('dns_ok')->nullable();
            $table->boolean('gateway_ok')->nullable();

            $table->string('agent_version')->nullable();
            $table->unsignedSmallInteger('check_interval_seconds')->default(60);
            $table->unsignedSmallInteger('missing_after_minutes')->default(3);
            $table->unsignedSmallInteger('alert_after_minutes')->default(5);

            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['facility_id', 'status']);
            $table->index(['is_active', 'last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
