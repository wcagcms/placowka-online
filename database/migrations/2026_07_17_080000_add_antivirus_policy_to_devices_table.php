<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table): void {
            $table->string('antivirus_policy', 32)
                ->default('auto')
                ->after('defender_status_updated_at');

            $table->string('expected_antivirus_provider', 120)
                ->nullable()
                ->after('antivirus_policy');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table): void {
            $table->dropColumn([
                'antivirus_policy',
                'expected_antivirus_provider',
            ]);
        });
    }
};
