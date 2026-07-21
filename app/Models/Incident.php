<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Incident extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_ACKNOWLEDGED = 'acknowledged';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_CLOSED = 'closed';

    public const ACTIVE_STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_ACKNOWLEDGED,
        self::STATUS_IN_PROGRESS,
    ];

    public const PRIORITIES = ['low', 'medium', 'high', 'critical'];

    protected $fillable = [
        'facility_id',
        'device_id',
        'type',
        'status',
        'priority',
        'assigned_user_id',
        'acknowledged_by_user_id',
        'acknowledged_at',
        'resolved_by_user_id',
        'resolution_note',
        'closed_at',
        'closed_by_user_id',
        'last_status_change_at',
        'occurrence_count',
        'started_at',
        'ended_at',
        'last_seen_at',
        'duration_seconds',
        'summary',
        'meta',
        'opened_notification_sent_at',
        'resolved_notification_sent_at',
        'notification_last_error',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'closed_at' => 'datetime',
        'last_status_change_at' => 'datetime',
        'duration_seconds' => 'integer',
        'occurrence_count' => 'integer',
        'meta' => 'array',
        'opened_notification_sent_at' => 'datetime',
        'resolved_notification_sent_at' => 'datetime',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->whereIn('facility_id', $user->facilities()->select('facilities.id'));
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_ACKNOWLEDGED => 'Potwierdzony',
            self::STATUS_IN_PROGRESS => 'W trakcie',
            self::STATUS_RESOLVED => 'Rozwiązany',
            self::STATUS_CLOSED => 'Zamknięty',
            default => 'Nowy',
        };
    }

    public function priorityLabel(): string
    {
        return match ($this->priority) {
            'critical' => 'Krytyczny',
            'high' => 'Wysoki',
            'low' => 'Niski',
            default => 'Średni',
        };
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by_user_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(IncidentComment::class)->oldest();
    }
}
