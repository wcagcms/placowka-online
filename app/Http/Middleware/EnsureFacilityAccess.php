<?php

namespace App\Http\Middleware;

use App\Models\Device;
use App\Models\Facility;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFacilityAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user && $user->is_active, 403);

        if ($user->isAdmin()) {
            return $next($request);
        }

        $facility = $this->resolveFacility($request);

        abort_unless(
            $facility && $user->canAccessFacility($facility),
            404
        );

        return $next($request);
    }

    private function resolveFacility(Request $request): ?Facility
    {
        $facility = $request->route('facility');

        if ($facility instanceof Facility) {
            return $facility;
        }

        if (is_numeric($facility)) {
            return Facility::query()->find((int) $facility);
        }

        $device = $request->route('device');

        if ($device instanceof Device) {
            return $device->relationLoaded('facility')
                ? $device->facility
                : $device->facility()->first();
        }

        if (is_numeric($device)) {
            return Device::query()->with('facility')->find((int) $device)?->facility;
        }

        return null;
    }
}
