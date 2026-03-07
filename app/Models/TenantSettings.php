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
        'billing_date',
        'isolir_page_title',
        'isolir_page_body',
        'isolir_page_contact',
        'isolir_page_bg_color',
        'isolir_page_accent_color',
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
            'billing_date' => 'integer',
        ];
    }

    public function getIsolirPageTitle(): string
    {
        return $this->isolir_page_title ?: ($this->business_name ? 'Layanan '.$this->business_name.' Dinonaktifkan' : 'Layanan Internet Dinonaktifkan');
    }

    public function getIsolirPageBody(): string
    {
        return $this->isolir_page_body ?: "Layanan internet Anda telah dinonaktifkan sementara karena belum melakukan pembayaran.\n\nSilakan segera lakukan pembayaran untuk mengaktifkan kembali layanan Anda.";
    }

    public function getIsolirPageContact(): string
    {
        $parts = array_filter([$this->business_phone, $this->business_email]);
        return $this->isolir_page_contact ?: implode(' | ', $parts);
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
            'registration' => "*#Konfirmasi Registrasi Pelanggan*\n\n*Kepada Yth Bapak/Ibu {name}*,\nTerima kasih telah menjadi pelanggan kami. Kami sangat berterima kasih dan berharap kami dapat memberikan layanan terbaik kepada Anda\nBerikut informasi data registrasi anda :\n\n*Nama Lengkap : {name}*\n*Id Pelanggan : {customer_id}*\n*Paket Layanan : {profile}*\n*Tipe Pengguna : {service}*\n*Harga Paket : {total}*\n\nUntuk menghindari terisolirnya layanan anda, harap selalu bayarkan tagihan sebelum tanggal jatuh tempo\n\nUntuk informasi lainnya silahkan hubungi nomor *_Whatsapp {cs_number}_* untuk bantuan Customer Service\n\n*_Salam Hormat_*.",
            'invoice'      => "*##Invoice anda sudah diterbitkan*\n\nKepada Yth Bapak/Ibu *{name}*,\nBerikut ini merupakan pengingat tagihan anda dengan nomor invoice : {invoice_no}\n\nId Pelanggan : {customer_id}\nPaket Layanan : {profile}\nTipe Pembayaran : {service}\nJatuh Tempo : *{due_date}*\nJumlah : *{total}*\n\nUntuk menghindari penangguhan layanan dan pemutusan layanan, harap bayarkan tagihan anda melalui nomor rekening dibawah atau pengambilan dirumah oleh Tim kolektor kami sebelum tanggal jatuh tempo pembayaran\n\nMohon sertakan ID PELANGGAN pada konfirmasi pembayaran anda\n{bank_account}\n\nUntuk informasi lainnya silahkan hubungi nomor *_Whatsapp {cs_number}_* untuk bantuan Customer Service\n\n_*Salam Hormat*_.",
            'payment'      => "*### Terima kasih atas pembayaran anda*\n\nKepada Yth Bapak/Ibu *{name}*,\nTerima kasih telah melunasi pembayaran invoice {invoice_no}\nBerikut informasi perpanjangan paket anda :\n\nId Pelanggan : {customer_id}\nPaket Layanan : {profile}\nTipe Pembayaran : {service}\nJumlah Dibayarkan: *{total}*\n\n_Silahkan hubungi kami jika layanan anda masih terputus setelah membaca pesan ini_\n\nUntuk informasi lainnya silahkan hubungi nomor *Whatsapp {cs_number}* untuk bantuan Customer Service\n\n_*Salam Hormat*_.",
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
