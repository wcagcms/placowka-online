<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Heartbeat extends Model
{
    protected $fillable = [
        'device_id',
        'heartbeat_uuid',
        'status',
        'diagnostic_status',
        'internet_ok',
        'dns_ok',
        'gateway_ok',
        'monitoring_server_ok',
        'latency_ms',
        'dns_latency_ms',
        'ip_address',
        'agent_version',
        'network_info',
        'checked_at',
        'received_at',
        'is_replayed',
        'queue_delay_seconds',
        'payload',
    ];

    protected $casts = [
        'internet_ok' => 'boolean',
        'dns_ok' => 'boolean',
        'gateway_ok' => 'boolean',
        'monitoring_server_ok' => 'boolean',
        'checked_at' => 'datetime',
        'received_at' => 'datetime',
        'is_replayed' => 'boolean',
        'queue_delay_seconds' => 'integer',
        'payload' => 'array',
        'network_info' => 'array',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
