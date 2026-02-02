<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantSettings extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'business_name',
        'business_logo',
        'business_phone',
        'business_email',
        'business_address',
        'invoice_prefix',
        'invoice_footer',
        'invoice_notes',
        'enable_qris_payment',
        'enable_va_payment',
        'enable_manual_payment',
        'tripay_api_key',
        'tripay_private_key',
        'tripay_merchant_code',
        'tripay_sandbox',
        'enabled_payment_channels',
        'payment_expiry_hours',
        'auto_isolate_unpaid',
        'grace_period_days',
    ];

    protected function casts(): array
    {
        return [
            'enable_qris_payment' => 'boolean',
            'enable_va_payment' => 'boolean',
            'enable_manual_payment' => 'boolean',
            'tripay_sandbox' => 'boolean',
            'enabled_payment_channels' => 'array',
            'payment_expiry_hours' => 'integer',
            'auto_isolate_unpaid' => 'boolean',
            'grace_period_days' => 'integer',
        ];
    }

    protected $hidden = [
        'tripay_api_key',
        'tripay_private_key',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasTripayConfigured(): bool
    {
        return !empty($this->tripay_api_key)
            && !empty($this->tripay_private_key)
            && !empty($this->tripay_merchant_code);
    }

    public function hasPaymentGateway(): bool
    {
        return $this->hasTripayConfigured() && ($this->enable_qris_payment || $this->enable_va_payment);
    }

    public function getTripayApiUrl(): string
    {
        return $this->tripay_sandbox
            ? 'https://tripay.co.id/api-sandbox'
            : 'https://tripay.co.id/api';
    }

    public function getEnabledChannels(): array
    {
        return $this->enabled_payment_channels ?? [];
    }

    public static function getOrCreate(int $userId): self
    {
        return static::firstOrCreate(
            ['user_id' => $userId],
            [
                'invoice_prefix' => 'INV',
                'enable_manual_payment' => true,
                'payment_expiry_hours' => 24,
                'auto_isolate_unpaid' => true,
                'grace_period_days' => 3,
            ]
        );
    }
}
