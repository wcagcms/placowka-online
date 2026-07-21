<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_enrollment_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code_hash', 64)->unique();
            $table->string('code_label', 32);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(5);
            $table->timestamp('expires_at')->index();
            $table->timestamp('claimed_at')->nullable()->index();
            $table->timestamp('used_at')->nullable()->index();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->string('created_ip', 45)->nullable();
            $table->string('claimed_ip', 45)->nullable();
            $table->string('used_ip', 45)->nullable();
            $table->timestamps();

            $table->index(['device_id', 'expires_at']);
            $table->index(['device_id', 'used_at', 'revoked_at'], 'enrollment_codes_device_state_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_enrollment_codes');
    }
};
