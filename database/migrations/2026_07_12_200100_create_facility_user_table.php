<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facility_user', function (Blueprint $table): void {
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['facility_id', 'user_id']);
            $table->index(['user_id', 'facility_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facility_user');
    }
};
