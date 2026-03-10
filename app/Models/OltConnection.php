<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OltConnection extends Model
{
    /** @use HasFactory<\Database\Factories\OltConnectionFactory> */
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'owner_id',
        'vendor',
        'name',
        'olt_model',
        'host',
        'snmp_port',
        'snmp_version',
        'snmp_community',
        'snmp_write_community',
        'snmp_timeout',
        'snmp_retries',
        'is_active',
        'oid_serial',
        'oid_onu_name',
        'oid_rx_onu',
        'oid_tx_onu',
        'oid_rx_olt',
        'oid_tx_olt',
        'oid_distance',
        'oid_status',
        'last_polled_at',
        'last_poll_success',
        'last_poll_message',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'snmp_port' => 'integer',
            'snmp_timeout' => 'integer',
            'snmp_retries' => 'integer',
            'is_active' => 'boolean',
            'last_polled_at' => 'datetime',
            'last_poll_success' => 'boolean',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function onuOptics(): HasMany
    {
        return $this->hasMany(OltOnuOptic::class);
    }

    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        return $query->where('owner_id', $user->effectiveOwnerId());
    }
}
