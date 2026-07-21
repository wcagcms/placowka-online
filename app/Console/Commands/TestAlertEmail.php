<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestAlertEmail extends Command
{
    protected $signature = 'placowka:test-email {--to=}';

    protected $description = 'Wysyła testową wiadomość e-mail z Placówka Online.';

    public function handle(): int
    {
        $to = $this->option('to') ?: config('placowka.alert_email_to');

        if (! $to) {
            $this->error('Brak adresu e-mail. Ustaw PLACOWKA_ALERT_EMAIL_TO w .env albo użyj --to=');
            return self::FAILURE;
        }

        $subject = '[Placówka Online] Test powiadomień e-mail';

        $body = implode(PHP_EOL, [
            'To jest testowa wiadomość z systemu Placówka Online.',
            '',
            'Jeżeli widzisz tę wiadomość, konfiguracja powiadomień działa.',
            '',
            'Czas serwera: ' . now()->timezone('Europe/Warsaw')->format('Y-m-d H:i:s'),
            'Adres aplikacji: ' . config('app.url'),
        ]);

        Mail::raw($body, function ($message) use ($to, $subject) {
            $recipients = collect(preg_split('/[,;]/', (string) $to))
                ->map(fn ($email) => trim((string) $email))
                ->filter()
                ->values()
                ->all();

            $message->to($recipients);
            $message->subject($subject);
        });

        $this->info('Wysłano test e-mail do: ' . $to);

        return self::SUCCESS;
    }
}
