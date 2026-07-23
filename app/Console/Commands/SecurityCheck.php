<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class SecurityCheck extends Command
{
    protected $signature = 'placowka:security-check {--strict : Traktuj ostrzeżenia jako błędy}';

    protected $description = 'Sprawdza najważniejsze ustawienia bezpieczeństwa Placówki Online.';

    /** @var array<int, string> */
    private array $errors = [];

    /** @var array<int, string> */
    private array $warnings = [];

    public function handle(): int
    {
        $this->components->info('Kontrola bezpieczeństwa Placówki Online');

        $isProduction = app()->environment('production');
        $appUrl = (string) config('app.url');
        $appHost = parse_url($appUrl, PHP_URL_HOST);
        $appKey = (string) config('app.key');
        $trustedHosts = (array) config('security.trusted_hosts', []);

        $this->check(
            ! $isProduction || config('app.debug') === false,
            'APP_DEBUG jest wyłączony w produkcji.',
            'APP_DEBUG musi mieć wartość false w produkcji.'
        );

        $this->check(
            ! $isProduction || Str::startsWith($appUrl, 'https://'),
            'APP_URL używa HTTPS.',
            'APP_URL w produkcji musi zaczynać się od https://.'
        );

        $this->check(
            $appKey !== '' && ! Str::contains($appKey, ['changeme', 'example']),
            'APP_KEY jest ustawiony.',
            'APP_KEY jest pusty albo wygląda jak wartość przykładowa.'
        );

        $this->check(
            in_array(config('session.driver'), ['database', 'redis'], true),
            'Sesje używają sterownika database lub redis.',
            'Zalecany sterownik sesji to database albo redis.',
            warning: true
        );

        $this->check(
            ! $isProduction || config('session.encrypt') === true,
            'Dane sesji są szyfrowane.',
            'SESSION_ENCRYPT musi mieć wartość true w produkcji.'
        );

        $this->check(
            ! $isProduction || config('session.secure') === true,
            'Cookie sesji jest wysyłane wyłącznie przez HTTPS.',
            'SESSION_SECURE_COOKIE musi mieć wartość true w produkcji.'
        );

        $this->check(
            config('session.http_only') === true,
            'Cookie sesji ma flagę HttpOnly.',
            'SESSION_HTTP_ONLY nie może być wyłączone.'
        );

        $this->check(
            in_array(config('session.same_site'), ['strict', 'lax'], true),
            'Cookie sesji ma bezpieczną politykę SameSite.',
            'SESSION_SAME_SITE powinno mieć wartość strict albo lax.'
        );

        $this->check(
            ! $isProduction || (int) config('session.lifetime') <= 60,
            'Czas bezczynności sesji nie przekracza 60 minut.',
            'SESSION_LIFETIME w produkcji powinno wynosić maksymalnie 60 minut.',
            warning: true
        );

        $this->check(
            ! $isProduction || config('session.expire_on_close') === true,
            'Sesja wygasa po zamknięciu przeglądarki.',
            'Zalecane jest SESSION_EXPIRE_ON_CLOSE=true w produkcji.',
            warning: true
        );

        $this->check(
            $trustedHosts !== [],
            'Lista zaufanych hostów nie jest pusta.',
            'APP_TRUSTED_HOSTS nie może być puste.'
        );

        if (is_string($appHost) && $appHost !== '') {
            $hostMatches = collect($trustedHosts)->contains(
                static fn (mixed $pattern): bool => is_string($pattern)
                    && @preg_match('/'.$pattern.'/i', $appHost) === 1
            );

            $this->check(
                $hostMatches,
                'Host APP_URL znajduje się na liście zaufanych hostów.',
                'Żaden wzorzec APP_TRUSTED_HOSTS nie pasuje do hosta APP_URL.'
            );
        }

        $contentSecurityPolicy = (string) config('security.content_security_policy');
        $requiredCspDirectives = [
            "default-src 'self'",
            "script-src 'self'",
            "object-src 'none'",
            "frame-ancestors 'none'",
        ];

        $this->check(
            collect($requiredCspDirectives)->every(
                static fn (string $directive): bool => Str::contains(
                    $contentSecurityPolicy,
                    $directive
                )
            ),
            'Content Security Policy zawiera wymagane dyrektywy.',
            'Content Security Policy nie zawiera wszystkich wymaganych dyrektyw.'
        );

        if ((bool) config('placowka.email_alerts_enabled')) {
            $this->check(
                trim((string) config('placowka.alert_email_to')) !== '',
                'Adres powiadomień jest ustawiony.',
                'Alerty e-mail są włączone, ale PLACOWKA_ALERT_EMAIL_TO jest puste.'
            );
        }

        $this->newLine();
        $this->line('Błędy: '.count($this->errors).', ostrzeżenia: '.count($this->warnings));

        if ($this->errors !== [] || ($this->option('strict') && $this->warnings !== [])) {
            $this->components->error('Kontrola bezpieczeństwa nie została zaliczona.');

            return SymfonyCommand::FAILURE;
        }

        $this->components->info('Kontrola bezpieczeństwa zakończona poprawnie.');

        return SymfonyCommand::SUCCESS;
    }

    private function check(
        bool $condition,
        string $success,
        string $failure,
        bool $warning = false
    ): void {
        if ($condition) {
            $this->components->twoColumnDetail($success, '<fg=green>OK</>');

            return;
        }

        if ($warning) {
            $this->warnings[] = $failure;
            $this->components->twoColumnDetail($failure, '<fg=yellow>OSTRZEŻENIE</>');

            return;
        }

        $this->errors[] = $failure;
        $this->components->twoColumnDetail($failure, '<fg=red>BŁĄD</>');
    }
}
