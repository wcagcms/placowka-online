<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Models\User;
use App\Services\IncidentNotificationService;
use App\Services\SecurityAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IncidentController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user, 403);

        $validated = $request->validate([
            'status' => ['nullable', 'string', Rule::in(array_merge(Incident::ACTIVE_STATUSES, [
                Incident::STATUS_RESOLVED,
                Incident::STATUS_CLOSED,
                'active',
            ]))],
            'priority' => ['nullable', 'string', Rule::in(Incident::PRIORITIES)],
            'type' => ['nullable', 'string', 'max:50'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $query = Incident::query()
            ->visibleTo($user)
            ->with(['facility', 'device', 'assignedUser'])
            ->latest('started_at');

        if (($validated['status'] ?? null) === 'active') {
            $query->active();
        } elseif (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        } else {
            $query->active();
        }

        if (! empty($validated['priority'])) {
            $query->where('priority', $validated['priority']);
        }

        if (! empty($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        if (! empty($validated['q'])) {
            $search = trim($validated['q']);
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('summary', 'like', '%'.$search.'%')
                    ->orWhereHas('facility', fn ($facility) => $facility
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('code', 'like', '%'.$search.'%'))
                    ->orWhereHas('device', fn ($device) => $device
                        ->where('name', 'like', '%'.$search.'%'));
            });
        }

        $incidents = $query->paginate(30)->withQueryString();

        $types = Incident::query()
            ->visibleTo($user)
            ->whereNotNull('type')
            ->distinct()
            ->orderBy('type')
            ->pluck('type');

        $counts = [
            'active' => Incident::query()->visibleTo($user)->active()->count(),
            'critical' => Incident::query()->visibleTo($user)->active()->where('priority', 'critical')->count(),
            'assigned_to_me' => Incident::query()->visibleTo($user)->active()->where('assigned_user_id', $user->id)->count(),
            'resolved_30d' => Incident::query()->visibleTo($user)->where('status', Incident::STATUS_RESOLVED)->where('ended_at', '>=', now()->subDays(30))->count(),
        ];

        return view('incidents.index', compact('incidents', 'types', 'counts'));
    }

    public function show(Request $request, Incident $incident): View
    {
        $this->authorizeIncident($request, $incident);

        $incident->load([
            'facility',
            'device',
            'assignedUser',
            'acknowledgedBy',
            'resolvedBy',
            'closedBy',
            'comments.user',
        ]);

        $operators = $this->availableOperators($request, $incident);

        return view('incidents.show', compact('incident', 'operators'));
    }

    public function acknowledge(
        Request $request,
        Incident $incident,
        SecurityAuditLogger $audit
    ): RedirectResponse {
        $this->authorizeIncident($request, $incident);

        if (! $incident->isActive()) {
            return back()->withErrors(['incident' => 'Tylko aktywny incydent można potwierdzić.']);
        }

        $incident->forceFill([
            'status' => Incident::STATUS_ACKNOWLEDGED,
            'acknowledged_by_user_id' => $request->user()->id,
            'acknowledged_at' => now(),
            'last_status_change_at' => now(),
        ])->save();

        $audit->write('incident.acknowledged', $request->user(), $incident, [
            'incident_id' => $incident->id,
            'facility_id' => $incident->facility_id,
            'status' => $incident->status,
        ], $request);

        return back()->with('success', 'Incydent został potwierdzony.');
    }

    public function startProgress(
        Request $request,
        Incident $incident,
        SecurityAuditLogger $audit
    ): RedirectResponse {
        $this->authorizeIncident($request, $incident);

        if (! $incident->isActive()) {
            return back()->withErrors(['incident' => 'Tylko aktywny incydent można oznaczyć jako „W trakcie”.']);
        }

        $incident->forceFill([
            'status' => Incident::STATUS_IN_PROGRESS,
            'acknowledged_by_user_id' => $incident->acknowledged_by_user_id ?: $request->user()->id,
            'acknowledged_at' => $incident->acknowledged_at ?: now(),
            'assigned_user_id' => $incident->assigned_user_id ?: $request->user()->id,
            'last_status_change_at' => now(),
        ])->save();

        $audit->write('incident.in_progress', $request->user(), $incident, [
            'incident_id' => $incident->id,
            'facility_id' => $incident->facility_id,
            'status' => $incident->status,
        ], $request);

        return back()->with('success', 'Incydent ma teraz status „W trakcie”.');
    }

    public function assign(
        Request $request,
        Incident $incident,
        SecurityAuditLogger $audit
    ): RedirectResponse {
        $this->authorizeIncident($request, $incident);

        $validated = $request->validate([
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'priority' => ['required', 'string', Rule::in(Incident::PRIORITIES)],
        ]);

        $assignedId = $validated['assigned_user_id'] ?? null;

        if ($assignedId !== null) {
            $allowed = $this->availableOperators($request, $incident)->contains('id', (int) $assignedId);
            abort_unless($allowed, 403, 'Wybrany operator nie ma dostępu do tej placówki.');
        }

        $incident->forceFill([
            'assigned_user_id' => $assignedId,
            'priority' => $validated['priority'],
        ])->save();

        $audit->write('incident.assigned', $request->user(), $incident, [
            'incident_id' => $incident->id,
            'facility_id' => $incident->facility_id,
            'operator_id' => $assignedId,
            'priority' => $incident->priority,
        ], $request);

        return back()->with('success', 'Przypisanie i priorytet zostały zapisane.');
    }

    public function comment(
        Request $request,
        Incident $incident,
        SecurityAuditLogger $audit
    ): RedirectResponse {
        $this->authorizeIncident($request, $incident);

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:4000'],
        ]);

        $incident->comments()->create([
            'user_id' => $request->user()->id,
            'body' => trim($validated['body']),
        ]);

        $audit->write('incident.comment_added', $request->user(), $incident, [
            'incident_id' => $incident->id,
            'facility_id' => $incident->facility_id,
        ], $request);

        return back()->with('success', 'Notatka została dodana.');
    }

    public function resolve(
        Request $request,
        Incident $incident,
        IncidentNotificationService $notifications,
        SecurityAuditLogger $audit
    ): RedirectResponse {
        $this->authorizeIncident($request, $incident);

        $validated = $request->validate([
            'resolution_note' => ['required', 'string', 'min:3', 'max:4000'],
        ]);

        if (! $incident->isActive()) {
            return back()->withErrors(['incident' => 'Incydent nie jest już aktywny.']);
        }

        $endedAt = now();
        $incident->forceFill([
            'status' => Incident::STATUS_RESOLVED,
            'ended_at' => $endedAt,
            'duration_seconds' => max(0, $incident->started_at->diffInSeconds($endedAt)),
            'resolved_by_user_id' => $request->user()->id,
            'resolution_note' => trim($validated['resolution_note']),
            'last_status_change_at' => $endedAt,
        ])->save();

        $notifications->sendResolved($incident->fresh(['facility', 'device']));

        $audit->write('incident.resolved_manually', $request->user(), $incident, [
            'incident_id' => $incident->id,
            'facility_id' => $incident->facility_id,
            'status' => $incident->status,
        ], $request);

        return back()->with('success', 'Incydent został oznaczony jako rozwiązany.');
    }

    public function close(
        Request $request,
        Incident $incident,
        SecurityAuditLogger $audit
    ): RedirectResponse {
        $this->authorizeIncident($request, $incident);

        if ($incident->status !== Incident::STATUS_RESOLVED) {
            return back()->withErrors(['incident' => 'Zamknąć można tylko rozwiązany incydent.']);
        }

        $incident->forceFill([
            'status' => Incident::STATUS_CLOSED,
            'closed_at' => now(),
            'closed_by_user_id' => $request->user()->id,
            'last_status_change_at' => now(),
        ])->save();

        $audit->write('incident.closed', $request->user(), $incident, [
            'incident_id' => $incident->id,
            'facility_id' => $incident->facility_id,
            'status' => $incident->status,
        ], $request);

        return back()->with('success', 'Incydent został zamknięty.');
    }

    private function authorizeIncident(Request $request, Incident $incident): void
    {
        $user = $request->user();
        abort_unless($user && $user->is_active && $user->canAccessFacility($incident->facility_id), 403);
    }

    private function availableOperators(Request $request, Incident $incident)
    {
        $user = $request->user();

        if (! $user->isAdmin()) {
            return User::query()->whereKey($user->id)->where('is_active', true)->get();
        }

        return User::query()
            ->where('is_active', true)
            ->where(function ($query) use ($incident): void {
                $query
                    ->where('role', User::ROLE_ADMIN)
                    ->orWhereHas('facilities', fn ($facilities) => $facilities->whereKey($incident->facility_id));
            })
            ->orderBy('name')
            ->get();
    }
}
