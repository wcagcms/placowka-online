<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role', 20)->default('operator')->after('password');
            $table->boolean('is_active')->default(true)->after('role');
            $table->boolean('must_change_password')->default(true)->after('is_active');
            $table->unsignedInteger('auth_version')->default(1)->after('must_change_password');
            $table->timestamp('last_login_at')->nullable()->after('remember_token');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');

            $table->index(['role', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['role', 'is_active']);
            $table->dropColumn([
                'role',
                'is_active',
                'must_change_password',
                'auth_version',
                'last_login_at',
                'last_login_ip',
            ]);
        });
    }
};
