<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSystemSettingsRequest;
use App\Models\Setting;
use App\Services\SystemSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SystemSettingsController extends Controller
{
    public function __construct(
        private readonly SystemSettingsService $settings
    ) {
    }

    public function edit(): View
    {
        return view('system-settings.edit', [
            'settings' => $this->settings->all(),
        ]);
    }

    public function update(UpdateSystemSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $values = [
            'panel_system_name' => (string) $validated['panel_system_name'],
            'admin_email' => (string) ($validated['admin_email'] ?? ''),
            'email_alerts_enabled' => $validated['email_alerts_enabled'] ? '1' : '0',
            'default_missing_after_minutes' => (string) $validated['default_missing_after_minutes'],
            'default_alert_after_minutes' => (string) $validated['default_alert_after_minutes'],
            'heartbeat_retention_days' => (string) $validated['heartbeat_retention_days'],
            'default_check_interval_seconds' => (string) $validated['default_check_interval_seconds'],
        ];

        foreach ($values as $key => $value) {
            Setting::query()
                ->where('key', $key)
                ->update(['value' => $value]);
        }

        $this->settings->forgetCache();

        return redirect()
            ->route('system-settings.edit')
            ->with('success', 'Ustawienia systemu zostały zapisane.');
    }
}
