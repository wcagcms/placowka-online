<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgentEnrollmentCode extends Model
{
    protected $fillable = [
        'device_id',
        'created_by',
        'code_hash',
        'code_label',
        'attempts',
        'max_attempts',
        'expires_at',
        'claimed_at',
        'used_at',
        'revoked_at',
        'created_ip',
        'claimed_ip',
        'used_ip',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'max_attempts' => 'integer',
        'expires_at' => 'datetime',
        'claimed_at' => 'datetime',
        'used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(AgentEnrollmentSession::class);
    }

    public function isAvailable(): bool
    {
        return $this->revoked_at === null
            && $this->used_at === null
            && $this->claimed_at === null
            && $this->expires_at?->isFuture()
            && $this->attempts < $this->max_attempts;
    }
}
