<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    ];

    protected function casts(): array
    {
        return [
            'promo_applied' => 'boolean',
            'due_date' => 'date',
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
}
