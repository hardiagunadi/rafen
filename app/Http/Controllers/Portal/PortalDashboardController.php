<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\PppUser;
use App\Models\TenantSettings;
use App\Models\WaConversation;
use App\Models\WaTicket;
use App\Services\WaGatewayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class PortalDashboardController extends Controller
{
    private function getPppUser(Request $request): PppUser
    {
        return $request->attributes->get('portal_ppp_user');
    }

    public function index(Request $request)
    {
        $pppUser = $this->getPppUser($request);
        $pppUser->load(['profile', 'owner.tenantSettings']);

        $latestInvoice = Invoice::where('ppp_user_id', $pppUser->id)
            ->orderByDesc('due_date')
            ->first();

        if ($latestInvoice && empty($latestInvoice->payment_token)) {
            $latestInvoice->update(['payment_token' => Invoice::generatePaymentToken()]);
            $latestInvoice->refresh();
        }

        return view('portal.dashboard', compact('pppUser', 'latestInvoice'));
    }

    public function invoices(Request $request)
    {
        $pppUser = $this->getPppUser($request);
        $pppUser->load(['owner.tenantSettings']);

        $invoices = Invoice::where('ppp_user_id', $pppUser->id)
            ->orderByDesc('due_date')
            ->paginate(15);

        // Ensure each invoice has a payment token
        foreach ($invoices as $invoice) {
            if (empty($invoice->payment_token)) {
                $invoice->update(['payment_token' => Invoice::generatePaymentToken()]);
            }
        }

        return view('portal.invoices', compact('pppUser', 'invoices'));
    }

    public function account(Request $request)
    {
        $pppUser = $this->getPppUser($request);
        $pppUser->load(['profile', 'owner.tenantSettings']);

        return view('portal.account', compact('pppUser'));
    }

    public function changePassword(Request $request)
    {
        $pppUser = $this->getPppUser($request);

        $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $storedPassword = $pppUser->password_clientarea;

        $valid = false;
        try {
            $valid = Hash::check($request->current_password, $storedPassword);
        } catch (\Throwable) {
        }
        if (! $valid) {
            $valid = $storedPassword === $request->current_password;
        }

        if (! $valid) {
            return response()->json(['success' => false, 'message' => 'Password lama tidak sesuai.'], 422);
        }

        $pppUser->update(['password_clientarea' => Hash::make($request->new_password)]);

        return response()->json(['success' => true, 'message' => 'Password berhasil diubah.']);
    }

    public function storeTicket(Request $request)
    {
        $pppUser = $this->getPppUser($request);

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'type' => ['required', 'string', 'in:complaint,installation,troubleshoot,other'],
        ]);

        $ownerId = $pppUser->owner_id;

        // Get or create conversation
        $conversation = WaConversation::firstOrCreate(
            ['owner_id' => $ownerId, 'contact_phone' => $pppUser->nomor_hp ?? ''],
            ['contact_name' => $pppUser->customer_name, 'status' => 'open']
        );

        $ticket = WaTicket::create([
            'owner_id' => $ownerId,
            'conversation_id' => $conversation->id,
            'title' => $data['subject'],
            'description' => $data['message'],
            'type' => $data['type'],
            'priority' => 'normal',
            'status' => 'open',
        ]);

        // Notify CS via WA
        try {
            $settings = TenantSettings::where('user_id', $ownerId)->first();
            if ($settings && $settings->hasWaConfigured() && $settings->business_phone) {
                $service = WaGatewayService::forTenant($settings);
                if ($service) {
                    $name = $pppUser->customer_name ?? $pppUser->nomor_hp;
                    $msg = "Tiket pengaduan baru dari portal pelanggan.\n\nPelanggan: {$name}\nJudul: {$ticket->title}\nTipe: {$ticket->type}\n\nCek dashboard untuk detail.";
                    $service->sendMessage($settings->business_phone, $msg, ['event' => 'ticket_from_portal']);
                }
            }
        } catch (\Throwable) {
            // Non-blocking
        }

        // Notify customer (confirmation)
        try {
            $settings = $settings ?? TenantSettings::where('user_id', $ownerId)->first();
            if ($settings && $settings->hasWaConfigured() && $pppUser->nomor_hp) {
                $service = WaGatewayService::forTenant($settings);
                if ($service) {
                    $msg = "Tiket pengaduan Anda #{$ticket->id} berhasil dibuat.\nJudul: {$ticket->title}\n\nTim kami akan segera menanganinya. Terima kasih.";
                    $service->sendMessage($pppUser->nomor_hp, $msg, ['event' => 'ticket_created_portal']);
                }
            }
        } catch (\Throwable) {
            // Non-blocking
        }

        return response()->json(['success' => true, 'ticket_id' => $ticket->id, 'message' => 'Tiket berhasil dibuat.']);
    }
}
