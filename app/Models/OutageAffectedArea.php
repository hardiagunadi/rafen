<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutageAffectedArea extends Model
{
    protected $fillable = [
        'outage_id',
        'area_type',
        'odp_id',
        'label',
    ];

    public function outage(): BelongsTo
    {
        return $this->belongsTo(Outage::class, 'outage_id');
    }

    public function odp(): BelongsTo
    {
        return $this->belongsTo(Odp::class, 'odp_id');
    }

    public function getDisplayLabelAttribute(): string
    {
        if ($this->area_type === 'odp' && $this->odp) {
            $name = $this->odp->name;
            if ($this->odp->area) {
                $name .= ' – ' . $this->odp->area;
            }

            return $name;
        }

        return $this->label ?? 'Area Tidak Diketahui';
    }
}
