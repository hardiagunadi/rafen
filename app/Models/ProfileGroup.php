<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfileGroup extends Model
{
    /** @use HasFactory<\Database\Factories\ProfileGroupFactory> */
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'owner',
        'mikrotik_connection_id',
        'type',
        'ip_pool_mode',
        'ip_pool_name',
        'ip_address',
        'netmask',
        'range_start',
        'range_end',
        'dns_servers',
        'parent_queue',
        'host_min',
        'host_max',
    ];

    public function mikrotikConnection(): BelongsTo
    {
        return $this->belongsTo(MikrotikConnection::class);
    }

    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->whereNull('mikrotik_connection_id')
              ->orWhereHas('mikrotikConnection', fn (Builder $q2) => $q2->where('owner_id', $user->effectiveOwnerId()));
        });
    }
}
