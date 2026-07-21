<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSystemSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Dostęp do trasy powinien być dodatkowo chroniony istniejącym
        // middleware sesji panelu administratora.
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'panel_system_name' => ['required', 'string', 'max:120'],
            'admin_email' => ['nullable', 'email:rfc', 'max:190'],
            'email_alerts_enabled' => ['nullable', 'boolean'],
            'default_missing_after_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'default_alert_after_minutes' => ['required', 'integer', 'min:0', 'max:10080'],
            'heartbeat_retention_days' => ['required', 'integer', 'min:7', 'max:3650'],
            'default_check_interval_seconds' => ['required', 'integer', 'min:15', 'max:3600'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'panel_system_name.required' => 'Podaj nazwę systemu.',
            'admin_email.email' => 'Podaj poprawny adres e-mail administratora.',
            'default_missing_after_minutes.min' => 'Czas braku komunikacji musi wynosić co najmniej 1 minutę.',
            'default_alert_after_minutes.min' => 'Opóźnienie alertu nie może być ujemne.',
            'heartbeat_retention_days.min' => 'Historia heartbeatów musi być przechowywana przez co najmniej 7 dni.',
            'default_check_interval_seconds.min' => 'Interwał agenta nie może być krótszy niż 15 sekund.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email_alerts_enabled' => $this->boolean('email_alerts_enabled'),
        ]);
    }
}
