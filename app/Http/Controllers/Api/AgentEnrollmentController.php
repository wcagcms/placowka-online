<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\AgentEnrollmentException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CompleteAgentEnrollmentRequest;
use App\Http\Requests\Api\StartAgentEnrollmentRequest;
use App\Models\AgentEnrollmentSession;
use App\Services\AgentEnrollmentService;
use App\Services\SecurityAuditLogger;
use Illuminate\Http\JsonResponse;
use Throwable;

class AgentEnrollmentController extends Controller
{
    public function start(
        StartAgentEnrollmentRequest $request,
        AgentEnrollmentService $enrollment,
        SecurityAuditLogger $audit
    ): JsonResponse {
        if (! $request->isJson() || strlen($request->getContent()) > 16384) {
            return $this->noStore(response()->json([
                'ok' => false,
                'message' => 'Nieprawidłowe żądanie rejestracji.',
            ], strlen($request->getContent()) > 16384 ? 413 : 415));
        }

        $data = $request->validated();

        try {
            $result = $enrollment->start($data, $request->ip());

            $session = AgentEnrollmentSession::query()
                ->with('device')
                ->where('public_id', $result['enrollment_id'])
                ->first();

            $audit->write('agent_enrollment_started', null, $session?->device, [
                'device_id' => $session?->device_id,
                'facility_id' => $session?->device?->facility_id,
                'enrollment_id' => $result['enrollment_id'],
                'machine_name' => $data['machine_name'],
                'setup_version' => $data['setup_version'] ?? null,
            ], $request);

            return $this->noStore(response()->json($result, 201));
        } catch (AgentEnrollmentException $exception) {
            $audit->write('agent_enrollment_rejected', null, null, [
                'machine_name' => $data['machine_name'] ?? null,
                'setup_version' => $data['setup_version'] ?? null,
                'reason' => 'invalid_or_expired_code',
            ], $request);

            return $this->noStore(response()->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], $exception->httpStatus));
        } catch (Throwable $exception) {
            report($exception);

            return $this->noStore(response()->json([
                'ok' => false,
                'message' => 'Nie udało się rozpocząć rejestracji. Spróbuj ponownie później.',
            ], 500));
        }
    }

    public function complete(
        CompleteAgentEnrollmentRequest $request,
        AgentEnrollmentService $enrollment,
        SecurityAuditLogger $audit
    ): JsonResponse {
        if (! $request->isJson() || strlen($request->getContent()) > 16384) {
            return $this->noStore(response()->json([
                'ok' => false,
                'message' => 'Nieprawidłowe żądanie rejestracji.',
            ], strlen($request->getContent()) > 16384 ? 413 : 415));
        }

        $data = $request->validated();

        try {
            $result = $enrollment->complete($data, $request->ip());
            $session = AgentEnrollmentSession::query()
                ->with('device')
                ->where('public_id', $data['enrollment_id'])
                ->first();

            $audit->write('agent_enrollment_completed', null, $session?->device, [
                'device_id' => $session?->device_id,
                'facility_id' => $session?->device?->facility_id,
                'enrollment_id' => $data['enrollment_id'],
                'machine_name' => $data['machine_name'],
                'setup_version' => $data['setup_version'] ?? null,
            ], $request);

            return $this->noStore(response()->json($result));
        } catch (AgentEnrollmentException $exception) {
            $audit->write('agent_enrollment_completion_failed', null, null, [
                'enrollment_id' => $data['enrollment_id'] ?? null,
                'machine_name' => $data['machine_name'] ?? null,
                'reason' => 'invalid_or_expired_session',
            ], $request);

            return $this->noStore(response()->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], $exception->httpStatus));
        } catch (Throwable $exception) {
            report($exception);

            return $this->noStore(response()->json([
                'ok' => false,
                'message' => 'Nie udało się zakończyć rejestracji. Instalator może ponowić operację.',
            ], 500));
        }
    }

    private function noStore(JsonResponse $response): JsonResponse
    {
        $response->headers->set('Cache-Control', 'no-store, private, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
