<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table): void {
            $table->string('priority', 20)->default('medium')->after('status')->index();
            $table->foreignId('assigned_user_id')->nullable()->after('priority')->constrained('users')->nullOnDelete();
            $table->foreignId('acknowledged_by_user_id')->nullable()->after('assigned_user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable()->after('acknowledged_by_user_id');
            $table->foreignId('resolved_by_user_id')->nullable()->after('acknowledged_at')->constrained('users')->nullOnDelete();
            $table->text('resolution_note')->nullable()->after('resolved_by_user_id');
            $table->timestamp('closed_at')->nullable()->after('resolution_note');
            $table->foreignId('closed_by_user_id')->nullable()->after('closed_at')->constrained('users')->nullOnDelete();
            $table->timestamp('last_status_change_at')->nullable()->after('closed_by_user_id');
            $table->unsignedInteger('occurrence_count')->default(1)->after('last_status_change_at');

            $table->index(['status', 'priority']);
            $table->index(['assigned_user_id', 'status']);
        });

        DB::table('incidents')->whereNull('last_status_change_at')->update([
            'last_status_change_at' => DB::raw('updated_at'),
        ]);
    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table): void {
            $table->dropIndex(['status', 'priority']);
            $table->dropIndex(['assigned_user_id', 'status']);
            $table->dropConstrainedForeignId('assigned_user_id');
            $table->dropConstrainedForeignId('acknowledged_by_user_id');
            $table->dropConstrainedForeignId('resolved_by_user_id');
            $table->dropConstrainedForeignId('closed_by_user_id');
            $table->dropColumn([
                'priority',
                'acknowledged_at',
                'resolution_note',
                'closed_at',
                'last_status_change_at',
                'occurrence_count',
            ]);
        });
    }
};
