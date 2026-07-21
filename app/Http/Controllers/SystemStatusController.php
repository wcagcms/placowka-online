<?php

namespace App\Http\Controllers;

use App\Models\BackupRun;
use App\Models\Device;
use App\Models\Facility;
use App\Models\Heartbeat;
use App\Models\Incident;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class SystemStatusController extends Controller
{
    public function index()
    {
        $now = now('Europe/Warsaw');

        $cronFile = storage_path('app/placowka-cron-last-run.txt');

        $cronLastRun = $this->readCronLastRun($cronFile);
        $cronAgeSeconds = $cronLastRun ? $cronLastRun->diffInSeconds($now) : null;
        $cronStatus = $this->cronStatus($cronAgeSeconds);

        $database = $this->databaseStatus();

        $counts = $database['ok'] ? $this->databaseCounts() : $this->emptyCounts();

        $mail = $this->mailStatus();

        $paths = [
            'cron_schedule' => storage_path('logs/cron-schedule.log'),
            'check_status' => storage_path('logs/placowka-check-status.log'),
            'cleanup_heartbeats' => storage_path('logs/placowka-cleanup-heartbeats.log'),
            'cron_heartbeat' => $cronFile,
            'backup_schedule' => storage_path('logs/placowka-backup.log'),
        ];

        $logs = [];

        foreach ($paths as $key => $path) {
            $logs[$key] = $this->fileStatus($path);
        }

        $storageChecks = [
            [
                'name' => 'storage',
                'path' => storage_path(),
                'writable' => is_writable(storage_path()),
            ],
            [
                'name' => 'storage/app',
                'path' => storage_path('app'),
                'writable' => is_writable(storage_path('app')),
            ],
            [
                'name' => 'storage/logs',
                'path' => storage_path('logs'),
                'writable' => is_writable(storage_path('logs')),
            ],
            [
                'name' => 'public/panel',
                'path' => public_path('panel'),
                'writable' => is_writable(public_path('panel')),
            ],
        ];

        $lastHeartbeat = null;
        $lastOpenIncident = null;
        $lastBackup = null;

        if ($database['ok']) {
            $lastHeartbeat = Heartbeat::query()
                ->with(['device.facility'])
                ->latest('created_at')
                ->first();

            $lastBackup = BackupRun::query()->where('status', 'success')->whereNull('deleted_at')->latest('completed_at')->first();

            $lastOpenIncident = Incident::query()
                ->with(['device', 'facility'])
                ->whereIn('status', \App\Models\Incident::ACTIVE_STATUSES)
                ->latest('started_at')
                ->first();
        }

        $checks = [
            [
                'name' => 'Cron / Laravel Scheduler',
                'status' => $cronStatus,
                'value' => $cronLastRun
                    ? $cronLastRun->format('Y-m-d H:i:s')
                    : 'brak danych',
                'description' => $this->cronDescription($cronAgeSeconds),
            ],
            [
                'name' => 'Baza danych',
                'status' => $database['ok'] ? 'online' : 'offline',
                'value' => $database['ok'] ? 'połączenie działa' : 'błąd',
                'description' => $database['message'],
            ],
            [
                'name' => 'Powiadomienia e-mail',
                'status' => $mail['status'],
                'value' => $mail['label'],
                'description' => $mail['description'],
            ],
            [
                'name' => 'Zapis logów',
                'status' => is_writable(storage_path('logs')) ? 'online' : 'offline',
                'value' => is_writable(storage_path('logs')) ? 'OK' : 'brak zapisu',
                'description' => storage_path('logs'),
            ],
            [
                'name' => 'Kopia zapasowa bazy',
                'status' => $lastBackup && $lastBackup->completed_at?->gte(now()->subHours(30)) ? 'online' : 'problem',
                'value' => $lastBackup?->completed_at?->format('Y-m-d H:i:s') ?? 'brak kopii',
                'description' => $lastBackup
                    ? 'Ostatnia poprawna kopia ma zweryfikowaną sumę SHA-256.'
                    : 'Nie znaleziono poprawnej kopii bazy danych.',
            ],
            [
                'name' => 'Tryb debugowania',
                'status' => config('app.debug') ? 'problem' : 'online',
                'value' => config('app.debug') ? 'APP_DEBUG=true' : 'APP_DEBUG=false',
                'description' => config('app.debug')
                    ? 'Na produkcji zalecane jest APP_DEBUG=false.'
                    : 'Tryb produkcyjny jest ustawiony bezpiecznie.',
            ],
        ];

        return view('system-status', [
            'now' => $now,
            'checks' => $checks,
            'counts' => $counts,
            'logs' => $logs,
            'storageChecks' => $storageChecks,
            'lastHeartbeat' => $lastHeartbeat,
            'lastOpenIncident' => $lastOpenIncident,
            'lastBackup' => $lastBackup,
            'cronLastRun' => $cronLastRun,
            'cronAgeSeconds' => $cronAgeSeconds,
            'mail' => $mail,
            'appInfo' => [
                'app_name' => config('app.name'),
                'app_env' => config('app.env'),
                'app_url' => config('app.url'),
                'timezone' => config('app.timezone'),
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
            ],
        ]);
    }

    private function databaseStatus(): array
    {
        try {
            DB::select('select 1');

            return [
                'ok' => true,
                'message' => 'Połączenie z bazą danych działa poprawnie.',
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    private function databaseCounts(): array
    {
        return [
            'facilities_total' => Facility::query()->count(),
            'facilities_active' => Facility::query()->where('is_active', true)->count(),

            'devices_total' => Device::query()->whereNull('archived_at')->count(),
            'devices_archived' => Device::query()->whereNotNull('archived_at')->count(),
            'devices_online' => Device::query()->whereNull('archived_at')->where('status', 'online')->count(),
            'devices_problem' => Device::query()->whereNull('archived_at')->where('status', 'problem')->count(),
            'devices_offline' => Device::query()->whereNull('archived_at')->where('status', 'offline')->count(),
            'devices_unknown' => Device::query()->whereNull('archived_at')->where('status', 'unknown')->count(),

            'heartbeats_total' => Heartbeat::query()->count(),
            'heartbeats_24h' => Heartbeat::query()->where('created_at', '>=', now()->subDay())->count(),

            'incidents_open' => Incident::query()->whereIn('status', \App\Models\Incident::ACTIVE_STATUSES)->count(),
            'incidents_24h' => Incident::query()->where('started_at', '>=', now()->subDay())->count(),
            'incidents_30d' => Incident::query()->where('started_at', '>=', now()->subDays(30))->count(),
        ];
    }

    private function emptyCounts(): array
    {
        return [
            'facilities_total' => 0,
            'facilities_active' => 0,
            'devices_total' => 0,
            'devices_archived' => 0,
            'devices_online' => 0,
            'devices_problem' => 0,
            'devices_offline' => 0,
            'devices_unknown' => 0,
            'heartbeats_total' => 0,
            'heartbeats_24h' => 0,
            'incidents_open' => 0,
            'incidents_24h' => 0,
            'incidents_30d' => 0,
        ];
    }

    private function mailStatus(): array
    {
        $defaultMailer = (string) config('mail.default');
        $fromAddress = (string) config('mail.from.address');

        $smtpHost = (string) config('mail.mailers.smtp.host');
        $smtpPort = (string) config('mail.mailers.smtp.port');
        $smtpEncryption = (string) config('mail.mailers.smtp.encryption');

        if ($defaultMailer === '') {
            return [
                'status' => 'offline',
                'label' => 'brak MAIL_MAILER',
                'description' => 'Nie ustawiono domyślnego mailera.',
                'default_mailer' => $defaultMailer,
                'from_address' => $fromAddress,
                'smtp_host_present' => false,
                'smtp_port' => $smtpPort,
                'smtp_encryption' => $smtpEncryption,
            ];
        }

        if ($fromAddress === '') {
            return [
                'status' => 'problem',
                'label' => 'brak adresu nadawcy',
                'description' => 'Brakuje MAIL_FROM_ADDRESS.',
                'default_mailer' => $defaultMailer,
                'from_address' => $fromAddress,
                'smtp_host_present' => $smtpHost !== '',
                'smtp_port' => $smtpPort,
                'smtp_encryption' => $smtpEncryption,
            ];
        }

        if ($defaultMailer === 'smtp' && $smtpHost === '') {
            return [
                'status' => 'problem',
                'label' => 'SMTP niepełny',
                'description' => 'Mailer SMTP jest aktywny, ale brakuje hosta SMTP.',
                'default_mailer' => $defaultMailer,
                'from_address' => $fromAddress,
                'smtp_host_present' => false,
                'smtp_port' => $smtpPort,
                'smtp_encryption' => $smtpEncryption,
            ];
        }

        return [
            'status' => 'online',
            'label' => 'konfiguracja wygląda poprawnie',
            'description' => 'Hasła i tokeny nie są pokazywane w panelu.',
            'default_mailer' => $defaultMailer,
            'from_address' => $fromAddress,
            'smtp_host_present' => $smtpHost !== '',
            'smtp_port' => $smtpPort,
            'smtp_encryption' => $smtpEncryption,
        ];
    }

    private function readCronLastRun(string $path): ?Carbon
    {
        if (! is_file($path)) {
            return null;
        }

        $value = trim((string) file_get_contents($path));

        if ($value === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d H:i:s', $value, 'Europe/Warsaw');
        } catch (Throwable $e) {
            try {
                return Carbon::parse($value)->timezone('Europe/Warsaw');
            } catch (Throwable $e) {
                return null;
            }
        }
    }

    private function cronStatus(?int $ageSeconds): string
    {
        if ($ageSeconds === null) {
            return 'offline';
        }

        if ($ageSeconds <= 120) {
            return 'online';
        }

        if ($ageSeconds <= 600) {
            return 'problem';
        }

        return 'offline';
    }

    private function cronDescription(?int $ageSeconds): string
    {
        if ($ageSeconds === null) {
            return 'Brak pliku kontrolnego crona. Scheduler prawdopodobnie jeszcze się nie uruchomił.';
        }

        if ($ageSeconds <= 120) {
            return 'Cron działa poprawnie. Ostatnie uruchomienie było mniej niż 2 minuty temu.';
        }

        if ($ageSeconds <= 600) {
            return 'Cron działał niedawno, ale nie odświeża się co minutę. Sprawdź crontab i log schedulera.';
        }

        return 'Cron nie działa prawidłowo albo zatrzymał się ponad 10 minut temu.';
    }

    private function fileStatus(string $path): array
    {
        $exists = is_file($path);

        return [
            'path' => $path,
            'exists' => $exists,
            'size' => $exists ? filesize($path) : 0,
            'modified_at' => $exists
                ? Carbon::createFromTimestamp(filemtime($path))->timezone('Europe/Warsaw')
                : null,
            'last_lines' => $exists ? $this->tail($path, 25) : [],
        ];
    }

    private function tail(string $path, int $lines = 25): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            return [];
        }

        $content = file($path, FILE_IGNORE_NEW_LINES);

        if (! is_array($content)) {
            return [];
        }

        return array_slice($content, -$lines);
    }
}
