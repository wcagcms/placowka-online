<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Facility extends Model
{
    protected $fillable = [
        'code',
        'name',
        'address',
        'contact_email',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    public function operators(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->where('users.role', User::ROLE_OPERATOR)
            ->withTimestamps()
            ->orderBy('users.name');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->whereHas('operators', function (Builder $operatorQuery) use ($user): void {
            $operatorQuery->where('users.id', $user->getKey())
                ->where('users.is_active', true);
        });
    }
}
