<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaWebhookLog extends Model
{
    protected $fillable = [
        'event_type',
        'session_id',
        'sender',
        'message',
        'status',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function scopeMessages($query)
    {
        return $query->where('event_type', 'message');
    }

    public function scopeSessions($query)
    {
        return $query->where('event_type', 'session');
    }
}
