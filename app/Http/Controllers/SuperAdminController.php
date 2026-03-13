<?php

namespace App\Http\Controllers;

use App\Models\HotspotUser;
use App\Models\MikrotikConnection;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\PppUser;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SuperAdminController extends Controller
{
    public function dashboard()
    {
        $stats = [
            'total_tenants' => User::tenants()->count(),
            'active_subscribers' => User::activeSubscribers()->count(),
            'trial_users' => User::trialUsers()->count(),
            'expired_subscribers' => User::expiredSubscribers()->count(),
            'total_mikrotik' => MikrotikConnection::count(),
            'total_ppp_users' => PppUser::count(),
            'total_revenue' => Payment::paid()->forSubscription()->sum('amount'),
            'monthly_revenue' => Payment::paid()->forSubscription()
                ->whereMonth('paid_at', now()->month)
                ->whereYear('paid_at', now()->year)
                ->sum('amount'),
        ];

        $recentSubscriptions = Subscription::with(['user', 'plan'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $recentPayments = Payment::with(['user', 'subscription.plan'])
            ->forSubscription()
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $expiringSubscriptions = User::activeSubscribers()
            ->where('subscription_expires_at', '<=', now()->addDays(7))
            ->orderBy('subscription_expires_at')
            ->limit(10)
            ->get();

        return view('super-admin.dashboard', compact(
            'stats',
            'recentSubscriptions',
            'recentPayments',
            'expiringSubscriptions'
        ));
    }

    public function tenants(Request $request)
    {
        $query = User::tenants()->with('subscriptionPlan');

        if ($request->filled('status')) {
            $query->where('subscription_status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%");
            });
        }

        $tenants = $query->orderByDesc('created_at')->paginate(20);

        return view('super-admin.tenants.index', compact('tenants'));
    }

    public function showTenant(User $tenant)
    {
        $this->ensureTenantAccount($tenant);

        $tenant->load([
            'subscriptionPlan',
            'subscriptions' => fn ($q) => $q->with('plan')->orderByDesc('created_at')->limit(10),
            'mikrotikConnections',
            'pppUsers',
            'tenantSettings',
        ]);

        $pendingSubscriptions = $tenant->subscriptions()
            ->with('plan')
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->get();
        $tenantRoles = $this->tenantRoleSummaries($tenant);

        $stats = [
            'mikrotik_count' => $tenant->mikrotikConnections()->count(),
            'ppp_users_count' => $tenant->pppUsers()->count(),
            'active_ppp_users' => $tenant->pppUsers()->where('status_akun', 'enable')->count(),
            'hotspot_users_count' => HotspotUser::query()->where('owner_id', $tenant->id)->count(),
            'active_hotspot_users' => HotspotUser::query()
                ->where('owner_id', $tenant->id)
                ->where('status_akun', 'enable')
                ->count(),
            'invoices_count' => $tenant->invoices()->count(),
            'unpaid_invoices' => $tenant->invoices()->unpaid()->count(),
            'total_revenue' => $tenant->invoices()->paid()->sum('total'),
        ];

        return view('super-admin.tenants.show', compact('tenant', 'stats', 'pendingSubscriptions', 'tenantRoles'));
    }

    public function editTenant(User $tenant)
    {
        $this->ensureTenantAccount($tenant);

        $plans = SubscriptionPlan::active()->orderBy('sort_order')->get();

        return view('super-admin.tenants.edit', compact('tenant', 'plans'));
    }

    public function updateTenant(Request $request, User $tenant)
    {
        $this->ensureTenantAccount($tenant);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$tenant->id,
            'phone' => 'nullable|string|max:20',
            'company_name' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:1000',
            'subscription_status' => 'required|in:trial,active,expired,suspended',
            'subscription_plan_id' => 'nullable|exists:subscription_plans,id',
            'subscription_method' => 'required|in:monthly,license',
            'license_max_mikrotik' => 'nullable|required_if:subscription_method,license|integer|min:-1',
            'license_max_ppp_users' => 'nullable|required_if:subscription_method,license|integer|min:-1',
            'subscription_expires_at' => 'nullable|date',
            'trial_days_remaining' => 'nullable|integer|min:0',
            'vpn_enabled' => 'boolean',
            'vpn_username' => 'nullable|string|max:100',
            'vpn_password' => 'nullable|string|max:100',
            'vpn_ip' => 'nullable|string|max:45',
        ]);

        if ($validated['subscription_method'] !== User::SUBSCRIPTION_METHOD_LICENSE) {
            $validated['license_max_mikrotik'] = null;
            $validated['license_max_ppp_users'] = null;
        } else {
            $validated['subscription_status'] = 'active';
            $validated['trial_days_remaining'] = 0;
            $validated['subscription_expires_at'] = $validated['subscription_expires_at']
                ?? $this->resolveLicenseExpiryDate($tenant);
        }

        $validated['vpn_enabled'] = $request->boolean('vpn_enabled');

        $tenant->update($validated);

        return redirect()->route('super-admin.tenants.show', $tenant)
            ->with('success', 'Data tenant berhasil diperbarui.');
    }

    public function activateTenant(Request $request, User $tenant)
    {
        $this->ensureTenantAccount($tenant);

        $request->validate([
            'plan_id' => 'required|exists:subscription_plans,id',
            'duration_days' => 'nullable|integer|min:1',
        ]);

        $plan = SubscriptionPlan::findOrFail($request->plan_id);
        $durationDays = $request->filled('duration_days') ? (int) $request->input('duration_days') : null;
        $duration = $tenant->resolveSubscriptionDurationDays($plan, $durationDays);

        // Expire all previous active/pending subscriptions
        $tenant->subscriptions()->whereIn('status', ['active', 'pending'])->update(['status' => 'expired']);

        $tenant->activateSubscription($plan, $duration);

        // Create subscription record
        Subscription::create([
            'user_id' => $tenant->id,
            'subscription_plan_id' => $plan->id,
            'start_date' => now(),
            'end_date' => now()->addDays($duration),
            'status' => 'active',
            'amount_paid' => 0,
            'activated_at' => now(),
        ]);

        return back()->with('success', 'Langganan tenant berhasil diaktifkan.');
    }

    public function suspendTenant(Request $request, User $tenant)
    {
        $this->ensureTenantAccount($tenant);

        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $tenant->update(['subscription_status' => 'suspended']);

        return back()->with('success', 'Tenant berhasil disuspend.');
    }

    public function extendTenant(Request $request, User $tenant)
    {
        $this->ensureTenantAccount($tenant);

        $request->validate([
            'days' => $tenant->isLicenseSubscription()
                ? 'nullable|integer|min:1|max:3650'
                : 'required|integer|min:1|max:365',
        ]);

        $days = $tenant->isLicenseSubscription()
            ? User::LICENSE_DURATION_DAYS
            : (int) $request->input('days');

        $tenant->extendSubscription($days);

        if ($tenant->isLicenseSubscription()) {
            return back()->with('success', 'Lisensi tenant diperpanjang 1 tahun (365 hari).');
        }

        return back()->with('success', "Langganan diperpanjang {$days} hari.");
    }

    public function changePlanPreview(Request $request, User $tenant)
    {
        $this->ensureTenantAccount($tenant);

        $request->validate(['plan_id' => 'required|exists:subscription_plans,id']);

        $plan = SubscriptionPlan::findOrFail($request->plan_id);
        $oldPlan = $tenant->subscriptionPlan;

        $remainingDays = 0;
        $remainingValue = 0;
        $newDurationDays = $tenant->resolveSubscriptionDurationDays($plan);
        $oldDurationDays = $oldPlan ? $tenant->resolveSubscriptionDurationDays($oldPlan) : 0;

        if ($tenant->subscription_expires_at && $tenant->subscription_expires_at->isFuture() && $oldPlan) {
            $remainingDays = (int) now()->diffInDays($tenant->subscription_expires_at, false);
            $remainingDays = max(0, $remainingDays);
            $pricePerDay = $oldDurationDays > 0 ? ($oldPlan->price / $oldDurationDays) : 0;
            $remainingValue = round($pricePerDay * $remainingDays);
        }

        $proratedCost = max(0, $plan->price - $remainingValue);
        $extraDays = 0;
        if ($remainingValue > $plan->price) {
            $newPricePerDay = $newDurationDays > 0 ? ($plan->price / $newDurationDays) : 1;
            $extraDays = (int) floor(($remainingValue - $plan->price) / $newPricePerDay);
        }
        $totalDuration = $newDurationDays + $extraDays;

        return response()->json([
            'remaining_days' => $remainingDays,
            'remaining_value' => $remainingValue,
            'prorated_cost' => $proratedCost,
            'extra_days' => $extraDays,
            'total_duration' => $totalDuration,
            'new_plan_price' => (float) $plan->price,
            'new_plan_name' => $plan->name,
            'new_plan_days' => $newDurationDays,
        ]);
    }

    public function changePlan(Request $request, User $tenant)
    {
        $this->ensureTenantAccount($tenant);

        $request->validate([
            'plan_id' => 'required|exists:subscription_plans,id',
            'payment_method' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        $plan = SubscriptionPlan::findOrFail($request->plan_id);
        $oldPlan = $tenant->subscriptionPlan;

        // Calculate prorated values
        $remainingDays = 0;
        $remainingValue = 0;
        $newDurationDays = $tenant->resolveSubscriptionDurationDays($plan);
        $oldDurationDays = $oldPlan ? $tenant->resolveSubscriptionDurationDays($oldPlan) : 0;

        if ($tenant->subscription_expires_at && $tenant->subscription_expires_at->isFuture() && $oldPlan) {
            $remainingDays = max(0, (int) now()->diffInDays($tenant->subscription_expires_at, false));
            $pricePerDay = $oldDurationDays > 0 ? ($oldPlan->price / $oldDurationDays) : 0;
            $remainingValue = round($pricePerDay * $remainingDays);
        }

        $proratedCost = max(0, $plan->price - $remainingValue);
        $extraDays = 0;
        if ($remainingValue > $plan->price) {
            $newPricePerDay = $newDurationDays > 0 ? ($plan->price / $newDurationDays) : 1;
            $extraDays = (int) floor(($remainingValue - $plan->price) / $newPricePerDay);
        }
        $totalDuration = $newDurationDays + $extraDays;

        $newExpiry = now()->addDays($totalDuration);

        // Expire all previous active/pending subscriptions
        $tenant->subscriptions()->whereIn('status', ['active', 'pending'])->update(['status' => 'expired']);

        $tenant->update([
            'subscription_plan_id' => $plan->id,
            'subscription_status' => 'active',
            'subscription_expires_at' => $newExpiry,
        ]);

        $subscription = Subscription::create([
            'user_id' => $tenant->id,
            'subscription_plan_id' => $plan->id,
            'start_date' => now(),
            'end_date' => $newExpiry,
            'status' => 'active',
            'amount_paid' => $proratedCost,
            'activated_at' => now(),
        ]);

        // Record payment (prorated)
        if ($proratedCost > 0) {
            Payment::create([
                'payment_number' => Payment::generatePaymentNumber(),
                'payment_type' => 'subscription',
                'user_id' => $tenant->id,
                'subscription_id' => $subscription->id,
                'payment_channel' => 'manual',
                'payment_method' => $request->input('payment_method', 'Transfer Manual'),
                'amount' => $proratedCost,
                'fee' => 0,
                'total_amount' => $proratedCost,
                'status' => 'paid',
                'paid_at' => now(),
                'notes' => $request->input('notes') ?: "Perubahan paket: {$oldPlan?->name} → {$plan->name}. Sisa nilai: Rp ".number_format($remainingValue, 0, ',', '.'),
            ]);
        }

        return back()->with('success', "Paket berhasil diubah ke {$plan->name}. Tagihan prorated: Rp ".number_format($proratedCost, 0, ',', '.').'. Aktif hingga: '.$newExpiry->format('d M Y').'.');
    }

    public function confirmSubscriptionPayment(Request $request, User $tenant, Subscription $subscription)
    {
        $this->ensureTenantAccount($tenant);

        if ($subscription->user_id !== $tenant->id) {
            abort(403);
        }

        if ($subscription->status !== 'pending') {
            return back()->with('error', 'Langganan ini sudah diproses.');
        }

        $request->validate([
            'payment_method' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        // Create a manual payment record
        Payment::create([
            'payment_number' => Payment::generatePaymentNumber(),
            'payment_type' => 'subscription',
            'user_id' => $tenant->id,
            'subscription_id' => $subscription->id,
            'payment_channel' => 'manual',
            'payment_method' => $request->input('payment_method', 'Transfer Manual'),
            'amount' => $subscription->amount_paid,
            'fee' => 0,
            'total_amount' => $subscription->amount_paid,
            'status' => 'paid',
            'paid_at' => now(),
            'notes' => $request->input('notes'),
        ]);

        // Activate subscription
        $subscription->activate();

        return back()->with('success', 'Pembayaran berhasil dikonfirmasi dan langganan telah diaktifkan.');
    }

    public function createTenant()
    {
        $plans = SubscriptionPlan::active()->orderBy('sort_order')->get();

        return view('super-admin.tenants.create', compact('plans'));
    }

    public function storeTenant(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
            'company_name' => 'nullable|string|max:255',
            'subscription_plan_id' => 'nullable|exists:subscription_plans,id',
            'subscription_method' => 'required|in:monthly,license',
            'license_max_mikrotik' => 'nullable|required_if:subscription_method,license|integer|min:-1',
            'license_max_ppp_users' => 'nullable|required_if:subscription_method,license|integer|min:-1',
            'trial_days' => 'nullable|integer|min:0|max:90',
        ]);

        if ($validated['subscription_method'] !== User::SUBSCRIPTION_METHOD_LICENSE) {
            $validated['license_max_mikrotik'] = null;
            $validated['license_max_ppp_users'] = null;
        }
        $isLicenseMethod = $validated['subscription_method'] === User::SUBSCRIPTION_METHOD_LICENSE;

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
            'company_name' => $validated['company_name'] ?? null,
            'role' => 'administrator',
            'is_super_admin' => false,
            'subscription_status' => $isLicenseMethod ? 'active' : 'trial',
            'subscription_plan_id' => $validated['subscription_plan_id'] ?? null,
            'subscription_method' => $validated['subscription_method'],
            'license_max_mikrotik' => $validated['license_max_mikrotik'] ?? null,
            'license_max_ppp_users' => $validated['license_max_ppp_users'] ?? null,
            'subscription_expires_at' => $isLicenseMethod ? now()->addDays(User::LICENSE_DURATION_DAYS)->toDateString() : null,
            'trial_days_remaining' => $isLicenseMethod ? 0 : ($validated['trial_days'] ?? 14),
            'registered_at' => now(),
        ]);

        // Create default settings
        $user->getSettings();

        return redirect()->route('super-admin.tenants.show', $user)
            ->with('success', 'Tenant berhasil dibuat.');
    }

    public function deleteTenant(User $tenant)
    {
        $this->ensureTenantAccount($tenant);

        $activePppUsers = $tenant->pppUsers()
            ->where('status_akun', 'enable')
            ->count();
        $activeHotspotUsers = HotspotUser::query()
            ->where('owner_id', $tenant->id)
            ->where('status_akun', 'enable')
            ->count();
        $activeCustomerCount = $activePppUsers + $activeHotspotUsers;

        if ($activeCustomerCount > 0) {
            return back()->with(
                'error',
                "Tenant tidak bisa dihapus karena masih ada {$activeCustomerCount} pelanggan aktif (PPPoE: {$activePppUsers}, Hotspot: {$activeHotspotUsers}). Nonaktifkan atau migrasikan pelanggan terlebih dahulu."
            );
        }

        $tenant->delete();

        return redirect()->route('super-admin.tenants')
            ->with('success', 'Tenant berhasil dihapus.');
    }

    // System Payment Gateway Settings

    public function paymentGateways()
    {
        $gateways = PaymentGateway::all();

        return view('super-admin.payment-gateways.index', compact('gateways'));
    }

    public function storePaymentGateway(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:payment_gateways,code',
            'provider' => 'required|string|max:50',
            'api_key' => 'nullable|string',
            'private_key' => 'nullable|string',
            'merchant_code' => 'nullable|string|max:50',
            'is_sandbox' => 'boolean',
            'is_active' => 'boolean',
        ]);

        PaymentGateway::create($validated);

        return back()->with('success', 'Payment gateway berhasil ditambahkan.');
    }

    public function updatePaymentGateway(Request $request, PaymentGateway $gateway)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'api_key' => 'nullable|string',
            'private_key' => 'nullable|string',
            'merchant_code' => 'nullable|string|max:50',
            'is_sandbox' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $gateway->update($validated);

        return back()->with('success', 'Payment gateway berhasil diperbarui.');
    }

    // Reports

    public function revenueReport(Request $request)
    {
        $startDate = $request->start_date ? \Carbon\Carbon::parse($request->start_date) : now()->startOfMonth();
        $endDate = $request->end_date ? \Carbon\Carbon::parse($request->end_date) : now()->endOfMonth();

        $subscriptionRevenue = Payment::paid()
            ->forSubscription()
            ->whereBetween('payments.paid_at', [$startDate, $endDate])
            ->sum('payments.amount');

        $dailyRevenue = Payment::paid()
            ->forSubscription()
            ->whereBetween('payments.paid_at', [$startDate, $endDate])
            ->selectRaw('DATE(payments.paid_at) as date, SUM(payments.amount) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $revenueByPlan = Payment::paid()
            ->forSubscription()
            ->whereBetween('payments.paid_at', [$startDate, $endDate])
            ->join('subscriptions', 'payments.subscription_id', '=', 'subscriptions.id')
            ->join('subscription_plans', 'subscriptions.subscription_plan_id', '=', 'subscription_plans.id')
            ->selectRaw('subscription_plans.name as plan_name, SUM(payments.amount) as total')
            ->groupBy('subscription_plans.id', 'subscription_plans.name')
            ->get();

        return view('super-admin.reports.revenue', compact(
            'startDate',
            'endDate',
            'subscriptionRevenue',
            'dailyRevenue',
            'revenueByPlan'
        ));
    }

    public function tenantsReport(Request $request)
    {
        $tenantsByStatus = User::tenants()
            ->selectRaw('subscription_status, COUNT(*) as total')
            ->groupBy('subscription_status')
            ->get()
            ->pluck('total', 'subscription_status');

        $newTenantsThisMonth = User::tenants()
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $churnedThisMonth = User::tenants()
            ->where('subscription_status', 'expired')
            ->whereMonth('subscription_expires_at', now()->month)
            ->whereYear('subscription_expires_at', now()->year)
            ->count();

        $topTenants = User::tenants()
            ->withCount('pppUsers')
            ->orderByDesc('ppp_users_count')
            ->limit(10)
            ->get();

        return view('super-admin.reports.tenants', compact(
            'tenantsByStatus',
            'newTenantsThisMonth',
            'churnedThisMonth',
            'topTenants'
        ));
    }

    // VPN Management for Tenants

    public function vpnSettings(User $tenant)
    {
        $this->ensureTenantAccount($tenant);

        return view('super-admin.tenants.vpn', compact('tenant'));
    }

    public function updateVpnSettings(Request $request, User $tenant)
    {
        $this->ensureTenantAccount($tenant);

        $validated = $request->validate([
            'vpn_enabled' => 'boolean',
            'vpn_username' => 'required_if:vpn_enabled,true|nullable|string|max:100',
            'vpn_password' => 'nullable|string|max:100',
            'vpn_ip' => 'nullable|ip',
        ]);

        $tenant->update($validated);

        // TODO: Integrate with OpenVPN to actually create/update the VPN user

        return back()->with('success', 'Pengaturan VPN berhasil diperbarui.');
    }

    public function generateVpnCredentials(User $tenant)
    {
        $this->ensureTenantAccount($tenant);

        $username = 'tenant_'.$tenant->id;
        $password = \Str::random(16);

        $tenant->update([
            'vpn_username' => $username,
            'vpn_password' => $password,
        ]);

        // TODO: Integrate with OpenVPN to create the VPN user

        return back()->with('success', 'Kredensial VPN berhasil dibuat.');
    }

    private function ensureTenantAccount(User $tenant): void
    {
        if ($tenant->isSuperAdmin() || $tenant->role !== 'administrator' || $tenant->parent_id !== null) {
            abort(404);
        }
    }

    private function tenantRoleSummaries(User $tenant)
    {
        return User::query()
            ->selectRaw('role, COUNT(*) as total')
            ->where(function ($query) use ($tenant) {
                $query->where('id', $tenant->id)
                    ->orWhere('parent_id', $tenant->id);
            })
            ->groupBy('role')
            ->orderBy('role')
            ->get()
            ->map(function (User $roleSummary): array {
                return [
                    'role' => (string) $roleSummary->role,
                    'label' => $this->roleLabel((string) $roleSummary->role),
                    'total' => (int) $roleSummary->total,
                ];
            })
            ->values();
    }

    private function resolveLicenseExpiryDate(User $tenant): string
    {
        if ($tenant->subscription_expires_at && $tenant->subscription_expires_at->isFuture()) {
            return $tenant->subscription_expires_at->toDateString();
        }

        return now()->addDays(User::LICENSE_DURATION_DAYS)->toDateString();
    }

    private function roleLabel(string $role): string
    {
        return match ($role) {
            'administrator' => 'Administrator',
            'it_support' => 'IT Support',
            'noc' => 'NOC',
            'keuangan' => 'Keuangan',
            'mitra' => 'Mitra',
            'teknisi' => 'Teknisi',
            'cs' => 'Customer Services',
            default => ucwords(str_replace('_', ' ', $role)),
        };
    }
}
