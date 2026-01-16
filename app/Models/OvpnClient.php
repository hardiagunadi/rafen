<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OvpnClient extends Model
{
    /** @use HasFactory<\Database\Factories\OvpnClientFactory> */
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'mikrotik_connection_id',
        'name',
        'common_name',
        'username',
        'password',
        'vpn_ip',
        'is_active',
        'last_synced_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    public function mikrotikConnection(): BelongsTo
    {
        return $this->belongsTo(MikrotikConnection::class);
    }
}
