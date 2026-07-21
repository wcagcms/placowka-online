<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAgentEnrollmentRequest
{
    private const MAX_BYTES = 16384;

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isJson()) {
            return $this->error('Content-Type application/json jest wymagany.', 415);
        }

        $declaredLength = (int) $request->server('CONTENT_LENGTH', 0);
        if ($declaredLength > self::MAX_BYTES || strlen($request->getContent()) > self::MAX_BYTES) {
            return $this->error('Żądanie rejestracyjne jest zbyt duże.', 413);
        }

        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    private function error(string $message, int $status): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'message' => $message,
        ], $status, [
            'Cache-Control' => 'no-store, private, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
