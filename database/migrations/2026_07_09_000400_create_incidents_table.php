<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();

            $table->string('type', 50)->index();
            $table->string('status', 30)->default('open')->index();

            $table->timestamp('started_at')->index();
            $table->timestamp('ended_at')->nullable()->index();
            $table->timestamp('last_seen_at')->nullable();

            $table->unsignedInteger('duration_seconds')->nullable();

            $table->string('summary')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['device_id', 'type', 'status']);
            $table->index(['facility_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
