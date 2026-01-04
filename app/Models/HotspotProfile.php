<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotspotProfile extends Model
{
    /** @use HasFactory<\Database\Factories\HotspotProfileFactory> */
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'owner_id',
        'harga_jual',
        'harga_promo',
        'ppn',
        'bandwidth_profile_id',
        'profile_type',
        'limit_type',
        'time_limit_value',
        'time_limit_unit',
        'quota_limit_value',
        'quota_limit_unit',
        'masa_aktif_value',
        'masa_aktif_unit',
        'profile_group_id',
        'shared_users',
        'prioritas',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function bandwidthProfile(): BelongsTo
    {
        return $this->belongsTo(BandwidthProfile::class);
    }

    public function profileGroup(): BelongsTo
    {
        return $this->belongsTo(ProfileGroup::class);
    }
}
