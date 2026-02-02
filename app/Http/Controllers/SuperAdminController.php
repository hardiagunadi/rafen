<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
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
        if ($tenant->isSuperAdmin()) {
            abort(404);
        }

        $tenant->load([
            'subscriptionPlan',
            'subscriptions' => fn($q) => $q->orderByDesc('created_at')->limit(10),
            'mikrotikConnections',
            'pppUsers',
            'tenantSettings',
        ]);

        $stats = [
            'mikrotik_count' => $tenant->mikrotikConnections()->count(),
            'ppp_users_count' => $tenant->pppUsers()->count(),
            'active_ppp_users' => $tenant->pppUsers()->where('status_akun', 'enable')->count(),
            'invoices_count' => $tenant->invoices()->count(),
            'unpaid_invoices' => $tenant->invoices()->unpaid()->count(),
            'total_revenue' => $tenant->invoices()->paid()->sum('total'),
        ];

        return view('super-admin.tenants.show', compact('tenant', 'stats'));
    }

    public function editTenant(User $tenant)
    {
        if ($tenant->isSuperAdmin()) {
            abort(404);
        }

        $plans = SubscriptionPlan::active()->orderBy('sort_order')->get();

        return view('super-admin.tenants.edit', compact('tenant', 'plans'));
    }

    public function updateTenant(Request $request, User $tenant)
    {
        if ($tenant->isSuperAdmin()) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $tenant->id,
            'phone' => 'nullable|string|max:20',
            'company_name' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:1000',
            'subscription_status' => 'required|in:trial,active,expired,suspended',
            'subscription_plan_id' => 'nullable|exists:subscription_plans,id',
            'subscription_expires_at' => 'nullable|date',
            'trial_days_remaining' => 'nullable|integer|min:0',
            'vpn_enabled' => 'boolean',
            'vpn_username' => 'nullable|string|max:100',
            'vpn_password' => 'nullable|string|max:100',
            'vpn_ip' => 'nullable|string|max:45',
        ]);

        $tenant->update($validated);

        return redirect()->route('super-admin.tenants.show', $tenant)
            ->with('success', 'Data tenant berhasil diperbarui.');
    }

    public function activateTenant(Request $request, User $tenant)
    {
        $request->validate([
            'plan_id' => 'required|exists:subscription_plans,id',
            'duration_days' => 'nullable|integer|min:1',
        ]);

        $plan = SubscriptionPlan::findOrFail($request->plan_id);
        $duration = $request->duration_days ?? $plan->duration_days;

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
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $tenant->update(['subscription_status' => 'suspended']);

        return back()->with('success', 'Tenant berhasil disuspend.');
    }

    public function extendTenant(Request $request, User $tenant)
    {
        $request->validate([
            'days' => 'required|integer|min:1|max:365',
        ]);

        $tenant->extendSubscription($request->days);

        return back()->with('success', "Langganan diperpanjang {$request->days} hari.");
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
            'trial_days' => 'nullable|integer|min:0|max:90',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
            'company_name' => $validated['company_name'] ?? null,
            'role' => 'administrator',
            'is_super_admin' => false,
            'subscription_status' => 'trial',
            'subscription_plan_id' => $validated['subscription_plan_id'] ?? null,
            'trial_days_remaining' => $validated['trial_days'] ?? 14,
            'registered_at' => now(),
        ]);

        // Create default settings
        $user->getSettings();

        return redirect()->route('super-admin.tenants.show', $user)
            ->with('success', 'Tenant berhasil dibuat.');
    }

    public function deleteTenant(User $tenant)
    {
        if ($tenant->isSuperAdmin()) {
            abort(404);
        }

        // This should cascade delete all related data
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
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->sum('amount');

        $dailyRevenue = Payment::paid()
            ->forSubscription()
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->selectRaw('DATE(paid_at) as date, SUM(amount) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $revenueByPlan = Payment::paid()
            ->forSubscription()
            ->whereBetween('paid_at', [$startDate, $endDate])
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
        return view('super-admin.tenants.vpn', compact('tenant'));
    }

    public function updateVpnSettings(Request $request, User $tenant)
    {
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
        $username = 'tenant_' . $tenant->id;
        $password = \Str::random(16);

        $tenant->update([
            'vpn_username' => $username,
            'vpn_password' => $password,
        ]);

        // TODO: Integrate with OpenVPN to create the VPN user

        return back()->with('success', 'Kredensial VPN berhasil dibuat.');
    }
}
