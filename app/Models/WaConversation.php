<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WaConversation extends Model
{
    protected $fillable = [
        'owner_id',
        'session_id',
        'contact_phone',
        'contact_name',
        'assigned_to_id',
        'status',
        'last_message',
        'last_message_at',
        'unread_count',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'unread_count' => 'integer',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WaChatMessage::class, 'conversation_id');
    }

    public function ticket(): HasOne
    {
        return $this->hasOne(WaTicket::class, 'conversation_id');
    }

    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        return $query->where('owner_id', $user->effectiveOwnerId());
    }

    public function updateFromIncoming(string $message): void
    {
        $this->update([
            'last_message' => mb_substr($message, 0, 500),
            'last_message_at' => now(),
            'status' => 'open',
            'unread_count' => $this->unread_count + 1,
        ]);
    }
}
