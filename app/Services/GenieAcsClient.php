<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GenieAcsClient
{
    private string $baseUrl;
    private string $username;
    private string $password;
    private int $timeout;

    public function __construct(?string $url = null, ?string $username = null, ?string $password = null)
    {
        $this->baseUrl  = rtrim($url ?? config('genieacs.nbi_url', 'http://localhost:7557'), '/');
        $this->username = $username ?? config('genieacs.username', '');
        $this->password = $password ?? config('genieacs.password', '');
        $this->timeout  = config('genieacs.timeout', 10);
    }

    /**
     * Create a GenieAcsClient from a TenantSettings model.
     * Falls back to global .env config when tenant has no GenieACS URL configured.
     */
    public static function fromTenantSettings(\App\Models\TenantSettings $settings): self
    {
        if ($settings->hasGenieacsConfigured()) {
            return new self(
                $settings->genieacs_url,
                $settings->genieacs_username ?? '',
                $settings->genieacs_password ?? '',
            );
        }

        return new self();
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Find a GenieACS device by its PPPoE username.
     * Searches both IGD (TR-098) and Device (TR-181) parameter paths.
     */
    public function findDeviceByUsername(string $username): ?array
    {
        $igdPath    = config('genieacs.params.igd.pppoe_username');
        $devicePath = config('genieacs.params.device.pppoe_username');

        // Try IGD path first (most common for ONUs/XPONs)
        foreach ([$igdPath, $devicePath] as $path) {
            $key      = $path.'._value';
            $query    = json_encode([$key => $username]);
            $response = $this->get('/devices/', ['query' => $query]);

            if ($response->successful()) {
                $devices = $response->json();
                if (! empty($devices)) {
                    return $devices[0];
                }
            }
        }

        // Fallback: search all devices and match manually (for devices with non-standard paths)
        Log::info("GenieACS: device with PPPoE username '{$username}' not found via standard paths.");

        return null;
    }

    /**
     * Detect parameter root: 'igd' or 'device' based on device document.
     */
    public function detectParamProfile(array $device): string
    {
        if (isset($device['InternetGatewayDevice'])) {
            return 'igd';
        }

        return 'device';
    }

    /**
     * Get a named parameter value from a device document.
     * Handles both IGD and Device root trees.
     */
    public function getParamValue(array $device, string $paramKey): mixed
    {
        $profile = $this->detectParamProfile($device);
        $path    = config("genieacs.params.{$profile}.{$paramKey}");

        if (! $path) {
            return null;
        }

        return $this->extractValue($device, $path);
    }

    /**
     * Get device info document from GenieACS.
     */
    public function getDeviceInfo(string $deviceId): array
    {
        $response = $this->get('/devices/', ['query' => json_encode(['_id' => $deviceId])]);

        if (! $response->successful()) {
            Log::warning('GenieACS: getDeviceInfo failed', [
                'deviceId' => $deviceId,
                'status'   => $response->status(),
            ]);

            return [];
        }

        $body = $response->json();

        return is_array($body) && ! empty($body) ? $body[0] : [];
    }

    /**
     * Create a reboot task for a device.
     */
    public function rebootDevice(string $deviceId): array
    {
        return $this->createTask($deviceId, ['name' => 'reboot']);
    }

    /**
     * Create a factory reset task for a device.
     */
    public function factoryReset(string $deviceId): array
    {
        return $this->createTask($deviceId, ['name' => 'factoryReset']);
    }

    /**
     * Set WiFi SSID and password on a device.
     * Auto-detects TR-098 vs TR-181 parameter paths.
     */
    public function setWifi(string $deviceId, string $ssid, string $password, string $profile = 'igd'): array
    {
        $params = config("genieacs.params.{$profile}");

        return $this->createTask($deviceId, [
            'name'            => 'setParameterValues',
            'parameterValues' => [
                [$params['wifi_ssid'], $ssid, 'xsd:string'],
                [$params['wifi_password'], $password, 'xsd:string'],
            ],
        ]);
    }

    /**
     * Set PPPoE credentials on a device.
     */
    public function setPppoeCredentials(string $deviceId, string $username, string $password, string $profile = 'igd'): array
    {
        $params = config("genieacs.params.{$profile}");

        return $this->createTask($deviceId, [
            'name'            => 'setParameterValues',
            'parameterValues' => [
                [$params['pppoe_username'], $username, 'xsd:string'],
                [$params['pppoe_password'], $password, 'xsd:string'],
            ],
        ]);
    }

    /**
     * Set arbitrary parameter values on a device.
     * $params = [['path.to.param', 'value', 'xsd:string'], ...]
     */
    public function setParameterValues(string $deviceId, array $params): array
    {
        return $this->createTask($deviceId, [
            'name'            => 'setParameterValues',
            'parameterValues' => $params,
        ]);
    }

    /**
     * Refresh a device object tree (forces GenieACS to re-fetch params from CPE).
     */
    public function refreshObject(string $deviceId, string $objectPath = 'InternetGatewayDevice'): array
    {
        return $this->createTask($deviceId, [
            'name'       => 'refreshObject',
            'objectName' => $objectPath,
        ]);
    }

    /**
     * Create an arbitrary task on a device.
     * Returns ['queued' => bool, 'task_id' => string|null, 'status' => int]
     */
    public function createTask(string $deviceId, array $taskBody): array
    {
        // timeout=0 → GenieACS queues the task and returns HTTP 202 immediately
        // without waiting for the CPE to respond (avoids long-hanging requests).
        $response = $this->post(
            '/devices/'.$deviceId.'/tasks?timeout=0',
            $taskBody
        );

        $status = $response->status();

        if ($status === 404) {
            throw new RuntimeException("Device '{$deviceId}' not found in GenieACS.");
        }

        if (! in_array($status, [200, 202])) {
            Log::error('GenieACS: createTask failed', [
                'deviceId' => $deviceId,
                'task'     => $taskBody,
                'status'   => $status,
                'body'     => $response->body(),
            ]);
            throw new RuntimeException("GenieACS task failed with HTTP {$status}: ".$response->body());
        }

        $body   = $response->json() ?? [];
        $taskId = $body['_id'] ?? null;

        return [
            'queued'  => $status === 202,
            'task_id' => $taskId,
            'status'  => $status,
        ];
    }

    /**
     * Get a task by its ID.
     */
    public function getTask(string $taskId): ?array
    {
        $query    = json_encode(['_id' => $taskId]);
        $response = $this->get('/tasks/', ['query' => $query]);

        if (! $response->successful()) {
            return null;
        }

        $tasks = $response->json();

        return ! empty($tasks) ? $tasks[0] : null;
    }

    /**
     * List all devices.
     */
    public function listDevices(array $queryFilter = []): array
    {
        $params = [];
        if (! empty($queryFilter)) {
            $params['query'] = json_encode($queryFilter);
        }

        $response = $this->get('/devices/', $params);

        return $response->successful() ? ($response->json() ?? []) : [];
    }

    // -------------------------------------------------------------------------
    // Value extraction helpers
    // -------------------------------------------------------------------------

    /**
     * Extract a value from a GenieACS device document by dotted path.
     * e.g. "InternetGatewayDevice.DeviceInfo.SoftwareVersion" → "_value"
     */
    public function extractValue(array $doc, string $path): mixed
    {
        $parts  = explode('.', $path);
        $cursor = $doc;

        foreach ($parts as $part) {
            if (! isset($cursor[$part])) {
                return null;
            }
            $cursor = $cursor[$part];
        }

        return $cursor['_value'] ?? null;
    }

    /**
     * Extract all WLANConfiguration instances from IGD device doc.
     * Returns array indexed by instance number, each with ssid/password/enabled/band fields.
     */
    public function extractWifiNetworks(array $doc): array
    {
        $lanDevice = $doc['InternetGatewayDevice']['LANDevice']['1'] ?? [];
        $wlanConfs = $lanDevice['WLANConfiguration'] ?? [];
        $networks  = [];

        foreach ($wlanConfs as $idx => $wlan) {
            if (! is_numeric($idx)) {
                continue;
            }
            $get = fn (string $key) => $wlan[$key]['_value'] ?? null;

            // Enable: null means not yet fetched from CPE — assume true (default on)
            $enableVal = $get('Enable');
            $enabled   = $enableVal === null ? true : (bool) $enableVal;

            // Detect band: X_CT-COM_RFBand (0=2.4GHz, 1=5GHz) is most reliable on CT-COM devices,
            // fallback to Standard field (a/ac/ax = 5GHz), then channel (>=36 = 5GHz)
            $standard = $get('Standard') ?? '';
            $channel  = (int) ($get('Channel') ?? 0);
            $rfBand   = $get('X_CT-COM_RFBand');
            if ($rfBand !== null) {
                $band = ((int) $rfBand === 1) ? '5GHz' : '2.4GHz';
            } elseif (str_contains($standard, 'ac') || str_contains($standard, 'ax') || preg_match('/\ba\b/', $standard)) {
                $band = '5GHz';
            } elseif ($channel >= 36) {
                $band = '5GHz';
            } else {
                $band = '2.4GHz';
            }

            $networks[(int) $idx] = [
                'index'      => (int) $idx,
                'ssid'       => $get('SSID'),
                'password'   => $get('KeyPassphrase'),
                'enabled'    => $enabled,
                'channel'    => $get('Channel'),
                'standard'   => $standard,
                'encryption' => $get('IEEE11iEncryptionModes'),
                'band'       => $band,
                'path'       => "InternetGatewayDevice.LANDevice.1.WLANConfiguration.{$idx}",
            ];
        }

        ksort($networks);

        return array_values($networks);
    }

    /**
     * Extract all WAN connections from IGD device doc.
     * Returns array of connections with name/status/type/vlan/username/ip fields.
     */
    public function extractWanConnections(array $doc): array
    {
        $wanDevices = $doc['InternetGatewayDevice']['WANDevice'] ?? [];
        $connections = [];

        foreach ($wanDevices as $wdIdx => $wanDev) {
            if (! is_numeric($wdIdx)) {
                continue;
            }
            $wanCds = $wanDev['WANConnectionDevice'] ?? [];

            foreach ($wanCds as $cdIdx => $cd) {
                if (! is_numeric($cdIdx)) {
                    continue;
                }

                // VLAN from WANEponLinkConfig
                $eponCfg = $cd['X_CT-COM_WANEponLinkConfig'] ?? $cd['X_CT-COM_WANGponLinkConfig'] ?? [];
                $vlan    = $eponCfg['VLANIDMark']['_value'] ?? null;
                $mode    = $eponCfg['Mode']['_value'] ?? null;
                $prio    = $eponCfg['802-1pMark']['_value'] ?? null;

                // PPPConnections
                $pppConns = $cd['WANPPPConnection'] ?? [];
                foreach ($pppConns as $pppIdx => $ppp) {
                    if (! is_numeric($pppIdx)) {
                        continue;
                    }
                    $get = fn (string $key) => $ppp[$key]['_value'] ?? null;

                    $connections[] = [
                        'wan_idx'          => (int) $wdIdx,
                        'cd_idx'           => (int) $cdIdx,
                        'ppp_idx'          => (int) $pppIdx,
                        'key'              => "{$wdIdx}.{$cdIdx}.{$pppIdx}",
                        'name'             => $get('Name'),
                        'enabled'          => $get('Enable'),
                        'status'           => $get('ConnectionStatus'),
                        'connection_type'  => $get('ConnectionType'),
                        'transport_type'   => $get('TransportType'),
                        'username'         => $get('Username'),
                        'external_ip'      => $get('ExternalIPAddress'),
                        'remote_ip'        => $get('RemoteIPAddress'),
                        'uptime'           => $get('Uptime'),
                        'nat_enabled'      => $get('NATEnabled'),
                        'dns_servers'      => $get('DNSServers'),
                        'service_list'     => $get('X_CT-COM_ServiceList'),
                        'lan_interface'    => $get('X_CT-COM_LanInterface'),
                        'vlan_id'          => $vlan,
                        'vlan_prio'        => $prio,
                        'vlan_mode'        => $mode,
                        'path_prefix'      => "InternetGatewayDevice.WANDevice.{$wdIdx}.WANConnectionDevice.{$cdIdx}.WANPPPConnection.{$pppIdx}",
                        'vlan_path_prefix' => "InternetGatewayDevice.WANDevice.{$wdIdx}.WANConnectionDevice.{$cdIdx}.X_CT-COM_WANEponLinkConfig",
                    ];
                }

                // IPConnections
                $ipConns = $cd['WANIPConnection'] ?? [];
                foreach ($ipConns as $ipIdx => $ip) {
                    if (! is_numeric($ipIdx)) {
                        continue;
                    }
                    $get = fn (string $key) => $ip[$key]['_value'] ?? null;

                    $connections[] = [
                        'wan_idx'          => (int) $wdIdx,
                        'cd_idx'           => (int) $cdIdx,
                        'ppp_idx'          => null,
                        'key'              => "{$wdIdx}.{$cdIdx}.ip{$ipIdx}",
                        'name'             => $get('Name'),
                        'enabled'          => $get('Enable'),
                        'status'           => $get('ConnectionStatus'),
                        'connection_type'  => $get('ConnectionType'),
                        'transport_type'   => 'IP',
                        'username'         => null,
                        'external_ip'      => $get('ExternalIPAddress'),
                        'remote_ip'        => null,
                        'uptime'           => $get('Uptime'),
                        'nat_enabled'      => $get('NATEnabled'),
                        'dns_servers'      => $get('DNSServers'),
                        'service_list'     => $get('X_CT-COM_ServiceList'),
                        'lan_interface'    => $get('X_CT-COM_LanInterface'),
                        'vlan_id'          => $vlan,
                        'vlan_prio'        => $prio,
                        'vlan_mode'        => $mode,
                        'path_prefix'      => "InternetGatewayDevice.WANDevice.{$wdIdx}.WANConnectionDevice.{$cdIdx}.WANIPConnection.{$ipIdx}",
                        'vlan_path_prefix' => "InternetGatewayDevice.WANDevice.{$wdIdx}.WANConnectionDevice.{$cdIdx}.X_CT-COM_WANEponLinkConfig",
                    ];
                }
            }
        }

        return $connections;
    }

    // -------------------------------------------------------------------------
    // Private HTTP helpers
    // -------------------------------------------------------------------------

    private function request(): \Illuminate\Http\Client\PendingRequest
    {
        $req = Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->acceptJson();

        if ($this->username !== '') {
            $req = $req->withBasicAuth($this->username, $this->password);
        }

        return $req;
    }

    private function get(string $path, array $query = []): Response
    {
        return $this->request()->get($path, $query);
    }

    private function post(string $path, array $body = []): Response
    {
        return $this->request()->post($path, $body);
    }
}
