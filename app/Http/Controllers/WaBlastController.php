<?php

namespace App\Http\Controllers;

use App\Models\HotspotProfile;
use App\Models\HotspotUser;
use App\Models\PppProfile;
use App\Models\PppUser;
use App\Models\TenantSettings;
use App\Services\WaGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WaBlastController extends Controller
{
    private function authorizeAccess(): void
    {
        $user = auth()->user();
        if (
            ! $user->isSuperAdmin()
            && ! in_array($user->role, ['administrator', 'noc', 'it_support'])
        ) {
            abort(403);
        }
    }

    public function index(Request $request): View
    {
        $this->authorizeAccess();

        $currentUser = $request->user();
        $settings = TenantSettings::getOrCreate($currentUser->effectiveOwnerId());

        if (! $settings->wa_broadcast_enabled && ! $currentUser->isSuperAdmin()) {
            abort(403, 'Fitur WA Blast belum diaktifkan. Aktifkan di Pengaturan → WhatsApp.');
        }

        $pppProfiles = PppProfile::query()->accessibleBy($currentUser)->orderBy('name')->get();
        $hotspotProfiles = HotspotProfile::query()->accessibleBy($currentUser)->orderBy('name')->get();

        return view('wa-blast.index', compact('settings', 'pppProfiles', 'hotspotProfiles'));
    }

    /**
     * Preview: return count + phone numbers based on filter.
     */
    public function preview(Request $request): JsonResponse
    {
        $this->authorizeAccess();

        $currentUser = $request->user();
        $tipe = $request->input('tipe', 'ppp');
        $statusAkun = $request->input('status_akun', '');
        $statusBayar = $request->input('status_bayar', '');
        $profileId = $request->input('profile_id', '');

        $recipients = $this->buildRecipients($currentUser, $tipe, $statusAkun, $statusBayar, $profileId, '');

        return response()->json([
            'count' => count($recipients),
            'phones' => array_column($recipients, 'phone'),
        ]);
    }

    /**
     * Send broadcast messages.
     */
    public function send(Request $request): JsonResponse
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'tipe'        => 'required|in:ppp,hotspot,all',
            'status_akun' => 'nullable|string',
            'status_bayar'=> 'nullable|string',
            'profile_id'  => 'nullable|integer',
            'message'     => 'required|string|min:5|max:4096',
        ]);

        $currentUser = $request->user();
        $settings = TenantSettings::getOrCreate($currentUser->effectiveOwnerId());

        if (! $settings->wa_broadcast_enabled && ! $currentUser->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'Fitur WA Blast tidak aktif.'], 403);
        }

        $waService = WaGatewayService::forTenant($settings);
        if (! $waService) {
            return response()->json(['success' => false, 'message' => 'WA Gateway belum dikonfigurasi di Pengaturan.'], 422);
        }

        $recipients = $this->buildRecipients(
            $currentUser,
            $validated['tipe'],
            $validated['status_akun'] ?? '',
            $validated['status_bayar'] ?? '',
            $validated['profile_id'] ?? '',
            $validated['message']
        );

        if (empty($recipients)) {
            return response()->json(['success' => false, 'message' => 'Tidak ada penerima yang cocok dengan filter.'], 422);
        }

        $result = $waService->sendBulk($recipients);

        return response()->json([
            'success' => true,
            'message' => "Pesan terkirim ke {$result['success']} penerima. Gagal: {$result['failed']}.",
            'success_count' => $result['success'],
            'failed_count'  => $result['failed'],
        ]);
    }

    /**
     * Build recipient list from filters.
     *
     * @return array<array{phone: string, message: string, name: string}>
     */
    private function buildRecipients($currentUser, string $tipe, string $statusAkun, string $statusBayar, $profileId, string $message): array
    {
        $recipients = [];

        if ($tipe === 'ppp' || $tipe === 'all') {
            $query = PppUser::query()->accessibleBy($currentUser)->whereNotNull('nomor_hp')->where('nomor_hp', '!=', '');

            if ($statusAkun !== '') {
                $query->where('status_akun', $statusAkun);
            }
            if ($statusBayar !== '') {
                $query->where('status_bayar', $statusBayar);
            }
            if ($profileId !== '' && $profileId !== null) {
                $query->where('ppp_profile_id', (int) $profileId);
            }

            foreach ($query->get() as $user) {
                $recipients[] = [
                    'phone'   => $user->nomor_hp,
                    'message' => $message,
                    'name'    => $user->customer_name,
                ];
            }
        }

        if ($tipe === 'hotspot' || $tipe === 'all') {
            $query = HotspotUser::query()->accessibleBy($currentUser)->whereNotNull('nomor_hp')->where('nomor_hp', '!=', '');

            if ($statusAkun !== '') {
                $query->where('status_akun', $statusAkun);
            }
            if ($statusBayar !== '') {
                $query->where('status_bayar', $statusBayar);
            }
            if ($profileId !== '' && $profileId !== null && $tipe === 'hotspot') {
                $query->where('hotspot_profile_id', (int) $profileId);
            }

            foreach ($query->get() as $user) {
                $recipients[] = [
                    'phone'   => $user->nomor_hp,
                    'message' => $message,
                    'name'    => $user->customer_name,
                ];
            }
        }

        return $recipients;
    }
}
