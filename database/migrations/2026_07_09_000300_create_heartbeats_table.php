<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('heartbeats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();

            $table->string('status', 30)->default('online');
            $table->boolean('internet_ok')->default(true);
            $table->boolean('dns_ok')->default(true);
            $table->boolean('gateway_ok')->nullable();

            $table->unsignedInteger('latency_ms')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('agent_version')->nullable();

            $table->timestamp('checked_at')->nullable();
            $table->json('payload')->nullable();

            $table->timestamps();

            $table->index(['device_id', 'checked_at']);
            $table->index(['device_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('heartbeats');
    }
};
