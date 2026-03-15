<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCpePppoeRequest;
use App\Http\Requests\UpdateCpeWifiRequest;
use App\Models\CpeDevice;
use App\Models\PppUser;
use App\Models\TenantSettings;
use App\Services\GenieAcsClient;
use App\Traits\LogsActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class CpeController extends Controller
{
    use LogsActivity;

    private GenieAcsClient $genieacs;

    public function __construct(?GenieAcsClient $genieacs = null)
    {
        $this->genieacs = $genieacs ?? $this->makeGenieacsClient();
    }

    private function makeGenieacsClient(): GenieAcsClient
    {
        $user = auth()->user();
        if ($user) {
            $ownerId  = $user->effectiveOwnerId();
            $settings = TenantSettings::where('user_id', $ownerId)->first();
            if ($settings) {
                return GenieAcsClient::fromTenantSettings($settings);
            }
        }

        return new GenieAcsClient();
    }

    /**
     * Global CPE list for the tenant.
     */
    public function index(): View
    {
        $user    = auth()->user();
        $devices = CpeDevice::query()
            ->accessibleBy($user)
            ->with('pppUser:id,customer_name,username')
            ->latest('last_seen_at')
            ->paginate(50);

        return view('cpe.index', compact('devices'));
    }

    /**
     * DataTable JSON for CPE index.
     */
    public function datatable(Request $request): JsonResponse
    {
        $user  = auth()->user();
        $query = CpeDevice::query()
            ->accessibleBy($user)
            ->with('pppUser:id,customer_name,username');

        $search = $request->input('search.value');
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('manufacturer', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%")
                    ->orWhereHas('pppUser', fn ($q) => $q->where('customer_name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%"));
            });
        }

        $total    = $query->count();
        $devices  = $query->orderBy('last_seen_at', 'desc')
            ->offset($request->input('start', 0))
            ->limit($request->input('length', 25))
            ->get();

        $data = $devices->map(fn (CpeDevice $d) => [
            'id'            => $d->id,
            'customer_name' => $d->pppUser?->customer_name ?? '-',
            'username'      => $d->pppUser?->username ?? '-',
            'ppp_user_id'   => $d->ppp_user_id,
            'manufacturer'  => $d->manufacturer ?? '-',
            'model'         => $d->model ?? '-',
            'firmware'      => $d->firmware_version ?? '-',
            'serial_number' => $d->serial_number ?? '-',
            'status'        => $d->status ?? 'unknown',
            'last_seen_at'  => $d->last_seen_at?->diffForHumans() ?? '-',
        ]);

        return response()->json([
            'draw'            => (int) $request->input('draw'),
            'recordsTotal'    => $total,
            'recordsFiltered' => $total,
            'data'            => $data,
        ]);
    }

    /**
     * Show CPE panel for a specific PppUser.
     */
    public function show(int $pppUserId): JsonResponse
    {
        $pppUser = $this->findPppUser($pppUserId);
        $device  = $pppUser->cpeDevice;

        if (! $device) {
            return response()->json(['linked' => false]);
        }

        return response()->json([
            'linked'        => true,
            'device'        => $this->deviceToArray($device),
        ]);
    }

    /**
     * Sync: find device in GenieACS by PPPoE username and save/update local record.
     */
    public function sync(int $pppUserId): JsonResponse
    {
        $pppUser = $this->findPppUser($pppUserId);

        if (! $this->canManageCpe()) {
            abort(403);
        }

        try {
            $genieDevice = $this->genieacs->findDeviceByUsername($pppUser->username);

            if (! $genieDevice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Perangkat tidak ditemukan di GenieACS. Pastikan CPE sudah terhubung ke ACS.',
                ], 404);
            }

            $device = $pppUser->cpeDevice ?? new CpeDevice([
                'ppp_user_id' => $pppUser->id,
                'owner_id'    => $pppUser->owner_id,
            ]);

            $device->updateFromGenieacs($genieDevice);
            $device->save();

            // Trigger GenieACS to fetch full parameter tree from CPE
            // so WiFi SSID etc. are populated on next inform
            $rootObj = isset($genieDevice['InternetGatewayDevice'])
                ? 'InternetGatewayDevice'
                : 'Device';
            try {
                $this->genieacs->refreshObject($device->genieacs_device_id, $rootObj);
            } catch (Throwable) {
                // Non-fatal — device info saved, refresh is best-effort
            }

        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Perangkat berhasil dihubungkan. Parameter WiFi akan tersedia setelah modem kontak ke ACS berikutnya.',
            'device'  => $this->deviceToArray($device),
        ]);
    }

    /**
     * Refresh device info from GenieACS (also issues refreshObject task).
     */
    public function refreshParams(int $pppUserId): JsonResponse
    {
        $pppUser = $this->findPppUser($pppUserId);
        $device  = $this->requireCpeDevice($pppUser);

        // Always fetch latest cached data from GenieACS first
        try {
            $genieDevice = $this->genieacs->getDeviceInfo($device->genieacs_device_id);
            if (! empty($genieDevice)) {
                $device->updateFromGenieacs($genieDevice);
                $device->save();
            }
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }

        // Queue getParameterValues for WLAN fields that are often not pre-fetched,
        // then a refreshObject so the CPE sends updated values on next inform.
        try {
            // Fetch WLAN params for known instances (1=2.4GHz, 5=5GHz on this modem)
            // Only fetch fields confirmed to exist (avoid fault 9005 for unknown params)
            $wlanParams = [];
            $knownInstances = array_keys($device->cached_params['wifi_networks'] ?? []);
            $wlanIndices = array_column($device->cached_params['wifi_networks'] ?? [], 'index') ?: [1, 5];
            foreach ($wlanIndices as $idx) {
                $base = "InternetGatewayDevice.LANDevice.1.WLANConfiguration.{$idx}";
                foreach (['Enable', 'SSID', 'KeyPassphrase', 'Channel', 'Standard', 'X_CT-COM_RFBand'] as $field) {
                    $wlanParams[] = "{$base}.{$field}";
                }
            }
            $this->genieacs->createTask($device->genieacs_device_id, [
                'name'            => 'getParameterValues',
                'parameterNames'  => $wlanParams,
            ]);
            $this->genieacs->refreshObject($device->genieacs_device_id);
        } catch (Throwable) {
            // Non-fatal — best-effort
        }

        return response()->json([
            'success' => true,
            'message' => 'Info perangkat diperbarui.',
            'device'  => $this->deviceToArray($device),
        ]);
    }

    /**
     * Get fresh device info (AJAX).
     */
    public function getInfo(int $pppUserId): JsonResponse
    {
        $pppUser = $this->findPppUser($pppUserId);
        $device  = $this->requireCpeDevice($pppUser);

        return response()->json([
            'success' => true,
            'device'  => $this->deviceToArray($device),
        ]);
    }

    /**
     * Reboot the CPE device.
     */
    public function reboot(int $pppUserId): JsonResponse
    {
        $pppUser = $this->findPppUser($pppUserId);
        $device  = $this->requireCpeDevice($pppUser);

        if (! $this->canRebootCpe()) {
            abort(403);
        }

        try {
            $result = $this->genieacs->rebootDevice($device->genieacs_device_id);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }

        $this->logActivity('reboot_cpe', 'CpeDevice', $device->id, $pppUser->customer_name, $pppUser->owner_id);

        $msg = $result['queued']
            ? 'Perintah reboot dikirim. Perangkat akan restart saat koneksi berikutnya ke ACS.'
            : 'Perangkat sedang di-restart.';

        return response()->json(['success' => true, 'message' => $msg, 'queued' => $result['queued']]);
    }

    /**
     * Update WiFi SSID and password.
     */
    public function updateWifi(UpdateCpeWifiRequest $request, int $pppUserId): JsonResponse
    {
        $pppUser = $this->findPppUser($pppUserId);
        $device  = $this->requireCpeDevice($pppUser);

        if (! $this->canManageCpe()) {
            abort(403);
        }

        try {
            $result = $this->genieacs->setWifi(
                $device->genieacs_device_id,
                $request->validated('ssid'),
                $request->validated('password'),
                $device->param_profile ?? 'igd'
            );
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }

        // Update cached SSID
        $cached             = $device->cached_params ?? [];
        $cached['wifi_ssid'] = $request->validated('ssid');
        $device->cached_params = $cached;
        $device->save();

        $this->logActivity('update_wifi', 'CpeDevice', $device->id, $pppUser->customer_name, $pppUser->owner_id);

        $msg = $result['queued']
            ? 'Konfigurasi WiFi dikirim. Akan diterapkan saat perangkat online.'
            : 'Konfigurasi WiFi berhasil diterapkan.';

        return response()->json(['success' => true, 'message' => $msg, 'queued' => $result['queued']]);
    }

    /**
     * Update PPPoE credentials on the CPE.
     */
    public function updatePppoe(UpdateCpePppoeRequest $request, int $pppUserId): JsonResponse
    {
        $pppUser = $this->findPppUser($pppUserId);
        $device  = $this->requireCpeDevice($pppUser);

        if (! $this->canManageCpe()) {
            abort(403);
        }

        try {
            $result = $this->genieacs->setPppoeCredentials(
                $device->genieacs_device_id,
                $request->validated('username'),
                $request->validated('password'),
                $device->param_profile ?? 'igd'
            );
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }

        $this->logActivity('update_pppoe', 'CpeDevice', $device->id, $pppUser->customer_name, $pppUser->owner_id);

        $msg = $result['queued']
            ? 'Kredensial PPPoE dikirim. Akan diterapkan saat perangkat online.'
            : 'Kredensial PPPoE berhasil diterapkan.';

        return response()->json(['success' => true, 'message' => $msg, 'queued' => $result['queued']]);
    }

    /**
     * Update WiFi SSID/password/enabled for a specific WLANConfiguration index.
     */
    public function updateWifiByIndex(Request $request, int $pppUserId, int $wlanIdx): JsonResponse
    {
        $pppUser = $this->findPppUser($pppUserId);
        $device  = $this->requireCpeDevice($pppUser);

        if (! $this->canManageCpe()) {
            abort(403);
        }

        $validated = $request->validate([
            'ssid'     => 'nullable|string|max:32',
            'password' => 'nullable|string|min:8|max:63',
            'enabled'  => 'nullable|boolean',
        ]);

        $base   = "InternetGatewayDevice.LANDevice.1.WLANConfiguration.{$wlanIdx}";
        $params = [];

        if (isset($validated['ssid'])) {
            $params[] = ["{$base}.SSID", $validated['ssid'], 'xsd:string'];
        }
        if (isset($validated['password'])) {
            $params[] = ["{$base}.KeyPassphrase", $validated['password'], 'xsd:string'];
        }
        if (isset($validated['enabled'])) {
            $params[] = ["{$base}.Enable", $validated['enabled'] ? 'true' : 'false', 'xsd:boolean'];
        }

        if (empty($params)) {
            return response()->json(['success' => false, 'message' => 'Tidak ada perubahan.'], 422);
        }

        try {
            $result = $this->genieacs->setParameterValues($device->genieacs_device_id, $params);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }

        // Update cache
        $cached  = $device->cached_params ?? [];
        $networks = $cached['wifi_networks'] ?? [];
        foreach ($networks as &$net) {
            if ($net['index'] === $wlanIdx) {
                if (isset($validated['ssid']))     $net['ssid']    = $validated['ssid'];
                if (isset($validated['password']))  $net['password'] = $validated['password'];
                if (isset($validated['enabled']))   $net['enabled']  = $validated['enabled'];
                break;
            }
        }
        unset($net);
        $cached['wifi_networks']   = $networks;
        $device->cached_params     = $cached;
        $device->save();

        $this->logActivity('update_wifi', 'CpeDevice', $device->id, $pppUser->customer_name, $pppUser->owner_id);

        $msg = $result['queued']
            ? "Konfigurasi WiFi #{$wlanIdx} dikirim. Akan diterapkan saat perangkat online."
            : "Konfigurasi WiFi #{$wlanIdx} berhasil diterapkan.";

        return response()->json(['success' => true, 'message' => $msg, 'queued' => $result['queued']]);
    }

    /**
     * Get current WAN connections (from cache).
     */
    public function getWanConnections(int $pppUserId): JsonResponse
    {
        $pppUser = $this->findPppUser($pppUserId);
        $device  = $this->requireCpeDevice($pppUser);

        $connections = $device->cached_params['wan_connections'] ?? [];

        return response()->json(['success' => true, 'wan_connections' => $connections]);
    }

    /**
     * Update a WAN connection parameters.
     */
    public function updateWanConnection(Request $request, int $pppUserId, int $wanIdx, int $cdIdx, string $connIdx): JsonResponse
    {
        $pppUser = $this->findPppUser($pppUserId);
        $device  = $this->requireCpeDevice($pppUser);

        if (! $this->canManageCpe()) {
            abort(403);
        }

        $validated = $request->validate([
            'enabled'         => 'nullable|boolean',
            'username'        => 'nullable|string|max:64',
            'password'        => 'nullable|string|max:64',
            'nat_enabled'     => 'nullable|boolean',
            'dns_servers'     => 'nullable|string|max:128',
            'connection_type' => 'nullable|in:IP_Routed,PPPoE_Bridged',
            'vlan_id'         => 'nullable|integer|min:1|max:4094',
            'vlan_prio'       => 'nullable|integer|min:0|max:7',
            'lan_interface'   => 'nullable|string|max:512',
        ]);

        // Determine connection sub-path (PPP or IP)
        $connType = str_starts_with($connIdx, 'ip') ? 'WANIPConnection' : 'WANPPPConnection';
        $connNum  = str_starts_with($connIdx, 'ip') ? ltrim($connIdx, 'ip') : $connIdx;
        $base     = "InternetGatewayDevice.WANDevice.{$wanIdx}.WANConnectionDevice.{$cdIdx}.{$connType}.{$connNum}";
        $vlanBase = "InternetGatewayDevice.WANDevice.{$wanIdx}.WANConnectionDevice.{$cdIdx}.X_CT-COM_WANEponLinkConfig";

        $params = [];

        if (isset($validated['enabled']))         $params[] = ["{$base}.Enable",        $validated['enabled'] ? 'true' : 'false', 'xsd:boolean'];
        if (isset($validated['username']))         $params[] = ["{$base}.Username",       $validated['username'],    'xsd:string'];
        if (isset($validated['password']))         $params[] = ["{$base}.Password",       $validated['password'],    'xsd:string'];
        if (isset($validated['nat_enabled']))      $params[] = ["{$base}.NATEnabled",     $validated['nat_enabled'] ? 'true' : 'false', 'xsd:boolean'];
        if (isset($validated['dns_servers']))      $params[] = ["{$base}.DNSServers",     $validated['dns_servers'], 'xsd:string'];
        if (isset($validated['connection_type']))  $params[] = ["{$base}.ConnectionType", $validated['connection_type'], 'xsd:string'];
        if (isset($validated['lan_interface']))    $params[] = ["{$base}.X_CT-COM_LanInterface", $validated['lan_interface'], 'xsd:string'];
        if (isset($validated['vlan_id']))          $params[] = ["{$vlanBase}.VLANIDMark", (string) $validated['vlan_id'], 'xsd:unsignedInt'];
        if (isset($validated['vlan_prio']))        $params[] = ["{$vlanBase}.802-1pMark", (string) $validated['vlan_prio'], 'xsd:unsignedInt'];

        if (empty($params)) {
            return response()->json(['success' => false, 'message' => 'Tidak ada perubahan.'], 422);
        }

        try {
            $result = $this->genieacs->setParameterValues($device->genieacs_device_id, $params);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }

        // Update cache
        $cached      = $device->cached_params ?? [];
        $connections = $cached['wan_connections'] ?? [];
        $key         = "{$wanIdx}.{$cdIdx}.{$connIdx}";
        foreach ($connections as &$conn) {
            if ($conn['key'] === $key) {
                foreach (['enabled','username','nat_enabled','dns_servers','connection_type','vlan_id','vlan_prio','lan_interface'] as $f) {
                    if (isset($validated[$f])) $conn[$f === 'lan_interface' ? 'lan_interface' : $f] = $validated[$f];
                }
                break;
            }
        }
        unset($conn);
        $cached['wan_connections'] = $connections;
        $device->cached_params     = $cached;
        $device->save();

        $this->logActivity('update_wan', 'CpeDevice', $device->id, $pppUser->customer_name, $pppUser->owner_id);

        $msg = $result['queued']
            ? 'Konfigurasi WAN dikirim. Akan diterapkan saat perangkat online.'
            : 'Konfigurasi WAN berhasil diterapkan.';

        return response()->json(['success' => true, 'message' => $msg, 'queued' => $result['queued']]);
    }

    /**
     * Remove the CPE device link (local record only, not GenieACS).
     */
    public function destroy(int $pppUserId): JsonResponse
    {
        $pppUser = $this->findPppUser($pppUserId);

        if (! $this->canManageCpe()) {
            abort(403);
        }

        $device = $pppUser->cpeDevice;
        if ($device) {
            $this->logActivity('unlink_cpe', 'CpeDevice', $device->id, $pppUser->customer_name, $pppUser->owner_id);
            $device->delete();
        }

        return response()->json(['success' => true, 'message' => 'Perangkat berhasil dilepaskan.']);
    }

    /**
     * Search PPP users for Select2 (link modal).
     */
    public function searchPppUsers(Request $request): JsonResponse
    {
        $user   = auth()->user();
        $term   = $request->input('q', '');
        $users  = PppUser::query()
            ->accessibleBy($user)
            ->where(function ($q) use ($term) {
                $q->where('customer_name', 'like', "%{$term}%")
                  ->orWhere('username', 'like', "%{$term}%");
            })
            ->limit(20)
            ->get(['id', 'customer_name', 'username']);

        return response()->json([
            'results' => $users->map(fn ($u) => [
                'id'   => $u->id,
                'text' => $u->customer_name.' ('.$u->username.')',
            ]),
        ]);
    }

    /**
     * List GenieACS devices that are not yet linked to any PPP user.
     */
    public function unlinkedDevices(): JsonResponse
    {
        if (! $this->canManageCpe()) {
            abort(403);
        }

        // Get all linked genieacs_device_ids for this tenant
        $user      = auth()->user();
        $linked    = CpeDevice::query()->accessibleBy($user)->pluck('genieacs_device_id')->all();

        // Fetch all devices from GenieACS
        $all = $this->genieacs->listDevices();

        $unlinked = [];
        foreach ($all as $dev) {
            $id = $dev['_id'] ?? null;
            if (! $id || in_array($id, $linked, true)) {
                continue;
            }

            $profile  = $this->genieacs->detectParamProfile($dev);
            $unlinked[] = [
                'genieacs_id'  => $id,
                'manufacturer' => $this->genieacs->getParamValue($dev, 'manufacturer') ?? '-',
                'model'        => $this->genieacs->getParamValue($dev, 'model') ?? '-',
                'serial'       => $this->genieacs->getParamValue($dev, 'serial_number') ?? '-',
                'firmware'     => $this->genieacs->getParamValue($dev, 'firmware_version') ?? '-',
                'pppoe_user'   => $this->genieacs->getParamValue($dev, 'pppoe_username') ?? '-',
                'last_inform'  => isset($dev['_lastInform'])
                    ? \Carbon\Carbon::parse($dev['_lastInform'])->diffForHumans()
                    : '-',
            ];
        }

        return response()->json($unlinked);
    }

    /**
     * Manually link a GenieACS device ID to a PPP user.
     */
    public function linkDevice(Request $request): JsonResponse
    {
        if (! $this->canManageCpe()) {
            abort(403);
        }

        $validated = $request->validate([
            'genieacs_id' => 'required|string|max:255',
            'ppp_user_id' => 'required|integer',
        ]);

        $pppUser = $this->findPppUser($validated['ppp_user_id']);

        // Fetch device from GenieACS
        $genieDevice = $this->genieacs->getDeviceInfo($validated['genieacs_id']);
        if (empty($genieDevice)) {
            return response()->json(['success' => false, 'message' => 'Device tidak ditemukan di GenieACS.'], 404);
        }

        // Remove existing link on this PPP user if any
        $pppUser->cpeDevice?->delete();

        $device = new CpeDevice([
            'ppp_user_id' => $pppUser->id,
            'owner_id'    => $pppUser->owner_id,
        ]);
        $device->updateFromGenieacs($genieDevice);
        $device->save();

        try {
            $rootObj = isset($genieDevice['InternetGatewayDevice']) ? 'InternetGatewayDevice' : 'Device';
            $this->genieacs->refreshObject($device->genieacs_device_id, $rootObj);
        } catch (Throwable) {
            // non-fatal
        }

        $this->logActivity('link_cpe', 'CpeDevice', $device->id, $pppUser->customer_name, $pppUser->owner_id);

        return response()->json(['success' => true, 'message' => 'Perangkat berhasil dihubungkan ke '.$pppUser->customer_name.'.']);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function findPppUser(int $id): PppUser
    {
        $user    = auth()->user();
        $pppUser = PppUser::query()->accessibleBy($user)->findOrFail($id);

        return $pppUser;
    }

    private function requireCpeDevice(PppUser $pppUser): CpeDevice
    {
        $device = $pppUser->cpeDevice;

        if (! $device || ! $device->genieacs_device_id) {
            abort(422, 'Perangkat belum terhubung ke GenieACS. Lakukan sinkronisasi terlebih dahulu.');
        }

        return $device;
    }

    private function deviceToArray(CpeDevice $device): array
    {
        // PPPoE session status from radacct (open session = no acctstoptime)
        $pppoeOnline = false;
        $pppoeIp     = null;
        if ($device->pppUser) {
            try {
                $session = \DB::table('radacct')
                    ->where('username', $device->pppUser->username)
                    ->whereNull('acctstoptime')
                    ->orderByDesc('acctstarttime')
                    ->first(['framedipaddress', 'acctstarttime']);
                $pppoeOnline = $session !== null;
                $pppoeIp     = $session?->framedipaddress;
            } catch (\Throwable) {
                // radacct table may not exist in all environments
            }
        }

        return [
            'id'                 => $device->id,
            'genieacs_device_id' => $device->genieacs_device_id,
            'serial_number'      => $device->serial_number,
            'manufacturer'       => $device->manufacturer,
            'model'              => $device->model,
            'firmware_version'   => $device->firmware_version,
            'status'             => $device->status ?? 'unknown',
            'last_seen_at'       => $device->last_seen_at?->diffForHumans(),
            'cached_params'      => $device->cached_params,
            'pppoe_online'       => $pppoeOnline,
            'pppoe_ip'           => $pppoeIp,
        ];
    }

    private function canManageCpe(): bool
    {
        $user = auth()->user();
        if ($user->isSuperAdmin()) {
            return true;
        }

        return in_array($user->role, ['administrator', 'noc', 'it_support']);
    }

    private function canRebootCpe(): bool
    {
        $user = auth()->user();
        if ($user->isSuperAdmin()) {
            return true;
        }

        return in_array($user->role, ['administrator', 'noc', 'it_support', 'teknisi']);
    }
}
