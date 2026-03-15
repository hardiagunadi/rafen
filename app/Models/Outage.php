<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Outage extends Model
{
    const STATUS_OPEN        = 'open';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_RESOLVED    = 'resolved';

    const SEVERITY_LOW      = 'low';
    const SEVERITY_MEDIUM   = 'medium';
    const SEVERITY_HIGH     = 'high';
    const SEVERITY_CRITICAL = 'critical';

    protected $fillable = [
        'owner_id',
        'title',
        'description',
        'status',
        'severity',
        'started_at',
        'estimated_resolved_at',
        'resolved_at',
        'assigned_teknisi_id',
        'public_token',
        'wa_blast_sent_at',
        'wa_blast_count',
        'resolution_wa_sent_at',
        'created_by_id',
        'include_status_link',
    ];

    protected function casts(): array
    {
        return [
            'started_at'            => 'datetime',
            'estimated_resolved_at' => 'datetime',
            'resolved_at'           => 'datetime',
            'wa_blast_sent_at'      => 'datetime',
            'resolution_wa_sent_at' => 'datetime',
            'wa_blast_count'        => 'integer',
            'include_status_link'   => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Outage $outage) {
            if (empty($outage->public_token)) {
                $outage->public_token = bin2hex(random_bytes(16));
            }
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function assignedTeknisi(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_teknisi_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function affectedAreas(): HasMany
    {
        return $this->hasMany(OutageAffectedArea::class, 'outage_id');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(OutageUpdate::class, 'outage_id')->orderBy('created_at');
    }

    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }

    public function affectedOdpIds(): array
    {
        return $this->affectedAreas
            ->where('area_type', 'odp')
            ->pluck('odp_id')
            ->filter()
            ->values()
            ->all();
    }

    public function affectedKeywords(): array
    {
        return $this->affectedAreas
            ->where('area_type', 'keyword')
            ->pluck('label')
            ->filter()
            ->values()
            ->all();
    }

    public function affectedAreaLabels(): array
    {
        return $this->affectedAreas
            ->map(fn ($a) => $a->display_label)
            ->filter()
            ->values()
            ->all();
    }

    public function affectedPppUsers(): Builder
    {
        $odpIds   = $this->affectedOdpIds();
        $keywords = $this->affectedKeywords();

        return PppUser::query()
            ->distinct()
            ->where('owner_id', $this->owner_id)
            ->where('status_akun', 'enable')
            ->whereNotNull('nomor_hp')
            ->where('nomor_hp', '!=', '')
            ->where(function ($q) use ($odpIds, $keywords) {
                if (! empty($odpIds)) {
                    $q->orWhereIn('odp_id', $odpIds);
                }
                foreach ($keywords as $kw) {
                    $q->orWhere('alamat', 'LIKE', '%'.$kw.'%');
                }
            });
    }

    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        $base = $query->where('owner_id', $user->effectiveOwnerId());

        if ($user->isTeknisi()) {
            return $base->where('assigned_teknisi_id', $user->id);
        }

        return $base;
    }
}
