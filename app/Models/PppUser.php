<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PppUser extends Model
{
    /** @use HasFactory<\Database\Factories\PppUserFactory> */
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'status_registrasi',
        'tipe_pembayaran',
        'status_bayar',
        'status_akun',
        'owner_id',
        'ppp_profile_id',
        'tipe_service',
        'tagihkan_ppn',
        'prorata_otomatis',
        'promo_aktif',
        'durasi_promo_bulan',
        'biaya_instalasi',
        'jatuh_tempo',
        'aksi_jatuh_tempo',
        'tipe_ip',
        'profile_group_id',
        'ip_static',
        'odp_pop',
        'customer_id',
        'customer_name',
        'nik',
        'nomor_hp',
        'email',
        'alamat',
        'latitude',
        'longitude',
        'metode_login',
        'username',
        'ppp_password',
        'password_clientarea',
        'catatan',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tagihkan_ppn' => 'boolean',
            'prorata_otomatis' => 'boolean',
            'promo_aktif' => 'boolean',
            'jatuh_tempo' => 'date',
            'biaya_instalasi' => 'decimal:2',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(PppProfile::class, 'ppp_profile_id');
    }

    public function profileGroup(): BelongsTo
    {
        return $this->belongsTo(ProfileGroup::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
