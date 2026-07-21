<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentWindowsService extends Model
{
    protected $fillable = [
        'system_name',
        'label',
        'expected_status',
        'monitoring_enabled',
        'alert_enabled',
        'sort_order',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'monitoring_enabled' => 'boolean',
            'alert_enabled' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
