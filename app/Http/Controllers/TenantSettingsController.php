<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateTenantMapCacheRequest;
use App\Http\Requests\UpdateTenantModuleSettingsRequest;
use App\Models\BankAccount;
use App\Services\DuitkuService;
use App\Services\MidtransService;
use App\Services\TripayService;
use App\Services\WaGatewayService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
        if ($request->user()->isSubUser()) {
            abort(403);
        }

        $validated = $request->validate([
            'business_name' => 'nullable|string|max:255',
            'business_phone' => 'nullable|string|max:20',
            'business_email' => 'nullable|email|max:255',
            'business_address' => 'nullable|string|max:1000',
            'npwp' => 'nullable|string|max:30',
            'website' => 'nullable|url|max:255',
            'invoice_prefix' => 'nullable|string|max:10',
            'invoice_footer' => 'nullable|string|max:1000',
            'invoice_notes' => 'nullable|string|max:1000',
            'billing_date' => 'nullable|integer|min:1|max:28',
        ]);

        $user = $request->user();
        $settings = $user->getSettings();
        $settings->update($validated);

        return back()->with('success', 'Pengaturan bisnis berhasil diperbarui.');
    }

    public function updatePayment(Request $request)
    {
        if ($request->user()->isSubUser()) {
            abort(403);
        }

        $validated = $request->validate([
            'enable_qris_payment' => 'boolean',
            'enable_va_payment' => 'boolean',
            'enable_manual_payment' => 'boolean',
            'active_gateway' => 'nullable|string|in:tripay,midtrans,duitku,ipaymu,xendit',
            // Tripay
            'tripay_api_key' => 'nullable|string|max:255',
            'tripay_private_key' => 'nullable|string|max:255',
            'tripay_merchant_code' => 'nullable|string|max:50',
            'tripay_sandbox' => 'boolean',
            // Midtrans
            'midtrans_server_key' => 'nullable|string|max:255',
            'midtrans_client_key' => 'nullable|string|max:255',
            'midtrans_merchant_id' => 'nullable|string|max:50',
            'midtrans_sandbox' => 'boolean',
            // Duitku
            'duitku_merchant_code' => 'nullable|string|max:50',
            'duitku_api_key' => 'nullable|string|max:255',
            'duitku_sandbox' => 'boolean',
            // iPaymu
            'ipaymu_va' => 'nullable|string|max:50',
            'ipaymu_api_key' => 'nullable|string|max:255',
            'ipaymu_sandbox' => 'boolean',
            // Xendit
            'xendit_secret_key' => 'nullable|string|max:255',
            'xendit_webhook_token' => 'nullable|string|max:255',
            'xendit_sandbox' => 'boolean',
            // Common
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

    public function updateModules(UpdateTenantModuleSettingsRequest $request)
    {
        $settings = $request->user()->getSettings();
        $settings->update($request->validated());

        return back()->with('success', 'Pengaturan modul tenant berhasil diperbarui.');
    }

    public function updateMapCache(UpdateTenantMapCacheRequest $request)
    {
        $settings = $request->user()->getSettings();
        $validated = $request->validated();

        $mapCacheEnabled = filter_var($request->input('map_cache_enabled', false), FILTER_VALIDATE_BOOLEAN);
        $centerLatitude = $validated['map_cache_center_lat'] ?? null;
        $centerLongitude = $validated['map_cache_center_lng'] ?? null;
        $coverageRadiusKm = $validated['map_cache_radius_km'] ?? null;
        $minZoom = $validated['map_cache_min_zoom'] ?? null;
        $maxZoom = $validated['map_cache_max_zoom'] ?? null;

        $newConfig = [
            'map_cache_enabled' => $mapCacheEnabled,
            'map_cache_center_lat' => $centerLatitude !== null ? round((float) $centerLatitude, 7) : null,
            'map_cache_center_lng' => $centerLongitude !== null ? round((float) $centerLongitude, 7) : null,
            'map_cache_radius_km' => $coverageRadiusKm !== null ? round((float) $coverageRadiusKm, 2) : (float) ($settings->map_cache_radius_km ?? 3),
            'map_cache_min_zoom' => $minZoom ?? (int) ($settings->map_cache_min_zoom ?? 14),
            'map_cache_max_zoom' => $maxZoom ?? (int) ($settings->map_cache_max_zoom ?? 17),
        ];

        $previousConfig = [
            'map_cache_enabled' => (bool) $settings->map_cache_enabled,
            'map_cache_center_lat' => $settings->map_cache_center_lat !== null ? round((float) $settings->map_cache_center_lat, 7) : null,
            'map_cache_center_lng' => $settings->map_cache_center_lng !== null ? round((float) $settings->map_cache_center_lng, 7) : null,
            'map_cache_radius_km' => $settings->map_cache_radius_km !== null ? round((float) $settings->map_cache_radius_km, 2) : 3.0,
            'map_cache_min_zoom' => (int) ($settings->map_cache_min_zoom ?? 14),
            'map_cache_max_zoom' => (int) ($settings->map_cache_max_zoom ?? 17),
        ];

        if ($previousConfig !== $newConfig) {
            $newConfig['map_cache_version'] = max(1, (int) ($settings->map_cache_version ?? 1)) + 1;
        }

        $settings->update($newConfig);

        return back()->with('success', 'Pengaturan cache peta coverage berhasil diperbarui.');
    }

    public function testTripay(Request $request)
    {
        $user = $request->user();
        $settings = $user->getSettings();

        if (! $settings->hasTripayConfigured()) {
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

    public function testMidtrans(Request $request)
    {
        $user = $request->user();
        $settings = $user->getSettings();

        if (! $settings->hasMidtransConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Kredensial Midtrans belum dikonfigurasi.',
            ]);
        }

        $midtrans = MidtransService::forTenant($settings);
        $channels = $midtrans->getPaymentChannels();

        return response()->json([
            'success' => true,
            'message' => 'Koneksi Midtrans berhasil!',
            'channels' => $channels,
        ]);
    }

    public function testDuitku(Request $request)
    {
        $user = $request->user();
        $settings = $user->getSettings();

        if (! $settings->hasDuitkuConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Kredensial Duitku belum dikonfigurasi.',
            ]);
        }

        $duitku = DuitkuService::forTenant($settings);
        $channels = $duitku->getPaymentChannels();

        return response()->json([
            'success' => true,
            'message' => 'Koneksi Duitku berhasil! '.count($channels).' channel tersedia.',
            'channels' => $channels,
        ]);
    }

    public function getPaymentChannels(Request $request)
    {
        $user = $request->user();
        $settings = $user->getSettings();

        if (! $settings->hasTripayConfigured()) {
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
            $groupChannels = array_filter($channels, fn ($ch) => in_array($ch['code'], $group['codes']));
            if (! empty($groupChannels)) {
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

    public function waGateway(Request $request)
    {
        $user = $request->user();

        $tenants = null;
        $selectedTenant = null;

        if ($user->isSuperAdmin()) {
            $tenants = \App\Models\User::query()
                ->tenants()
                ->orderBy('name')
                ->get();

            $tenantId = $request->integer('tenant_id');
            if ($tenantId) {
                $selectedTenant = $tenants->firstWhere('id', $tenantId);
            }

            $settings = $selectedTenant
                ? \App\Models\TenantSettings::getOrCreate($selectedTenant->id)
                : null;
        } else {
            $settings = $user->getSettings();
        }

        return view('wa-gateway.index', compact('settings', 'tenants', 'selectedTenant'));
    }

    public function updateWa(Request $request)
    {
        $user = $request->user();

        if ($user->isSubUser()) {
            abort(403);
        }

        $validated = $request->validate([
            'wa_gateway_url' => 'nullable|url|max:255',
            'wa_gateway_token' => 'nullable|string|max:500|required_with:wa_gateway_url',
            'wa_gateway_key' => 'nullable|string|max:500',
            'wa_webhook_secret' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9_-]+$/'],
            'wa_notify_registration' => 'boolean',
            'wa_notify_invoice' => 'boolean',
            'wa_notify_payment' => 'boolean',
            'wa_broadcast_enabled' => 'boolean',
            'wa_antispam_enabled' => 'boolean',
            'wa_antispam_delay_ms' => 'integer|min:500|max:10000',
            'wa_antispam_max_per_minute' => 'integer|min:1|max:20',
            'wa_msg_randomize' => 'boolean',
            'wa_template_registration' => 'nullable|string|max:10000',
            'wa_template_invoice' => 'nullable|string|max:10000',
            'wa_template_payment' => 'nullable|string|max:10000',
            'wa_notify_on_process' => 'boolean',
            'wa_template_on_process' => 'nullable|string|max:10000',
            'tenant_id' => 'nullable|integer',
        ]);

        if ($user->isSuperAdmin() && ! empty($validated['tenant_id'])) {
            $tenant = \App\Models\User::query()
                ->tenants()
                ->where('id', $validated['tenant_id'])
                ->firstOrFail();
            $settings = \App\Models\TenantSettings::getOrCreate($tenant->id);
        } else {
            $settings = $user->getSettings();
        }

        unset($validated['tenant_id']);

        $gatewayUrl = trim((string) ($validated['wa_gateway_url'] ?? ''));
        $webhookSecret = trim((string) ($validated['wa_webhook_secret'] ?? ''));

        if ($gatewayUrl !== '' && $webhookSecret === '') {
            $validated['wa_webhook_secret'] = Str::random(40);
        }

        $settings->update($validated);

        return back()->with('success', 'Pengaturan WhatsApp berhasil diperbarui.');
    }

    public function testWa(Request $request)
    {
        $request->validate([
            'phone' => 'nullable|string|max:20',
            'wa_gateway_url' => 'nullable|url|max:255',
            'wa_gateway_token' => 'nullable|string|max:500',
            'wa_gateway_key' => 'nullable|string|max:500',
        ]);

        // Use values from request if provided (form not yet saved), else fall back to DB
        $url = trim((string) $request->input('wa_gateway_url', ''));
        $token = trim((string) $request->input('wa_gateway_token', ''));
        $key = trim((string) $request->input('wa_gateway_key', ''));

        if ($url !== '' || $token !== '' || $key !== '') {
            if ($url === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'URL Gateway wajib diisi.',
                ]);
            }

            if ($token === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Token perangkat WA wajib diisi agar nomor pengirim tidak kosong.',
                ]);
            }

            $service = new WaGatewayService(rtrim($url, '/'), $token, $key);
        } else {
            $user = $request->user();

            if ($user->isSuperAdmin() && $request->integer('tenant_id')) {
                $tenant = \App\Models\User::query()
                    ->tenants()
                    ->where('id', $request->integer('tenant_id'))
                    ->first();
                $settings = $tenant ? \App\Models\TenantSettings::getOrCreate($tenant->id) : null;
            } else {
                $settings = $user->getSettings();
            }

            if (! $settings || ! $settings->hasWaConfigured()) {
                return response()->json([
                    'success' => false,
                    'message' => 'URL Gateway dan Token perangkat WA belum dikonfigurasi.',
                ]);
            }

            $service = WaGatewayService::forTenant($settings);
        }

        $result = $service->testConnection();

        \Log::info('WA testConnection result', $result);

        return response()->json([
            'success' => $result['status'],
            'message' => $result['message'],
            'http_status' => $result['http_status'] ?? null,
            'network_error' => $result['network_error'] ?? false,
            'gateway_response' => $result['data'] ?? null,
        ]);
    }

    public function testTemplate(Request $request)
    {
        $request->validate([
            'type' => 'required|in:registration,invoice,payment',
        ]);

        $user = $request->user();
        $settings = $user->getSettings();

        if (! $settings->hasWaConfigured()) {
            return response()->json(['success' => false, 'message' => 'WA Gateway belum dikonfigurasi.']);
        }

        $csNumber = $settings->business_phone ?? '';
        if (empty(trim($csNumber))) {
            return response()->json(['success' => false, 'message' => 'Nomor HP bisnis (CS) belum diisi di Pengaturan.']);
        }

        $template = $settings->getTemplate($request->type);

        $bankAccounts = $user->bankAccounts()->active()->get();
        $bankLines = $bankAccounts->map(fn ($b) => $b->bank_name.' '.$b->account_number.' a/n '.$b->account_name)->join("\n");
        if (empty(trim($bankLines))) {
            $bankLines = '(Belum ada rekening bank aktif)';
        }

        $message = str_replace(
            ['{name}', '{username}', '{service}', '{profile}', '{due_date}', '{invoice_no}', '{total}', '{paid_at}', '{customer_id}', '{cs_number}', '{bank_account}'],
            ['Bapak/Ibu Test', 'test_user', 'PPPoE', 'Paket 10Mbps', date('d/m/Y'), 'INV-TEST001', 'Rp 150.000', now()->format('d/m/Y H:i'), 'CUST-001', $csNumber, $bankLines],
            $template
        );

        $service = \App\Services\WaGatewayService::forTenant($settings);
        if (! $service) {
            return response()->json(['success' => false, 'message' => 'WA Gateway tidak dapat diinisialisasi.']);
        }

        $phone = '62'.ltrim(preg_replace('/[^0-9]/', '', $csNumber), '0');

        try {
            $service->sendMessage($phone, '[TEST TEMPLATE] '."\n\n".$message);

            return response()->json(['success' => true, 'message' => 'Pesan test berhasil dikirim ke '.$csNumber]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal kirim: '.$e->getMessage()]);
        }
    }

    public function uploadLogo(Request $request)
    {
        if ($request->user()->isSubUser()) {
            abort(403);
        }

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

    public function updateIsolir(Request $request)
    {
        if ($request->user()->isSubUser()) {
            abort(403);
        }

        $validated = $request->validate([
            'isolir_page_title' => 'nullable|string|max:255',
            'isolir_page_body' => 'nullable|string|max:2000',
            'isolir_page_contact' => 'nullable|string|max:255',
            'isolir_page_bg_color' => ['nullable', 'string', 'max:20', 'regex:/^#[0-9a-fA-F]{3,6}$/'],
            'isolir_page_accent_color' => ['nullable', 'string', 'max:20', 'regex:/^#[0-9a-fA-F]{3,6}$/'],
        ]);

        $user = $request->user();
        $settings = $user->getSettings();
        $settings->update($validated);

        return back()->with('success', 'Pengaturan halaman isolir berhasil disimpan.');
    }

    public function isolirPreview(Request $request)
    {
        return app(\App\Http\Controllers\IsolirPageController::class)->preview($request);
    }
}
