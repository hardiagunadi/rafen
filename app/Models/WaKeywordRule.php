<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class WaKeywordRule extends Model
{
    protected $fillable = [
        'owner_id',
        'keywords',
        'reply_text',
        'priority',
        'is_active',
    ];

    protected $casts = [
        'keywords'  => 'array',
        'priority'  => 'integer',
        'is_active' => 'boolean',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        return $query->where('owner_id', $user->effectiveOwnerId());
    }
}
