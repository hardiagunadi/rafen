<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MikrotikConnection extends Model
{
    /** @use HasFactory<\Database\Factories\MikrotikConnectionFactory> */
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'owner_id',
        'name',
        'host',
        'api_port',
        'api_ssl_port',
        'use_ssl',
        'username',
        'password',
        'radius_secret',
        'ros_version',
        'api_timeout',
        'notes',
        'is_active',
        'is_online',
        'last_ping_latency_ms',
        'last_ping_at',
        'failed_ping_count',
        'last_port_open',
        'last_ping_message',
        'auth_port',
        'acct_port',
        'timezone',
        'isolir_url',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'api_port' => 'integer',
            'api_ssl_port' => 'integer',
            'use_ssl' => 'boolean',
            'api_timeout' => 'integer',
            'is_active' => 'boolean',
            'is_online' => 'boolean',
            'last_ping_at' => 'datetime',
            'failed_ping_count' => 'integer',
            'last_port_open' => 'boolean',
            'auth_port' => 'integer',
            'acct_port' => 'integer',
        ];
    }

    public function radiusAccounts(): HasMany
    {
        return $this->hasMany(RadiusAccount::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function scopeForOwner($query, $userId)
    {
        return $query->where('owner_id', $userId);
    }

    public function scopeAccessibleBy($query, User $user)
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }
        return $query->where('owner_id', $user->id);
    }
}
