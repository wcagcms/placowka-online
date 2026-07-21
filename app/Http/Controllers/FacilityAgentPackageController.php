<?php

namespace App\Http\Controllers;

use App\Models\Facility;
use App\Services\AgentPackageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class FacilityAgentPackageController extends Controller
{
    public function __construct(
        private readonly AgentPackageService $packages
    ) {
    }

    public function create(): View
    {
        return view('facilities-create');
    }

    public function store(Request $request): View|RedirectResponse
    {
        $validated = $request->validate([
            'facility_code' => ['required', 'string', 'max:50'],
            'facility_name' => ['required', 'string', 'max:255'],
            'device_name' => ['required', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email:rfc', 'max:255'],
        ]);

        $facilityCode = mb_strtoupper(trim($validated['facility_code']));
        $facilityName = trim($validated['facility_name']);
        $deviceName = trim($validated['device_name']);
        $email = $validated['contact_email'] ?? null;

        $facility = Facility::query()->firstOrCreate(
            ['code' => $facilityCode],
            [
                'name' => $facilityName,
                'contact_email' => $email,
                'is_active' => true,
            ]
        );

        $facility->forceFill([
            'name' => $facilityName,
            'contact_email' => $email ?: $facility->contact_email,
            'is_active' => true,
        ])->save();

        try {
            if (config('placowka.agent_enrollment_enabled', true)) {
                $device = $this->packages->createUnenrolledDevice($facility, $deviceName);

                return redirect()
                    ->route('devices.edit', $device)
                    ->with('success', 'Placówka i pierwsze urządzenie zostały utworzone. Wygeneruj jednorazowy kod instalacyjny.');
            }

            [$device, $zipName] = $this->packages->createForNewDevice($facility, $deviceName);
        } catch (Throwable $exception) {
            return back()
                ->withInput()
                ->withErrors(['package' => $exception->getMessage()]);
        }

        return view('agent-package-created', [
            'facility' => $facility,
            'device' => $device,
            'zipName' => $zipName,
        ]);
    }

    public function createDevice(Facility $facility): View
    {
        return view('facilities-device-create', [
            'facility' => $facility,
        ]);
    }

    public function storeDevice(Request $request, Facility $facility): View|RedirectResponse
    {
        $validated = $request->validate([
            'device_name' => ['required', 'string', 'max:255'],
        ]);

        try {
            if (config('placowka.agent_enrollment_enabled', true)) {
                $device = $this->packages->createUnenrolledDevice(
                    $facility,
                    trim($validated['device_name'])
                );

                return redirect()
                    ->route('devices.edit', $device)
                    ->with('success', 'Urządzenie zostało utworzone. Wygeneruj jednorazowy kod instalacyjny.');
            }

            [$device, $zipName] = $this->packages->createForNewDevice(
                $facility,
                trim($validated['device_name'])
            );
        } catch (Throwable $exception) {
            return back()
                ->withInput()
                ->withErrors(['package' => $exception->getMessage()]);
        }

        return view('agent-package-created', [
            'facility' => $facility,
            'device' => $device,
            'zipName' => $zipName,
        ]);
    }

    public function download(string $zipName): BinaryFileResponse
    {
        abort_unless(config('placowka.legacy_agent_packages_enabled', true), 404);

        abort_unless(
            preg_match('/^[a-zA-Z0-9._-]+\.zip$/', $zipName) === 1,
            404
        );

        $path = storage_path('app/agent-packages/'.$zipName);

        abort_unless(is_file($path), 404);

        return response()
            ->download($path, $zipName, [
                'Cache-Control' => 'no-store, private',
                'X-Content-Type-Options' => 'nosniff',
            ])
            ->deleteFileAfterSend(true);
    }
}
