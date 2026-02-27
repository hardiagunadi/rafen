<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotspotUser extends Model
{
    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'status_registrasi',
        'tipe_pembayaran',
        'status_bayar',
        'status_akun',
        'owner_id',
        'hotspot_profile_id',
        'profile_group_id',
        'tagihkan_ppn',
        'biaya_instalasi',
        'jatuh_tempo',
        'aksi_jatuh_tempo',
        'customer_id',
        'customer_name',
        'nik',
        'nomor_hp',
        'email',
        'alamat',
        'username',
        'hotspot_password',
        'catatan',
        'mixradius_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tagihkan_ppn'   => 'boolean',
            'jatuh_tempo'    => 'date',
            'biaya_instalasi' => 'decimal:2',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function hotspotProfile(): BelongsTo
    {
        return $this->belongsTo(HotspotProfile::class);
    }

    public function profileGroup(): BelongsTo
    {
        return $this->belongsTo(ProfileGroup::class);
    }

    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        return $query->where('owner_id', $user->id);
    }

    public function getMaskedPasswordAttribute(): string
    {
        $user = auth()->user();

        if ($user && $user->canViewPppCredentials()) {
            return $this->hotspot_password ?? '';
        }

        return '********';
    }
}
