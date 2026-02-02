<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Invoice extends Model
{
    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'invoice_number',
        'ppp_user_id',
        'ppp_profile_id',
        'owner_id',
        'customer_id',
        'customer_name',
        'tipe_service',
        'paket_langganan',
        'harga_dasar',
        'ppn_percent',
        'ppn_amount',
        'total',
        'promo_applied',
        'due_date',
        'status',
        'payment_method',
        'payment_channel',
        'payment_reference',
        'paid_at',
        'payment_id',
    ];

    protected function casts(): array
    {
        return [
            'promo_applied' => 'boolean',
            'due_date' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    public function pppUser(): BelongsTo
    {
        return $this->belongsTo(PppUser::class);
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(PppProfile::class, 'ppp_profile_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function payments(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isUnpaid(): bool
    {
        return $this->status === 'unpaid';
    }

    public function isOverdue(): bool
    {
        return $this->isUnpaid() && $this->due_date && $this->due_date->isPast();
    }

    public function scopeUnpaid($query)
    {
        return $query->where('status', 'unpaid');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'unpaid')
            ->whereNotNull('due_date')
            ->where('due_date', '<', now());
    }

    public function scopeAccessibleBy($query, User $user)
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }
        return $query->where('owner_id', $user->id);
    }

    public function getFormattedTotalAttribute(): string
    {
        return 'Rp ' . number_format($this->total, 0, ',', '.');
    }
}
