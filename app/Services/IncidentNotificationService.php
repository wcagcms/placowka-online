<?php

namespace App\Services;

use App\Models\Incident;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class IncidentNotificationService
{
    public function sendOpened(Incident $incident): void
    {
        $incident->loadMissing(['facility', 'device']);

        if (! $this->enabled()) {
            return;
        }

        if ($incident->opened_notification_sent_at) {
            return;
        }

        $subject = $this->subject('AWARIA', $incident);

        $body = $this->buildOpenedBody($incident);

        $this->send($incident, $subject, $body, 'opened');
    }

    public function sendResolved(Incident $incident): void
    {
        $incident->loadMissing(['facility', 'device']);

        if (! $this->enabled()) {
            return;
        }

        if ($incident->resolved_notification_sent_at) {
            return;
        }

        $subject = $this->subject('PRZYWRÓCONO', $incident);

        $body = $this->buildResolvedBody($incident);

        $this->send($incident, $subject, $body, 'resolved');
    }

    private function enabled(): bool
    {
        return (bool) config('placowka.email_alerts_enabled') && count($this->recipients()) > 0;
    }

    private function recipients(): array
    {
        $raw = (string) config('placowka.alert_email_to');

        return collect(preg_split('/[,;]/', $raw))
            ->map(fn ($email) => trim((string) $email))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function send(Incident $incident, string $subject, string $body, string $mode): void
    {
        try {
            Mail::raw($body, function ($message) use ($subject) {
                $message->to($this->recipients());
                $message->subject($subject);
            });

            if ($mode === 'opened') {
                $incident->update([
                    'opened_notification_sent_at' => now(),
                    'notification_last_error' => null,
                ]);
            }

            if ($mode === 'resolved') {
                $incident->update([
                    'resolved_notification_sent_at' => now(),
                    'notification_last_error' => null,
                ]);
            }
        } catch (Throwable $e) {
            $incident->update([
                'notification_last_error' => $e->getMessage(),
            ]);

            Log::error('Placowka Online email notification failed', [
                'incident_id' => $incident->id,
                'mode' => $mode,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function subject(string $prefix, Incident $incident): string
    {
        return sprintf(
            '[Placówka Online] %s — %s — %s',
            $prefix,
            $incident->facility?->code ?? 'PLACÓWKA',
            $this->typeLabel($incident->type)
        );
    }

    private function buildOpenedBody(Incident $incident): string
    {
        return implode(PHP_EOL, [
            'Placówka Online — wykryto awarię',
            '',
            'Placówka: ' . ($incident->facility?->code ?? '-') . ' — ' . ($incident->facility?->name ?? '-'),
            'Urządzenie: ' . ($incident->device?->name ?? '-'),
            'Typ awarii: ' . $this->typeLabel($incident->type),
            'Status: ' . $incident->statusLabel(),
            'Priorytet: ' . $incident->priorityLabel(),
            'Rozpoczęcie: ' . $this->formatDate($incident->started_at),
            'Ostatni raport agenta: ' . $this->formatDate($incident->last_seen_at),
            '',
            'Opis:',
            $incident->summary ?: '-',
            '',
            'Panel:',
            url('/'),
            '',
            'Wiadomość została wysłana automatycznie przez Placówka Online.',
        ]);
    }

    private function buildResolvedBody(Incident $incident): string
    {
        return implode(PHP_EOL, [
            'Placówka Online — awaria zakończona',
            '',
            'Placówka: ' . ($incident->facility?->code ?? '-') . ' — ' . ($incident->facility?->name ?? '-'),
            'Urządzenie: ' . ($incident->device?->name ?? '-'),
            'Typ awarii: ' . $this->typeLabel($incident->type),
            'Status: ' . $incident->statusLabel(),
            'Priorytet: ' . $incident->priorityLabel(),
            'Rozpoczęcie: ' . $this->formatDate($incident->started_at),
            'Zakończenie: ' . $this->formatDate($incident->ended_at),
            'Czas trwania: ' . $this->formatDuration($incident->duration_seconds),
            '',
            'Opis:',
            $incident->summary ?: '-',
            '',
            'Panel:',
            url('/'),
            '',
            'Wiadomość została wysłana automatycznie przez Placówka Online.',
        ]);
    }

    private function typeLabel(?string $type): string
    {
        return match ($type) {
            'no_communication' => 'brak komunikacji z agentem',
            'gateway_problem' => 'brak komunikacji z bramą lub routerem',
            'dns_problem' => 'problem z DNS',
            'internet_problem' => 'problem z Internetem',
            'monitoring_server_problem' => 'brak dostępu do serwera monitoringu',
            'windows_service_problem' => 'problem z usługą Windows',
            'smart_disk_problem', 'smart_failure' => 'krytyczny problem SMART dysku',
            'windows_update_attention' => 'Windows Update wymaga uwagi',
            'defender_problem' => 'Microsoft Defender wymaga uwagi',
            default => $type ? str_replace('_', ' ', $type) : 'nieznany typ awarii',
        };
    }

    private function formatDate($date): string
    {
        if (! $date) {
            return '-';
        }

        return $date->timezone('Europe/Warsaw')->format('Y-m-d H:i:s');
    }

    private function formatDuration(?int $seconds): string
    {
        if ($seconds === null) {
            return '-';
        }

        $minutes = intdiv($seconds, 60);
        $restSeconds = $seconds % 60;

        if ($minutes < 1) {
            return $restSeconds . ' sek.';
        }

        return $minutes . ' min ' . $restSeconds . ' sek.';
    }
}
