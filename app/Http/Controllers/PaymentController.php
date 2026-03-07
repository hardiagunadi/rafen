<?php

namespace App\Http\Controllers;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\TenantSettings;
use App\Services\DuitkuService;
use App\Services\MidtransService;
use App\Services\TripayService;
use App\Services\WaNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    private function resolveGateway(TenantSettings $settings): PaymentGatewayInterface
    {
        return match ($settings->getActiveGateway()) {
            'midtrans' => MidtransService::forTenant($settings),
            'duitku'   => DuitkuService::forTenant($settings),
            default    => TripayService::forTenant($settings),
        };
    }

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
        if (!$user->isSuperAdmin() && $invoice->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        if ($invoice->isPaid()) {
            return redirect()->route('invoices.show', $invoice)
                ->with('error', 'Invoice sudah dibayar.');
        }

        // Get tenant settings
        $settings      = $invoice->owner->getSettings();
        $manualEnabled = (bool) $settings->enable_manual_payment;
        $bankAccounts  = $invoice->owner->bankAccounts()->active()->get();

        if (!$settings->hasPaymentGateway()) {
            // No Tripay — only show manual transfer if enabled
            if (!$manualEnabled || $bankAccounts->isEmpty()) {
                return redirect()->route('invoices.show', $invoice)
                    ->with('error', 'Tidak ada metode pembayaran yang tersedia. Hubungi admin.');
            }
            return view('payments.manual', compact('invoice', 'settings', 'bankAccounts'));
        }

        // Get available channels from active gateway
        $gateway     = $this->resolveGateway($settings);
        $allChannels = $gateway->getPaymentChannels();

        // Filter to only enabled channels
        $enabledCodes = $settings->getEnabledChannels();
        $channels = empty($enabledCodes)
            ? $allChannels
            : array_values(array_filter($allChannels, fn($ch) => in_array($ch['code'], $enabledCodes)));

        // Group channels (Tripay has static groups; other gateways return flat list)
        $groupedChannels = [];
        if ($settings->getActiveGateway() === 'tripay') {
            foreach (TripayService::getChannelGroups() as $groupKey => $group) {
                $groupChannels = array_filter($channels, fn($ch) => in_array($ch['code'], $group['codes']));
                if (!empty($groupChannels)) {
                    $groupedChannels[$groupKey] = [
                        'name'        => $group['name'],
                        'description' => $group['description'],
                        'channels'    => array_values($groupChannels),
                    ];
                }
            }
        } else {
            // For other gateways, group by type (QRIS vs VA)
            $qris = array_values(array_filter($channels, fn($ch) => str_contains(strtolower($ch['type'] ?? $ch['name'] ?? ''), 'qris')));
            $va   = array_values(array_filter($channels, fn($ch) => !str_contains(strtolower($ch['type'] ?? $ch['name'] ?? ''), 'qris')));
            if (!empty($qris)) {
                $groupedChannels['qris'] = ['name' => 'QRIS', 'description' => 'Bayar via QRIS', 'channels' => $qris];
            }
            if (!empty($va)) {
                $groupedChannels['va'] = ['name' => 'Virtual Account', 'description' => 'Transfer ke Virtual Account', 'channels' => $va];
            }
            if (empty($groupedChannels)) {
                $groupedChannels['other'] = ['name' => 'Metode Pembayaran', 'description' => '', 'channels' => $channels];
            }
        }

        return view('payments.create', compact('invoice', 'channels', 'groupedChannels', 'settings', 'manualEnabled', 'bankAccounts'));
    }

    public function storeForInvoice(Request $request, Invoice $invoice)
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && $invoice->owner_id !== $user->effectiveOwnerId()) {
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
        $gateway  = $this->resolveGateway($settings);

        $result = $gateway->createInvoicePayment(
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

            if ($payment->payment_type === 'invoice' && $payment->invoice) {
                $invSettings = TenantSettings::getOrCreate((int) $payment->invoice->owner_id);
                WaNotificationService::notifyInvoicePaid($invSettings, $payment->invoice->fresh()->load('pppUser'));
            }
        } elseif ($status === 'EXPIRED') {
            $payment->markAsExpired();
            Log::info('Payment marked as expired', ['payment_id' => $payment->id]);
        } elseif ($status === 'FAILED') {
            $payment->markAsFailed();
            Log::info('Payment marked as failed', ['payment_id' => $payment->id]);
        }

        return response()->json(['success' => true]);
    }

    public function callbackMidtrans(Request $request)
    {
        $callbackData = $request->all();
        Log::info('Midtrans callback received', $callbackData);

        $orderId = $callbackData['order_id'] ?? '';

        $payment = Payment::where('merchant_ref', $orderId)->first();
        if (!$payment) {
            Log::warning('Midtrans: payment not found', ['order_id' => $orderId]);
            return response()->json(['success' => false, 'message' => 'Payment not found'], 404);
        }

        $settings = null;
        if ($payment->payment_type === 'invoice' && $payment->invoice) {
            $settings = $payment->invoice->owner->getSettings();
        }

        $midtrans = $settings ? MidtransService::forTenant($settings) : MidtransService::forSystem();

        if (!$midtrans->verifyCallback($callbackData)) {
            Log::warning('Midtrans: invalid signature', ['order_id' => $orderId]);
            return response()->json(['success' => false, 'message' => 'Invalid signature'], 400);
        }

        $transactionStatus = $callbackData['transaction_status'] ?? '';
        $fraudStatus       = $callbackData['fraud_status'] ?? 'accept';

        if (in_array($transactionStatus, ['capture', 'settlement']) && $fraudStatus !== 'deny') {
            $payment->markAsPaid($callbackData);
            Log::info('Midtrans: payment marked as paid', ['payment_id' => $payment->id]);

            if ($payment->payment_type === 'invoice' && $payment->invoice) {
                $invSettings = TenantSettings::getOrCreate((int) $payment->invoice->owner_id);
                WaNotificationService::notifyInvoicePaid($invSettings, $payment->invoice->fresh()->load('pppUser'));
            }
        } elseif (in_array($transactionStatus, ['cancel', 'deny', 'expire'])) {
            $payment->markAsExpired();
            Log::info('Midtrans: payment expired/cancelled', ['payment_id' => $payment->id]);
        }

        return response()->json(['success' => true]);
    }

    public function callbackDuitku(Request $request)
    {
        $callbackData = $request->all();
        Log::info('Duitku callback received', $callbackData);

        $merchantOrderId = $callbackData['merchantOrderId'] ?? '';
        $resultCode      = $callbackData['resultCode'] ?? '';

        $payment = Payment::where('merchant_ref', $merchantOrderId)->first();
        if (!$payment) {
            Log::warning('Duitku: payment not found', ['merchantOrderId' => $merchantOrderId]);
            return response()->json(['success' => false, 'message' => 'Payment not found'], 404);
        }

        $settings = null;
        if ($payment->payment_type === 'invoice' && $payment->invoice) {
            $settings = $payment->invoice->owner->getSettings();
        }

        $duitku = $settings ? DuitkuService::forTenant($settings) : DuitkuService::forSystem();

        if (!$duitku->verifyCallback($callbackData)) {
            Log::warning('Duitku: invalid signature', ['merchantOrderId' => $merchantOrderId]);
            return response()->json(['success' => false, 'message' => 'Invalid signature'], 400);
        }

        if ($resultCode === '00') {
            $payment->markAsPaid($callbackData);
            Log::info('Duitku: payment marked as paid', ['payment_id' => $payment->id]);

            if ($payment->payment_type === 'invoice' && $payment->invoice && $settings) {
                WaNotificationService::notifyInvoicePaid($settings, $payment->invoice->fresh()->load('pppUser'));
            }
        } elseif (in_array($resultCode, ['01', '02'])) {
            $payment->markAsFailed();
            Log::info('Duitku: payment failed', ['payment_id' => $payment->id, 'resultCode' => $resultCode]);
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
            $gateway  = $this->resolveGateway($settings);
        } else {
            $gateway = TripayService::forSystem();
        }

        $result = $gateway->getTransactionDetail($payment->reference);

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

    public function manualForm(Request $request, Invoice $invoice)
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && $invoice->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        if ($invoice->isPaid()) {
            return redirect()->route('invoices.show', $invoice)->with('error', 'Invoice sudah dibayar.');
        }

        $settings     = $invoice->owner->getSettings();
        $bankAccounts = $invoice->owner->bankAccounts()->active()->get();

        if (!$settings->enable_manual_payment || $bankAccounts->isEmpty()) {
            return redirect()->route('invoices.show', $invoice)->with('error', 'Pembayaran manual tidak tersedia.');
        }

        return view('payments.manual', compact('invoice', 'settings', 'bankAccounts'));
    }

    public function manualConfirmation(Request $request, Invoice $invoice)
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && $invoice->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        $settings = $invoice->owner->getSettings();
        if (!$settings->enable_manual_payment) {
            return redirect()->route('invoices.show', $invoice)->with('error', 'Pembayaran manual tidak tersedia.');
        }

        $request->validate([
            'payment_proof'      => 'required|image|max:5120',
            'bank_account_id'    => 'required|exists:bank_accounts,id',
            'amount_transferred' => 'required|numeric|min:0',
            'transfer_date'      => 'required|date',
            'notes'              => 'nullable|string|max:500',
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
            if (!$invoice || $invoice->owner_id !== $user->effectiveOwnerId()) {
                abort(403);
            }
        }

        $payment->markAsPaid([
            'confirmed_by' => $user->id,
            'confirmed_at' => now()->toIso8601String(),
        ]);

        if ($payment->invoice) {
            $invSettings = TenantSettings::getOrCreate((int) $payment->invoice->owner_id);
            WaNotificationService::notifyInvoicePaid($invSettings, $payment->invoice->fresh()->load('pppUser'));
        }

        return back()->with('success', 'Pembayaran berhasil dikonfirmasi.');
    }

    public function rejectManual(Request $request, Payment $payment)
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin()) {
            $invoice = $payment->invoice;
            if (!$invoice || $invoice->owner_id !== $user->effectiveOwnerId()) {
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
