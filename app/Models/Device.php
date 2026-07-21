<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Device extends Model
{
    protected $fillable = [
        'facility_id',
        'uuid',
        'name',
        'token_hash',
        'status',
        'diagnostic_status',
        'last_seen_at',
        'last_latency_ms',
        'last_dns_latency_ms',
        'last_ip',
        'internet_ok',
        'dns_ok',
        'gateway_ok',
        'monitoring_server_ok',
        'agent_version',
        'agent_health',
        'agent_health_updated_at',
        'network_info',
        'network_info_updated_at',
        'windows_update',
        'windows_update_updated_at',
        'defender_status',
        'defender_status_updated_at',
        'antivirus_policy',
        'expected_antivirus_provider',
        'check_interval_seconds',
        'missing_after_minutes',
        'alert_after_minutes',
        'notes',
        'is_active',
        'archived_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'internet_ok' => 'boolean',
        'dns_ok' => 'boolean',
        'gateway_ok' => 'boolean',
        'monitoring_server_ok' => 'boolean',
        'is_active' => 'boolean',
        'archived_at' => 'datetime',
        'agent_health' => 'array',
        'agent_health_updated_at' => 'datetime',
        'network_info' => 'array',
        'network_info_updated_at' => 'datetime',
        'windows_update' => 'array',
        'windows_update_updated_at' => 'datetime',
        'defender_status' => 'array',
        'defender_status_updated_at' => 'datetime',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function heartbeats(): HasMany
    {
        return $this->hasMany(Heartbeat::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    public function enrollmentCodes(): HasMany
    {
        return $this->hasMany(AgentEnrollmentCode::class);
    }

    public function enrollmentSessions(): HasMany
    {
        return $this->hasMany(AgentEnrollmentSession::class);
    }
}
