<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('placowka:check-status')
    ->everyMinute()
    ->withoutOverlapping(2)
    ->appendOutputTo(storage_path('logs/placowka-check-status.log'));

Schedule::command('placowka:cleanup-heartbeats --days=60')
    ->dailyAt('03:20')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/placowka-cleanup-heartbeats.log'));

Schedule::command('placowka:cleanup-agent-packages --hours=24')
    ->hourly()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/placowka-cleanup-agent-packages.log'));


Schedule::command('placowka:cleanup-enrollments --days=90')
    ->hourly()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/placowka-cleanup-enrollments.log'));

Schedule::command('placowka:backup --retention=14')
    ->dailyAt('02:30')
    ->when(fn (): bool => (bool) config('placowka.backup_enabled', true))
    ->withoutOverlapping(120)
    ->appendOutputTo(storage_path('logs/placowka-backup.log'));

Schedule::command('placowka:backup-verify --limit=7')
    ->weeklyOn(1, '04:10')
    ->when(fn (): bool => (bool) config('placowka.backup_enabled', true))
    ->withoutOverlapping(120)
    ->appendOutputTo(storage_path('logs/placowka-backup-verify.log'));

Schedule::call(function (): void {
    file_put_contents(
        storage_path('app/placowka-cron-last-run.txt'),
        now()->timezone('Europe/Warsaw')->format('Y-m-d H:i:s').PHP_EOL
    );
})
    ->name('placowka-cron-heartbeat')
    ->everyMinute()
    ->withoutOverlapping();
