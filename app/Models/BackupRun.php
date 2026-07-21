<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackupRun extends Model
{
    protected $fillable = [
        'status',
        'driver',
        'filename',
        'storage_path',
        'checksum_sha256',
        'size_bytes',
        'started_at',
        'completed_at',
        'verified_at',
        'deleted_at',
        'error_message',
        'meta',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'verified_at' => 'datetime',
        'deleted_at' => 'datetime',
        'meta' => 'array',
    ];

    public function statusLabel(): string
    {
        return match ($this->status) {
            'success' => 'Poprawna',
            'running' => 'Tworzenie',
            'corrupted' => 'Uszkodzona',
            'failed' => 'Błąd',
            default => ucfirst((string) $this->status),
        };
    }

    public function sizeLabel(): string
    {
        $bytes = (int) ($this->size_bytes ?? 0);

        if ($bytes <= 0) {
            return '—';
        }

        if ($bytes >= 1024 * 1024 * 1024) {
            return number_format($bytes / (1024 * 1024 * 1024), 2, ',', ' ').' GB';
        }

        if ($bytes >= 1024 * 1024) {
            return number_format($bytes / (1024 * 1024), 2, ',', ' ').' MB';
        }

        return number_format($bytes / 1024, 1, ',', ' ').' KB';
    }

    public function isAvailable(): bool
    {
        return $this->deleted_at === null
            && is_string($this->storage_path)
            && $this->storage_path !== ''
            && is_file(storage_path($this->storage_path));
    }
}
