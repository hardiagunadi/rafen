<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\TripayService;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $currentSubscription = $user->activeSubscription;
        $plans = SubscriptionPlan::active()->orderBy('sort_order')->get();

        return view('subscription.index', compact('currentSubscription', 'plans', 'user'));
    }

    public function subscriptionsDatatable(Request $request)
    {
        $user = $request->user();
        $search = $request->input('search.value', '');

        $query = $user->subscriptions()
            ->with('plan')
            ->when($search !== '', fn ($q) => $q->whereHas('plan', fn ($q2) => $q2->where('name', 'like', "%{$search}%")))
            ->orderByDesc('created_at');

        $total = $user->subscriptions()->count();
        $filtered = $query->count();
        $rows = $query->offset($request->integer('start'))
            ->limit(max(1, $request->integer('length', 10)))
            ->get();

        $statusLabels = [
            'active' => '<span class="badge badge-success">Aktif</span>',
            'pending' => '<span class="badge badge-warning">Menunggu Pembayaran</span>',
            'expired' => '<span class="badge badge-secondary">Berakhir</span>',
            'cancelled' => '<span class="badge badge-danger">Dibatalkan</span>',
        ];

        return response()->json([
            'draw' => $request->integer('draw'),
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $rows->map(fn ($r) => [
                'plan' => $r->plan->name ?? '-',
                'start_date' => $r->start_date->format('d M Y'),
                'end_date' => $r->end_date->format('d M Y'),
                'status' => $statusLabels[$r->status] ?? $r->status,
                'amount' => 'Rp '.number_format($r->amount_paid, 0, ',', '.'),
            ]),
        ]);
    }

    public function plans()
    {
        $plans = SubscriptionPlan::active()->orderBy('sort_order')->get();
        $user = auth()->user();

        return view('subscription.plans', compact('plans', 'user'));
    }

    public function subscribe(Request $request, SubscriptionPlan $plan)
    {
        $user = $request->user();
        $durationDays = $user->resolveSubscriptionDurationDays($plan);

        // Create pending subscription
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'start_date' => now(),
            'end_date' => now()->addDays($durationDays),
            'status' => 'pending',
            'amount_paid' => $plan->price,
        ]);

        return redirect()->route('subscription.payment', $subscription);
    }

    public function payment(Subscription $subscription)
    {
        if ($subscription->user_id !== auth()->id()) {
            abort(403);
        }

        if ($subscription->status !== 'pending') {
            return redirect()->route('subscription.index')
                ->with('error', 'Langganan ini sudah diproses.');
        }

        $tripay = TripayService::forSystem();
        $channels = $tripay->getPaymentChannels();

        // Filter only enabled channels
        $enabledCodes = config('tripay.enabled_channels', []);
        $channels = array_filter($channels, fn ($ch) => in_array($ch['code'], $enabledCodes));

        return view('subscription.payment', compact('subscription', 'channels'));
    }

    public function processPayment(Request $request, Subscription $subscription)
    {
        if ($subscription->user_id !== auth()->id()) {
            abort(403);
        }

        if ($subscription->status !== 'pending') {
            return redirect()->route('subscription.index')
                ->with('error', 'Langganan ini sudah diproses.');
        }

        $request->validate([
            'payment_channel' => 'required|string',
        ]);

        $tripay = TripayService::forSystem();
        $result = $tripay->createSubscriptionPayment(
            $subscription,
            $request->payment_channel
        );

        if ($result['success']) {
            $payment = $result['payment'];
            $data = $result['data'];

            return view('subscription.payment-detail', compact('subscription', 'payment', 'data'));
        }

        return back()->with('error', $result['message'] ?? 'Gagal membuat pembayaran.');
    }

    public function paymentCallback(Request $request)
    {
        $callbackData = $request->all();

        $tripay = TripayService::forSystem();

        // Verify signature
        if (! $tripay->verifyCallback($callbackData)) {
            return response()->json(['success' => false, 'message' => 'Invalid signature'], 400);
        }

        $merchantRef = $callbackData['merchant_ref'] ?? '';
        $status = $callbackData['status'] ?? '';

        // Find payment by merchant reference
        $payment = \App\Models\Payment::where('merchant_ref', $merchantRef)->first();

        if (! $payment) {
            return response()->json(['success' => false, 'message' => 'Payment not found'], 404);
        }

        if ($status === 'PAID') {
            $payment->markAsPaid($callbackData);
        } elseif ($status === 'EXPIRED') {
            $payment->markAsExpired();
        } elseif ($status === 'FAILED') {
            $payment->markAsFailed();
        }

        return response()->json(['success' => true]);
    }

    public function expired()
    {
        $user = auth()->user();
        $plans = SubscriptionPlan::active()->orderBy('sort_order')->get();

        return view('subscription.expired', compact('user', 'plans'));
    }

    public function renew(Request $request)
    {
        $user = $request->user();
        $plan = $user->subscriptionPlan ?? SubscriptionPlan::active()->first();

        if (! $plan) {
            return redirect()->route('subscription.plans')
                ->with('error', 'Silakan pilih paket langganan.');
        }

        $durationDays = $user->resolveSubscriptionDurationDays($plan);
        $startDate = $user->subscription_expires_at ?? now();

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'start_date' => $startDate,
            'end_date' => $startDate->copy()->addDays($durationDays),
            'status' => 'pending',
            'amount_paid' => $plan->price,
        ]);

        return redirect()->route('subscription.payment', $subscription);
    }

    public function history(Request $request)
    {
        return view('subscription.history');
    }

    public function historyDatatable(Request $request)
    {
        $user = $request->user();
        $search = $request->input('search.value', '');

        $query = $user->payments()
            ->where('payment_type', 'subscription')
            ->with('subscription.plan')
            ->when($search !== '', fn ($q) => $q->where(function ($q2) use ($search) {
                $q2->where('payment_number', 'like', "%{$search}%")
                    ->orWhere('payment_channel', 'like', "%{$search}%");
            }))
            ->orderByDesc('created_at');

        $total = $user->payments()->where('payment_type', 'subscription')->count();
        $filtered = $query->count();
        $rows = $query->offset($request->integer('start'))
            ->limit(max(1, $request->integer('length', 20)))
            ->get();

        $statusLabels = [
            'paid' => '<span class="badge badge-success">Dibayar</span>',
            'pending' => '<span class="badge badge-warning">Menunggu</span>',
            'expired' => '<span class="badge badge-secondary">Kedaluwarsa</span>',
            'failed' => '<span class="badge badge-danger">Gagal</span>',
        ];

        return response()->json([
            'draw' => $request->integer('draw'),
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $rows->map(fn ($r) => [
                'payment_number' => $r->payment_number,
                'plan' => $r->subscription?->plan?->name ?? '-',
                'payment_channel' => $r->payment_channel ?? '-',
                'total_amount' => 'Rp '.number_format($r->total_amount, 0, ',', '.'),
                'status' => $statusLabels[$r->status] ?? $r->status,
                'created_at' => $r->created_at->format('d M Y H:i'),
            ]),
        ]);
    }
}
