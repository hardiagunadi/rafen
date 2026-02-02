<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\TenantSettings;
use App\Services\TripayService;
use Illuminate\Http\Request;

class TenantSettingsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $settings = $user->getSettings();
        $bankAccounts = $user->bankAccounts()->orderBy('is_primary', 'desc')->get();

        return view('tenant-settings.index', compact('settings', 'bankAccounts'));
    }

    public function updateBusiness(Request $request)
    {
        $validated = $request->validate([
            'business_name' => 'nullable|string|max:255',
            'business_phone' => 'nullable|string|max:20',
            'business_email' => 'nullable|email|max:255',
            'business_address' => 'nullable|string|max:1000',
            'invoice_prefix' => 'nullable|string|max:10',
            'invoice_footer' => 'nullable|string|max:1000',
            'invoice_notes' => 'nullable|string|max:1000',
        ]);

        $user = $request->user();
        $settings = $user->getSettings();
        $settings->update($validated);

        return back()->with('success', 'Pengaturan bisnis berhasil diperbarui.');
    }

    public function updatePayment(Request $request)
    {
        $validated = $request->validate([
            'enable_qris_payment' => 'boolean',
            'enable_va_payment' => 'boolean',
            'enable_manual_payment' => 'boolean',
            'tripay_api_key' => 'nullable|string|max:255',
            'tripay_private_key' => 'nullable|string|max:255',
            'tripay_merchant_code' => 'nullable|string|max:50',
            'tripay_sandbox' => 'boolean',
            'enabled_payment_channels' => 'nullable|array',
            'payment_expiry_hours' => 'integer|min:1|max:168',
            'auto_isolate_unpaid' => 'boolean',
            'grace_period_days' => 'integer|min:0|max:30',
        ]);

        $user = $request->user();
        $settings = $user->getSettings();
        $settings->update($validated);

        return back()->with('success', 'Pengaturan pembayaran berhasil diperbarui.');
    }

    public function testTripay(Request $request)
    {
        $user = $request->user();
        $settings = $user->getSettings();

        if (!$settings->hasTripayConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Kredensial Tripay belum dikonfigurasi.',
            ]);
        }

        $tripay = TripayService::forTenant($settings);
        $channels = $tripay->getPaymentChannels();

        if (empty($channels)) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal terhubung ke Tripay. Periksa kredensial Anda.',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Koneksi Tripay berhasil!',
            'channels' => $channels,
        ]);
    }

    public function getPaymentChannels(Request $request)
    {
        $user = $request->user();
        $settings = $user->getSettings();

        if (!$settings->hasTripayConfigured()) {
            return response()->json([
                'success' => false,
                'channels' => [],
            ]);
        }

        $tripay = TripayService::forTenant($settings);
        $channels = $tripay->getPaymentChannels();

        // Group channels
        $groupedChannels = [];
        foreach (TripayService::getChannelGroups() as $key => $group) {
            $groupChannels = array_filter($channels, fn($ch) => in_array($ch['code'], $group['codes']));
            if (!empty($groupChannels)) {
                $groupedChannels[$key] = [
                    'name' => $group['name'],
                    'description' => $group['description'],
                    'channels' => array_values($groupChannels),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'channels' => $channels,
            'grouped' => $groupedChannels,
        ]);
    }

    // Bank Account Management

    public function storeBankAccount(Request $request)
    {
        $validated = $request->validate([
            'bank_name' => 'required|string|max:100',
            'bank_code' => 'nullable|string|max:20',
            'account_number' => 'required|string|max:50',
            'account_name' => 'required|string|max:255',
            'branch' => 'nullable|string|max:255',
            'is_primary' => 'boolean',
        ]);

        $user = $request->user();

        $bankAccount = $user->bankAccounts()->create($validated);

        if ($validated['is_primary'] ?? false) {
            $bankAccount->setAsPrimary();
        }

        return back()->with('success', 'Rekening bank berhasil ditambahkan.');
    }

    public function updateBankAccount(Request $request, BankAccount $bankAccount)
    {
        $user = $request->user();

        if ($bankAccount->user_id !== $user->id) {
            abort(403);
        }

        $validated = $request->validate([
            'bank_name' => 'required|string|max:100',
            'bank_code' => 'nullable|string|max:20',
            'account_number' => 'required|string|max:50',
            'account_name' => 'required|string|max:255',
            'branch' => 'nullable|string|max:255',
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $bankAccount->update($validated);

        if ($validated['is_primary'] ?? false) {
            $bankAccount->setAsPrimary();
        }

        return back()->with('success', 'Rekening bank berhasil diperbarui.');
    }

    public function destroyBankAccount(BankAccount $bankAccount)
    {
        $user = auth()->user();

        if ($bankAccount->user_id !== $user->id) {
            abort(403);
        }

        $bankAccount->delete();

        return back()->with('success', 'Rekening bank berhasil dihapus.');
    }

    public function setPrimaryBankAccount(BankAccount $bankAccount)
    {
        $user = auth()->user();

        if ($bankAccount->user_id !== $user->id) {
            abort(403);
        }

        $bankAccount->setAsPrimary();

        return back()->with('success', 'Rekening utama berhasil diubah.');
    }

    public function uploadLogo(Request $request)
    {
        $request->validate([
            'business_logo' => 'required|image|max:2048',
        ]);

        $user = $request->user();
        $settings = $user->getSettings();

        // Delete old logo
        if ($settings->business_logo) {
            \Storage::disk('public')->delete($settings->business_logo);
        }

        $path = $request->file('business_logo')->store('business-logos', 'public');

        $settings->update(['business_logo' => $path]);

        return back()->with('success', 'Logo bisnis berhasil diunggah.');
    }
}
