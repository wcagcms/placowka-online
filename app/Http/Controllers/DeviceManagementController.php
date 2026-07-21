<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Services\AgentPackageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class DeviceManagementController extends Controller
{
    public function __construct(
        private readonly AgentPackageService $packages
    ) {
    }

    public function edit(Device $device): View
    {
        $device->load(['facility', 'enrollmentCodes' => function ($query): void {
            $query->latest()->limit(8);
        }]);

        $installerPath = storage_path((string) config(
            'placowka.agent_installer_storage_path',
            'app/agent-installer/PlacowkaOnlineSetup.exe'
        ));

        return view('device-edit', [
            'device' => $device,
            'installerAvailable' => is_file($installerPath),
            'installerSha256' => is_file($installerPath) ? hash_file('sha256', $installerPath) : null,
            'legacyPackagesEnabled' => (bool) config('placowka.legacy_agent_packages_enabled', true),
        ]);
    }

    public function update(Request $request, Device $device): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'antivirus_policy' => [
                'required',
                Rule::in(['auto', 'microsoft_defender', 'third_party']),
            ],
            'expected_antivirus_provider' => [
                'nullable',
                'string',
                'max:120',
                'required_if:antivirus_policy,third_party',
            ],
        ]);

        $antivirusPolicy = (string) $validated['antivirus_policy'];

        $device->forceFill([
            'name' => trim($validated['name']),
            'notes' => $validated['notes'] ?? null,
            'antivirus_policy' => $antivirusPolicy,
            'expected_antivirus_provider' => $antivirusPolicy === 'third_party'
                ? trim((string) ($validated['expected_antivirus_provider'] ?? ''))
                : null,
        ])->save();

        return redirect()
            ->route('devices.edit', $device)
            ->with('success', 'Zapisano zmiany urządzenia.');
    }

    public function deactivate(Device $device): RedirectResponse
    {
        $device->forceFill([
            'is_active' => false,
            'status' => 'offline',
        ])->save();

        return redirect()
            ->route('devices.edit', $device)
            ->with('success', 'Urządzenie zostało dezaktywowane.');
    }

    public function activate(Device $device): RedirectResponse
    {
        $device->forceFill([
            'is_active' => true,
            'archived_at' => null,
            'status' => 'unknown',
        ])->save();

        return redirect()
            ->route('devices.edit', $device)
            ->with('success', 'Urządzenie zostało ponownie aktywowane.');
    }

    public function archive(Device $device): RedirectResponse
    {
        $note = 'Zarchiwizowano: '.now('Europe/Warsaw')->format('Y-m-d H:i:s');

        $device->forceFill([
            'is_active' => false,
            'archived_at' => now(),
            'status' => 'offline',
            'notes' => trim(($device->notes ? $device->notes.PHP_EOL.PHP_EOL : '').$note),
        ])->save();

        return redirect()
            ->route('facilities.show', $device->facility_id)
            ->with('success', 'Urządzenie zostało zarchiwizowane.');
    }

    public function regeneratePackage(Device $device): View|RedirectResponse
    {
        if (! config('placowka.legacy_agent_packages_enabled', true)) {
            return redirect()
                ->route('devices.edit', $device)
                ->withErrors(['package' => 'Awaryjne paczki ZIP zostały wyłączone. Użyj stałego instalatora i kodu jednorazowego.']);
        }

        $device->load('facility');

        try {
            $zipName = $this->packages->regenerateForDevice($device);
        } catch (Throwable $exception) {
            return redirect()
                ->route('devices.edit', $device)
                ->withErrors(['package' => $exception->getMessage()]);
        }

        return view('agent-package-created', [
            'facility' => $device->facility,
            'device' => $device,
            'zipName' => $zipName,
        ]);
    }
}
