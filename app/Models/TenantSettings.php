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
        'npwp',
        'website',
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
        'wa_gateway_url',
        'wa_gateway_token',
        'wa_gateway_key',
        'wa_webhook_secret',
        'wa_notify_registration',
        'wa_notify_invoice',
        'wa_notify_payment',
        'wa_broadcast_enabled',
        'wa_antispam_enabled',
        'wa_antispam_delay_ms',
        'wa_antispam_max_per_minute',
        'wa_msg_randomize',
        'wa_template_registration',
        'wa_template_invoice',
        'wa_template_payment',
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
            'wa_notify_registration' => 'boolean',
            'wa_notify_invoice' => 'boolean',
            'wa_notify_payment' => 'boolean',
            'wa_broadcast_enabled' => 'boolean',
            'wa_antispam_enabled' => 'boolean',
            'wa_antispam_delay_ms' => 'integer',
            'wa_antispam_max_per_minute' => 'integer',
            'wa_msg_randomize' => 'boolean',
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

    public function hasWaConfigured(): bool
    {
        return ! empty($this->wa_gateway_url)
            && (! empty($this->wa_gateway_token) || ! empty($this->wa_gateway_key));
    }

    /**
     * Default template untuk tiap tipe notifikasi.
     */
    public function getDefaultTemplate(string $type): string
    {
        return match ($type) {
            'registration' => "Halo {name}, akun {service} Anda telah berhasil didaftarkan.\nUsername: {username}\nPaket: {profile}\nJatuh Tempo: {due_date}\n\nTerima kasih.",
            'invoice'      => "Halo {name}, tagihan Anda telah terbit.\nNo. Tagihan: {invoice_no}\nTotal: Rp {total}\nJatuh Tempo: {due_date}\n\nSilakan lakukan pembayaran sebelum jatuh tempo.",
            'payment'      => "Halo {name}, pembayaran Anda telah dikonfirmasi.\nNo. Tagihan: {invoice_no}\nJumlah: Rp {total}\nTanggal: {paid_at}\n\nTerima kasih atas pembayaran Anda.",
            default        => '',
        };
    }

    /**
     * Ambil template (custom jika ada, default jika tidak).
     */
    public function getTemplate(string $type): string
    {
        $custom = match ($type) {
            'registration' => $this->wa_template_registration,
            'invoice'      => $this->wa_template_invoice,
            'payment'      => $this->wa_template_payment,
            default        => null,
        };

        return ! empty($custom) ? $custom : $this->getDefaultTemplate($type);
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
