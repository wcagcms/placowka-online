<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentEnrollmentSession extends Model
{
    protected $fillable = [
        'public_id',
        'agent_enrollment_code_id',
        'device_id',
        'session_token_hash',
        'client_nonce_hash',
        'machine_name',
        'architecture',
        'windows_version',
        'setup_version',
        'start_ip',
        'complete_ip',
        'expires_at',
        'completed_at',
        'confirmed_at',
        'issued_token_ciphertext',
        'token_replay_until',
    ];

    protected $hidden = [
        'session_token_hash',
        'client_nonce_hash',
        'issued_token_ciphertext',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'completed_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'token_replay_until' => 'datetime',
    ];

    public function code(): BelongsTo
    {
        return $this->belongsTo(AgentEnrollmentCode::class, 'agent_enrollment_code_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
