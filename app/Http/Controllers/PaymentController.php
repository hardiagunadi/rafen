<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\TenantSettings;
use App\Services\TripayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Payment::query();

        if (!$user->isSuperAdmin()) {
            $query->where('user_id', $user->id);
        }

        $payments = $query->with(['invoice', 'subscription', 'gateway'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('payments.index', compact('payments'));
    }

    public function show(Payment $payment)
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && $payment->user_id !== $user->id) {
            abort(403);
        }

        return view('payments.show', compact('payment'));
    }

    public function createForInvoice(Request $request, Invoice $invoice)
    {
        $user = auth()->user();

        // Check ownership
        if (!$user->isSuperAdmin() && $invoice->owner_id !== $user->id) {
            abort(403);
        }

        if ($invoice->isPaid()) {
            return redirect()->route('invoices.show', $invoice)
                ->with('error', 'Invoice sudah dibayar.');
        }

        // Get tenant settings
        $settings = $invoice->owner->getSettings();

        if (!$settings->hasPaymentGateway()) {
            return view('payments.manual', compact('invoice', 'settings'));
        }

        // Get available channels
        $tripay = TripayService::forTenant($settings);
        $allChannels = $tripay->getPaymentChannels();

        // Filter to only enabled channels
        $enabledCodes = $settings->getEnabledChannels();
        $channels = [];

        foreach ($allChannels as $channel) {
            if (in_array($channel['code'], $enabledCodes)) {
                $channels[] = $channel;
            }
        }

        // Group channels
        $channelGroups = TripayService::getChannelGroups();
        $groupedChannels = [];

        foreach ($channelGroups as $groupKey => $group) {
            $groupChannels = array_filter($channels, fn($ch) => in_array($ch['code'], $group['codes']));
            if (!empty($groupChannels)) {
                $groupedChannels[$groupKey] = [
                    'name' => $group['name'],
                    'description' => $group['description'],
                    'channels' => array_values($groupChannels),
                ];
            }
        }

        return view('payments.create', compact('invoice', 'channels', 'groupedChannels', 'settings'));
    }

    public function storeForInvoice(Request $request, Invoice $invoice)
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && $invoice->owner_id !== $user->id) {
            abort(403);
        }

        if ($invoice->isPaid()) {
            return redirect()->route('invoices.show', $invoice)
                ->with('error', 'Invoice sudah dibayar.');
        }

        $request->validate([
            'payment_channel' => 'required|string',
        ]);

        $settings = $invoice->owner->getSettings();
        $tripay = TripayService::forTenant($settings);

        $result = $tripay->createInvoicePayment(
            $invoice,
            $request->payment_channel,
            $settings->payment_expiry_hours
        );

        if ($result['success']) {
            $payment = $result['payment'];
            $data = $result['data'];

            return view('payments.detail', compact('invoice', 'payment', 'data'));
        }

        return back()->with('error', $result['message'] ?? 'Gagal membuat pembayaran.');
    }

    public function callback(Request $request)
    {
        Log::info('Payment callback received', $request->all());

        $callbackData = $request->all();
        $merchantRef = $callbackData['merchant_ref'] ?? '';
        $status = $callbackData['status'] ?? '';

        // Find payment by merchant reference
        $payment = Payment::where('merchant_ref', $merchantRef)->first();

        if (!$payment) {
            Log::warning('Payment not found for callback', ['merchant_ref' => $merchantRef]);
            return response()->json(['success' => false, 'message' => 'Payment not found'], 404);
        }

        // Get settings to verify signature
        if ($payment->payment_type === 'invoice' && $payment->invoice) {
            $settings = $payment->invoice->owner->getSettings();
            $tripay = TripayService::forTenant($settings);
        } else {
            $tripay = TripayService::forSystem();
        }

        // Verify signature
        if (!$tripay->verifyCallback($callbackData)) {
            Log::warning('Invalid callback signature', $callbackData);
            return response()->json(['success' => false, 'message' => 'Invalid signature'], 400);
        }

        if ($status === 'PAID') {
            $payment->markAsPaid($callbackData);
            Log::info('Payment marked as paid', ['payment_id' => $payment->id]);
        } elseif ($status === 'EXPIRED') {
            $payment->markAsExpired();
            Log::info('Payment marked as expired', ['payment_id' => $payment->id]);
        } elseif ($status === 'FAILED') {
            $payment->markAsFailed();
            Log::info('Payment marked as failed', ['payment_id' => $payment->id]);
        }

        return response()->json(['success' => true]);
    }

    public function checkStatus(Payment $payment)
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && $payment->user_id !== $user->id) {
            abort(403);
        }

        if (!$payment->reference) {
            return response()->json(['status' => $payment->status]);
        }

        // Get settings
        if ($payment->payment_type === 'invoice' && $payment->invoice) {
            $settings = $payment->invoice->owner->getSettings();
            $tripay = TripayService::forTenant($settings);
        } else {
            $tripay = TripayService::forSystem();
        }

        $result = $tripay->getTransactionDetail($payment->reference);

        if ($result['success']) {
            $data = $result['data'];
            $gatewayStatus = $data['status'] ?? '';

            if ($gatewayStatus === 'PAID' && $payment->status !== 'paid') {
                $payment->markAsPaid($data);
            } elseif ($gatewayStatus === 'EXPIRED' && $payment->status !== 'expired') {
                $payment->markAsExpired();
            }
        }

        return response()->json(['status' => $payment->fresh()->status]);
    }

    public function success(Request $request)
    {
        return view('payments.success');
    }

    public function manualConfirmation(Request $request, Invoice $invoice)
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && $invoice->owner_id !== $user->id) {
            abort(403);
        }

        $request->validate([
            'payment_proof' => 'required|image|max:5120',
            'bank_account_id' => 'required|exists:bank_accounts,id',
            'amount_transferred' => 'required|numeric|min:0',
            'transfer_date' => 'required|date',
            'notes' => 'nullable|string|max:500',
        ]);

        // Store proof
        $path = $request->file('payment_proof')->store('payment-proofs', 'public');

        $payment = Payment::create([
            'payment_number' => Payment::generatePaymentNumber(),
            'payment_type' => 'invoice',
            'user_id' => $invoice->owner_id,
            'invoice_id' => $invoice->id,
            'payment_method' => 'bank_transfer',
            'payment_channel' => 'manual',
            'amount' => $invoice->total,
            'fee' => 0,
            'total_amount' => $invoice->total,
            'status' => 'pending',
            'merchant_ref' => 'MANUAL-' . $invoice->id . '-' . time(),
            'notes' => "Bukti transfer: {$path}\nJumlah: {$request->amount_transferred}\nTanggal: {$request->transfer_date}\nCatatan: {$request->notes}",
        ]);

        return redirect()->route('invoices.show', $invoice)
            ->with('success', 'Bukti pembayaran berhasil dikirim. Menunggu konfirmasi.');
    }

    public function confirmManual(Request $request, Payment $payment)
    {
        $user = auth()->user();

        // Only owner or super admin can confirm
        if (!$user->isSuperAdmin()) {
            $invoice = $payment->invoice;
            if (!$invoice || $invoice->owner_id !== $user->id) {
                abort(403);
            }
        }

        $payment->markAsPaid([
            'confirmed_by' => $user->id,
            'confirmed_at' => now()->toIso8601String(),
        ]);

        return back()->with('success', 'Pembayaran berhasil dikonfirmasi.');
    }

    public function rejectManual(Request $request, Payment $payment)
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin()) {
            $invoice = $payment->invoice;
            if (!$invoice || $invoice->owner_id !== $user->id) {
                abort(403);
            }
        }

        $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        $payment->update([
            'status' => 'failed',
            'notes' => $payment->notes . "\n\nDitolak: " . $request->rejection_reason,
        ]);

        return back()->with('success', 'Pembayaran ditolak.');
    }
}
