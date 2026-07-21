<?php

namespace App\Http\Controllers;

use App\Exceptions\AgentEnrollmentException;
use App\Models\AgentEnrollmentCode;
use App\Models\Device;
use App\Services\AgentEnrollmentService;
use App\Services\SecurityAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AgentEnrollmentCodeController extends Controller
{
    public function store(
        Request $request,
        Device $device,
        AgentEnrollmentService $enrollment,
        SecurityAuditLogger $audit
    ): RedirectResponse {
        $device->loadMissing('facility');

        try {
            $created = $enrollment->createCode($device, $request->user(), $request->ip());
        } catch (AgentEnrollmentException $exception) {
            return back()->withErrors(['enrollment' => $exception->getMessage()]);
        }

        $audit->write('agent_enrollment_code_created', $request->user(), $device, [
            'device_id' => $device->getKey(),
            'facility_id' => $device->facility_id,
            'expires_at' => $created['code']->expires_at?->toIso8601String(),
        ], $request);

        return redirect()
            ->route('devices.edit', $device)
            ->with('success', 'Utworzono jednorazowy kod instalacyjny.')
            ->with('agent_enrollment_plain_code', $created['plain_code'])
            ->with('agent_enrollment_expires_at', $created['code']->expires_at?->toIso8601String())
            ->with('agent_enrollment_device_id', $device->getKey());
    }

    public function revoke(
        Request $request,
        Device $device,
        AgentEnrollmentCode $enrollmentCode,
        AgentEnrollmentService $enrollment,
        SecurityAuditLogger $audit
    ): RedirectResponse {
        abort_unless($enrollmentCode->device_id === $device->getKey(), 404);

        try {
            $enrollment->revokeCode($enrollmentCode);
        } catch (AgentEnrollmentException $exception) {
            return back()->withErrors(['enrollment' => $exception->getMessage()]);
        }

        $audit->write('agent_enrollment_code_revoked', $request->user(), $device, [
            'device_id' => $device->getKey(),
            'facility_id' => $device->facility_id,
        ], $request);

        return back()->with('success', 'Kod instalacyjny został unieważniony.');
    }

    public function downloadSetup(Request $request, SecurityAuditLogger $audit): BinaryFileResponse|RedirectResponse
    {
        $path = storage_path((string) config(
            'placowka.agent_installer_storage_path',
            'app/agent-installer/PlacowkaOnlineSetup.exe'
        ));

        if (! is_file($path)) {
            return back()->withErrors([
                'enrollment' => 'Stały instalator nie został jeszcze opublikowany na serwerze.',
            ]);
        }

        $audit->write('agent_installer_downloaded', $request->user(), null, [
            'setup_version' => config('placowka.agent_setup_version', '1.0.2'),
        ], $request);

        return response()->download($path, 'PlacowkaOnlineSetup.exe', [
            'Content-Type' => 'application/vnd.microsoft.portable-executable',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }
}
