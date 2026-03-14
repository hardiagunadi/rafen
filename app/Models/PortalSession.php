<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortalSession extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'ppp_user_id',
        'token',
        'ip_address',
        'user_agent',
        'last_activity_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'last_activity_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function pppUser(): BelongsTo
    {
        return $this->belongsTo(PppUser::class, 'ppp_user_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
