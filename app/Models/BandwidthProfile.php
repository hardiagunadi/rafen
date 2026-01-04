<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BandwidthProfile extends Model
{
    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'upload_min_mbps',
        'upload_max_mbps',
        'download_min_mbps',
        'download_max_mbps',
        'owner',
    ];
}
