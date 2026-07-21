<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('devices', 'archived_at')) {
            Schema::table('devices', function (Blueprint $table) {
                $table->timestamp('archived_at')->nullable()->after('is_active');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('devices', 'archived_at')) {
            Schema::table('devices', function (Blueprint $table) {
                $table->dropColumn('archived_at');
            });
        }
    }
};
