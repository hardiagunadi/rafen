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

        $subscriptions = $user->subscriptions()
            ->with('plan')
            ->orderByDesc('created_at')
            ->paginate(10);

        $currentSubscription = $user->activeSubscription;
        $plans = SubscriptionPlan::active()->orderBy('sort_order')->get();

        return view('subscription.index', compact('subscriptions', 'currentSubscription', 'plans', 'user'));
    }

    public function plans()
    {
        $plans = SubscriptionPlan::active()->orderBy('sort_order')->get();

        return view('subscription.plans', compact('plans'));
    }

    public function subscribe(Request $request, SubscriptionPlan $plan)
    {
        $user = $request->user();

        // Create pending subscription
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'start_date' => now(),
            'end_date' => now()->addDays($plan->duration_days),
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
        $channels = array_filter($channels, fn($ch) => in_array($ch['code'], $enabledCodes));

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
        if (!$tripay->verifyCallback($callbackData)) {
            return response()->json(['success' => false, 'message' => 'Invalid signature'], 400);
        }

        $merchantRef = $callbackData['merchant_ref'] ?? '';
        $status = $callbackData['status'] ?? '';

        // Find payment by merchant reference
        $payment = \App\Models\Payment::where('merchant_ref', $merchantRef)->first();

        if (!$payment) {
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

        if (!$plan) {
            return redirect()->route('subscription.plans')
                ->with('error', 'Silakan pilih paket langganan.');
        }

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'start_date' => $user->subscription_expires_at ?? now(),
            'end_date' => ($user->subscription_expires_at ?? now())->addDays($plan->duration_days),
            'status' => 'pending',
            'amount_paid' => $plan->price,
        ]);

        return redirect()->route('subscription.payment', $subscription);
    }

    public function history(Request $request)
    {
        $user = $request->user();

        $payments = $user->payments()
            ->where('payment_type', 'subscription')
            ->with('subscription.plan')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('subscription.history', compact('payments'));
    }
}
