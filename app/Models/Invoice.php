<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

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
        'harga_asli',
        'ppn_percent',
        'ppn_amount',
        'total',
        'promo_applied',
        'prorata_applied',
        'due_date',
        'status',
        'payment_method',
        'payment_channel',
        'payment_reference',
        'paid_at',
        'payment_id',
        'paid_by',
        'cash_received',
        'transfer_amount',
        'payment_note',
    ];

    protected function casts(): array
    {
        return [
            'promo_applied' => 'boolean',
            'prorata_applied' => 'boolean',
            'harga_asli' => 'decimal:2',
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

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
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
        return $query->where('owner_id', $user->effectiveOwnerId());
    }

    public function getFormattedTotalAttribute(): string
    {
        return 'Rp ' . number_format($this->total, 0, ',', '.');
    }

    /**
     * Generate nomor invoice berurutan per-tenant per-bulan.
     * Format: PREFIX-YYYYMMnnnn  contoh: INV-2026030001
     */
    public static function generateNumber(int $ownerId, string $prefix): string
    {
        return DB::transaction(function () use ($ownerId, $prefix) {
            $yearMonth = now()->format('Ym');
            $pattern   = $prefix . '-' . $yearMonth . '%';

            $last = static::where('owner_id', $ownerId)
                ->where('invoice_number', 'like', $pattern)
                ->lockForUpdate()
                ->max('invoice_number');

            $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

            return $prefix . '-' . $yearMonth . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
        });
    }
}
