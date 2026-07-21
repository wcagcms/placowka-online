<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('heartbeat', function (Request $request): array {
            $uuid = (string) $request->route('uuid');
            $ip = (string) $request->ip();
            $response = fn () => response()->json([
                'ok' => false,
                'message' => 'Too many heartbeat requests.',
            ], 429);

            return [
                Limit::perMinute(10)
                    ->by('heartbeat-device:'.hash('sha256', $uuid))
                    ->response($response),
                Limit::perMinute(300)
                    ->by('heartbeat-ip:'.hash('sha256', $ip))
                    ->response($response),
            ];
        });


        RateLimiter::for('agent-enrollment-start', function (Request $request): array {
            $ip = hash('sha256', (string) $request->ip());
            $response = fn () => response()->json([
                'ok' => false,
                'message' => 'Zbyt wiele prób rejestracji. Spróbuj ponownie później.',
            ], 429);

            return [
                Limit::perMinutes(15, 5)
                    ->by('agent-enrollment-start-ip:'.$ip)
                    ->response($response),
            ];
        });

        RateLimiter::for('agent-enrollment-complete', function (Request $request): array {
            $ip = hash('sha256', (string) $request->ip());
            $enrollmentId = hash('sha256', (string) $request->input('enrollment_id'));
            $response = fn () => response()->json([
                'ok' => false,
                'message' => 'Zbyt wiele prób zakończenia rejestracji. Spróbuj ponownie później.',
            ], 429);

            return [
                Limit::perMinutes(15, 20)
                    ->by('agent-enrollment-complete-ip:'.$ip)
                    ->response($response),
                Limit::perMinutes(15, 8)
                    ->by('agent-enrollment-complete-session:'.$enrollmentId)
                    ->response($response),
            ];
        });
    }
}
