<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->timestamp('opened_notification_sent_at')->nullable();
            $table->timestamp('resolved_notification_sent_at')->nullable();
            $table->text('notification_last_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->dropColumn([
                'opened_notification_sent_at',
                'resolved_notification_sent_at',
                'notification_last_error',
            ]);
        });
    }
};
