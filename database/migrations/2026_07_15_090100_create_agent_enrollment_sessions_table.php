<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_enrollment_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('agent_enrollment_code_id')
                ->constrained('agent_enrollment_codes')
                ->cascadeOnDelete();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->string('session_token_hash', 64);
            $table->string('client_nonce_hash', 64);
            $table->string('machine_name', 255);
            $table->string('architecture', 32)->nullable();
            $table->string('windows_version', 255)->nullable();
            $table->string('setup_version', 50)->nullable();
            $table->string('start_ip', 45)->nullable();
            $table->string('complete_ip', 45)->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('completed_at')->nullable()->index();
            $table->timestamp('confirmed_at')->nullable()->index();
            $table->text('issued_token_ciphertext')->nullable();
            $table->timestamp('token_replay_until')->nullable();
            $table->timestamps();

            $table->index(['device_id', 'completed_at']);
            $table->index(['agent_enrollment_code_id', 'expires_at'], 'enrollment_sessions_code_expiry_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_enrollment_sessions');
    }
};
