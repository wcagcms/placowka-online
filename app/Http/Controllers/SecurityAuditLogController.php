<?php

namespace App\Http\Controllers;

use App\Models\SecurityAuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class SecurityAuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'event' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);

        $search = trim((string) ($validated['q'] ?? ''));
        $event = trim((string) ($validated['event'] ?? ''));
        $dateFrom = $validated['date_from'] ?? '';
        $dateTo = $validated['date_to'] ?? '';

        $logs = SecurityAuditLog::query()
            ->with('user:id,name,email')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $pattern = '%'.$search.'%';

                $query->where(function (Builder $searchQuery) use ($pattern): void {
                    $searchQuery
                        ->where('event', 'like', $pattern)
                        ->orWhere('ip_address', 'like', $pattern)
                        ->orWhere('user_agent', 'like', $pattern)
                        ->orWhere('context', 'like', $pattern)
                        ->orWhereHas('user', function (Builder $userQuery) use ($pattern): void {
                            $userQuery
                                ->where('name', 'like', $pattern)
                                ->orWhere('email', 'like', $pattern);
                        });
                });
            })
            ->when($event !== '', fn (Builder $query): Builder => $query->where('event', $event))
            ->when($dateFrom !== '', function (Builder $query) use ($dateFrom): void {
                $query->where('created_at', '>=', Carbon::createFromFormat('Y-m-d', $dateFrom)->startOfDay());
            })
            ->when($dateTo !== '', function (Builder $query) use ($dateTo): void {
                $query->where('created_at', '<=', Carbon::createFromFormat('Y-m-d', $dateTo)->endOfDay());
            })
            ->latest('created_at')
            ->paginate(30)
            ->withQueryString();

        $eventOptions = SecurityAuditLog::query()
            ->select('event')
            ->distinct()
            ->orderBy('event')
            ->pluck('event')
            ->mapWithKeys(fn (string $eventName): array => [
                $eventName => SecurityAuditLog::labelFor($eventName),
            ]);

        $stats = [
            'last_24_hours' => SecurityAuditLog::query()
                ->where('created_at', '>=', now()->subDay())
                ->count(),
            'successful_logins_today' => SecurityAuditLog::query()
                ->where('event', 'login_success')
                ->where('created_at', '>=', now()->startOfDay())
                ->count(),
            'failed_logins_7_days' => SecurityAuditLog::query()
                ->whereIn('event', ['login_failed', 'login_blocked'])
                ->where('created_at', '>=', now()->subDays(7))
                ->count(),
            'account_changes_7_days' => SecurityAuditLog::query()
                ->whereIn('event', ['operator_created', 'operator_updated', 'password_changed'])
                ->where('created_at', '>=', now()->subDays(7))
                ->count(),
        ];

        return view('security-audit-logs.index', [
            'logs' => $logs,
            'stats' => $stats,
            'search' => $search,
            'selectedEvent' => $event,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'eventOptions' => $eventOptions,
        ]);
    }
}
