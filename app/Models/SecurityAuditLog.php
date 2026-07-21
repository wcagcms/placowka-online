<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityAuditLog extends Model
{
    private const EVENT_META = [
        'login_success' => [
            'label' => 'Pomyślne logowanie',
            'description' => 'Użytkownik poprawnie uwierzytelnił się w panelu.',
            'tone' => 'success',
            'icon' => 'health',
        ],
        'login_failed' => [
            'label' => 'Nieudane logowanie',
            'description' => 'Podano nieprawidłowe dane logowania.',
            'tone' => 'danger',
            'icon' => 'alarm',
        ],
        'login_blocked' => [
            'label' => 'Logowanie zablokowane',
            'description' => 'Próba logowania została zatrzymana przez ograniczenie bezpieczeństwa.',
            'tone' => 'danger',
            'icon' => 'alarm',
        ],
        'logout' => [
            'label' => 'Wylogowanie',
            'description' => 'Użytkownik zakończył aktywną sesję panelu.',
            'tone' => 'neutral',
            'icon' => 'history',
        ],
        'operator_created' => [
            'label' => 'Utworzono operatora',
            'description' => 'Administrator utworzył nowe konto operatora.',
            'tone' => 'info',
            'icon' => 'users',
        ],
        'operator_updated' => [
            'label' => 'Zmieniono operatora',
            'description' => 'Administrator zmienił dane konta lub zakres przypisanych placówek.',
            'tone' => 'warning',
            'icon' => 'users',
        ],
        'password_changed' => [
            'label' => 'Zmieniono hasło',
            'description' => 'Użytkownik ustawił nowe hasło do panelu.',
            'tone' => 'info',
            'icon' => 'settings',
        ],
        'agent_enrollment_code_created' => [
            'label' => 'Utworzono kod instalacyjny',
            'description' => 'Administrator utworzył jednorazowy kod rejestracji agenta.',
            'tone' => 'info',
            'icon' => 'device',
        ],
        'agent_enrollment_code_revoked' => [
            'label' => 'Unieważniono kod instalacyjny',
            'description' => 'Administrator wycofał niewykorzystany kod rejestracji agenta.',
            'tone' => 'warning',
            'icon' => 'device',
        ],
        'agent_enrollment_started' => [
            'label' => 'Rozpoczęto rejestrację agenta',
            'description' => 'Instalator poprawnie wykorzystał jednorazowy kod i rozpoczął bezpieczną sesję.',
            'tone' => 'info',
            'icon' => 'device',
        ],
        'agent_enrollment_completed' => [
            'label' => 'Zakończono rejestrację agenta',
            'description' => 'Instalator otrzymał konfigurację urządzenia i zakończył drugi etap rejestracji.',
            'tone' => 'success',
            'icon' => 'health',
        ],
        'agent_enrollment_rejected' => [
            'label' => 'Odrzucono rejestrację agenta',
            'description' => 'Kod był nieprawidłowy, wygasły albo został już wykorzystany.',
            'tone' => 'danger',
            'icon' => 'alarm',
        ],
        'agent_enrollment_completion_failed' => [
            'label' => 'Nie zakończono rejestracji',
            'description' => 'Drugi etap rejestracji został odrzucony lub sesja wygasła.',
            'tone' => 'danger',
            'icon' => 'alarm',
        ],
        'agent_installer_downloaded' => [
            'label' => 'Pobrano instalator agenta',
            'description' => 'Administrator pobrał stały instalator Placówka Online.',
            'tone' => 'neutral',
            'icon' => 'device',
        ],
    ];

    private const CONTEXT_LABELS = [
        'email' => 'Adres e-mail',
        'operator_id' => 'Identyfikator operatora',
        'operator_email' => 'Konto operatora',
        'facility_ids' => 'Identyfikatory placówek',
        'active' => 'Konto aktywne',
        'role' => 'Rola',
        'reason' => 'Powód',
        'route' => 'Trasa',
        'device_id' => 'Identyfikator urządzenia',
        'facility_id' => 'Identyfikator placówki',
        'enrollment_id' => 'Identyfikator sesji rejestracji',
        'machine_name' => 'Nazwa komputera',
        'setup_version' => 'Wersja instalatora',
        'expires_at' => 'Ważny do',
    ];

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'event',
        'subject_type',
        'subject_id',
        'ip_address',
        'user_agent',
        'context',
        'created_at',
    ];

    protected $casts = [
        'context' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function labelFor(string $event): string
    {
        return self::EVENT_META[$event]['label'] ?? str($event)->replace('_', ' ')->headline()->toString();
    }

    public function eventLabel(): string
    {
        return self::labelFor($this->event);
    }

    public function eventDescription(): string
    {
        return self::EVENT_META[$this->event]['description'] ?? 'Zdarzenie zapisane przez mechanizm bezpieczeństwa systemu.';
    }

    public function eventTone(): string
    {
        return self::EVENT_META[$this->event]['tone'] ?? 'neutral';
    }

    public function eventIcon(): string
    {
        return self::EVENT_META[$this->event]['icon'] ?? 'history';
    }

    public function eventToneLabel(): string
    {
        return match ($this->eventTone()) {
            'success' => 'POPRAWNE',
            'danger' => 'ZAGROŻENIE',
            'warning' => 'ZMIANA',
            'info' => 'INFORMACJA',
            default => 'ZDARZENIE',
        };
    }

    public function actorLabel(): string
    {
        if ($this->user) {
            return $this->user->name;
        }

        $email = data_get($this->context, 'email');

        return is_string($email) && $email !== '' ? $email : 'Niezalogowany użytkownik';
    }

    public function subjectLabel(): string
    {
        if (! $this->subject_type || ! $this->subject_id) {
            return 'Nie dotyczy';
        }

        $type = match (class_basename($this->subject_type)) {
            'User' => 'Konto użytkownika',
            'Facility' => 'Placówka',
            'Device' => 'Urządzenie',
            default => class_basename($this->subject_type),
        };

        return $type.' #'.$this->subject_id;
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    public function contextRows(): array
    {
        $rows = [];

        foreach (($this->context ?? []) as $key => $value) {
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            if (is_bool($value)) {
                $formatted = $value ? 'Tak' : 'Nie';
            } elseif (is_array($value)) {
                $formatted = implode(', ', array_map(static fn (mixed $item): string => (string) $item, $value));
            } else {
                $formatted = (string) $value;
            }

            $rows[] = [
                'label' => self::CONTEXT_LABELS[$key] ?? str((string) $key)->replace('_', ' ')->headline()->toString(),
                'value' => $formatted,
            ];
        }

        return $rows;
    }
}
