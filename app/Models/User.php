<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'last_login_at',
        'phone',
        'company_name',
        'address',
        'is_super_admin',
        'subscription_status',
        'subscription_expires_at',
        'subscription_plan_id',
        'vpn_username',
        'vpn_password',
        'vpn_ip',
        'vpn_enabled',
        'trial_days_remaining',
        'registered_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'vpn_password',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'is_super_admin' => 'boolean',
            'subscription_expires_at' => 'date',
            'vpn_enabled' => 'boolean',
            'trial_days_remaining' => 'integer',
            'registered_at' => 'datetime',
        ];
    }

    // Relationships

    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->where('status', 'active')->latest();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(BankAccount::class);
    }

    public function tenantSettings(): HasOne
    {
        return $this->hasOne(TenantSettings::class);
    }

    public function mikrotikConnections(): HasMany
    {
        return $this->hasMany(MikrotikConnection::class, 'owner_id');
    }

    public function pppUsers(): HasMany
    {
        return $this->hasMany(PppUser::class, 'owner_id');
    }

    public function pppProfiles(): HasMany
    {
        return $this->hasMany(PppProfile::class, 'owner_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'owner_id');
    }

    // Subscription helper methods

    public function isSuperAdmin(): bool
    {
        return $this->is_super_admin === true;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'administrator' || $this->isSuperAdmin();
    }

    public function canViewPppCredentials(): bool
    {
        return $this->isSuperAdmin();
    }

    public function hasActiveSubscription(): bool
    {
        return $this->subscription_status === 'active'
            && $this->subscription_expires_at
            && $this->subscription_expires_at->isFuture();
    }

    public function isOnTrial(): bool
    {
        return $this->subscription_status === 'trial' && $this->trial_days_remaining > 0;
    }

    public function isSubscriptionExpired(): bool
    {
        return $this->subscription_status === 'expired'
            || ($this->subscription_expires_at && $this->subscription_expires_at->isPast());
    }

    public function canAccessApp(): bool
    {
        return $this->isSuperAdmin() || $this->hasActiveSubscription() || $this->isOnTrial();
    }

    public function getSubscriptionDaysRemaining(): int
    {
        if ($this->isOnTrial()) {
            return $this->trial_days_remaining;
        }

        if (!$this->subscription_expires_at) {
            return 0;
        }

        if ($this->subscription_expires_at->isPast()) {
            return 0;
        }

        return now()->diffInDays($this->subscription_expires_at);
    }

    public function decrementTrialDays(): void
    {
        if ($this->isOnTrial() && $this->trial_days_remaining > 0) {
            $this->decrement('trial_days_remaining');

            if ($this->trial_days_remaining <= 0) {
                $this->update(['subscription_status' => 'expired']);
            }
        }
    }

    public function activateSubscription(SubscriptionPlan $plan, int $durationDays = null): void
    {
        $duration = $durationDays ?? $plan->duration_days;

        $this->update([
            'subscription_status' => 'active',
            'subscription_plan_id' => $plan->id,
            'subscription_expires_at' => now()->addDays($duration),
        ]);
    }

    public function extendSubscription(int $days): void
    {
        $currentExpiry = $this->subscription_expires_at ?? now();

        if ($currentExpiry->isPast()) {
            $currentExpiry = now();
        }

        $this->update([
            'subscription_status' => 'active',
            'subscription_expires_at' => $currentExpiry->addDays($days),
        ]);
    }

    public function getSettings(): TenantSettings
    {
        return TenantSettings::getOrCreate($this->id);
    }

    // Scopes

    public function scopeSuperAdmins($query)
    {
        return $query->where('is_super_admin', true);
    }

    public function scopeTenants($query)
    {
        return $query->where('is_super_admin', false);
    }

    public function scopeActiveSubscribers($query)
    {
        return $query->where('subscription_status', 'active')
            ->where('subscription_expires_at', '>', now());
    }

    public function scopeExpiredSubscribers($query)
    {
        return $query->where('subscription_status', 'expired')
            ->orWhere('subscription_expires_at', '<', now());
    }

    public function scopeTrialUsers($query)
    {
        return $query->where('subscription_status', 'trial')
            ->where('trial_days_remaining', '>', 0);
    }
}
