<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class TenantVpnService
{
    protected string $ccdPath;
    protected string $authUsersPath;
    protected string $poolStart;
    protected string $poolEnd;
    protected string $netmask;

    public function __construct()
    {
        $this->ccdPath = config('ovpn.tenant_ccd_path', '/etc/openvpn/tenant-ccd');
        $this->authUsersPath = config('ovpn.tenant_auth_users_path', '/etc/openvpn/tenant-users');
        $this->poolStart = config('ovpn.tenant_pool_start', '10.9.0.2');
        $this->poolEnd = config('ovpn.tenant_pool_end', '10.9.255.254');
        $this->netmask = config('ovpn.tenant_netmask', '255.255.0.0');
    }

    public function createVpnUser(User $tenant): array
    {
        $username = 'tenant_' . $tenant->id;
        $password = Str::random(16);
        $vpnIp = $this->allocateVpnIp($tenant->id);

        // Update tenant record
        $tenant->update([
            'vpn_username' => $username,
            'vpn_password' => $password,
            'vpn_ip' => $vpnIp,
            'vpn_enabled' => true,
        ]);

        // Create CCD file
        $this->createCcdFile($username, $vpnIp);

        // Update auth users file
        $this->syncAuthUsers();

        return [
            'username' => $username,
            'password' => $password,
            'vpn_ip' => $vpnIp,
        ];
    }

    public function updateVpnUser(User $tenant): void
    {
        if (!$tenant->vpn_username) {
            return;
        }

        // Update CCD file
        $this->createCcdFile($tenant->vpn_username, $tenant->vpn_ip);

        // Sync auth users
        $this->syncAuthUsers();
    }

    public function disableVpnUser(User $tenant): void
    {
        $tenant->update(['vpn_enabled' => false]);

        // Remove CCD file
        $ccdFile = $this->ccdPath . '/' . $tenant->vpn_username;
        if (File::exists($ccdFile)) {
            File::delete($ccdFile);
        }

        // Sync auth users
        $this->syncAuthUsers();
    }

    public function deleteVpnUser(User $tenant): void
    {
        // Remove CCD file
        if ($tenant->vpn_username) {
            $ccdFile = $this->ccdPath . '/' . $tenant->vpn_username;
            if (File::exists($ccdFile)) {
                File::delete($ccdFile);
            }
        }

        // Clear VPN data
        $tenant->update([
            'vpn_username' => null,
            'vpn_password' => null,
            'vpn_ip' => null,
            'vpn_enabled' => false,
        ]);

        // Sync auth users
        $this->syncAuthUsers();
    }

    protected function createCcdFile(string $username, string $vpnIp): void
    {
        if (!File::isDirectory($this->ccdPath)) {
            File::makeDirectory($this->ccdPath, 0755, true);
        }

        $content = "ifconfig-push {$vpnIp} {$this->netmask}\n";

        File::put($this->ccdPath . '/' . $username, $content);
    }

    protected function syncAuthUsers(): void
    {
        $users = User::tenants()
            ->whereNotNull('vpn_username')
            ->whereNotNull('vpn_password')
            ->where('vpn_enabled', true)
            ->get();

        $lines = [];
        foreach ($users as $user) {
            $lines[] = "{$user->vpn_username}:{$user->vpn_password}";
        }

        $dir = dirname($this->authUsersPath);
        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        File::put($this->authUsersPath, implode("\n", $lines) . "\n");
    }

    protected function allocateVpnIp(int $tenantId): string
    {
        // Simple allocation based on tenant ID
        // This ensures unique IPs within the pool
        $startParts = explode('.', $this->poolStart);
        $baseIp = ip2long($this->poolStart);

        // Offset by tenant ID
        $newIp = $baseIp + $tenantId;

        // Check if within range
        $endIp = ip2long($this->poolEnd);
        if ($newIp > $endIp) {
            // Wrap around or use hash-based allocation
            $newIp = $baseIp + ($tenantId % ($endIp - $baseIp));
        }

        return long2ip($newIp);
    }

    public function getVpnConfig(User $tenant): string
    {
        if (!$tenant->vpn_username || !$tenant->vpn_enabled) {
            throw new \Exception('VPN not configured for this tenant.');
        }

        $serverHost = config('ovpn.server_host', 'vpn.example.com');
        $serverPort = config('ovpn.server_port', 1194);

        return <<<OVPN
client
dev tun
proto udp
remote {$serverHost} {$serverPort}
resolv-retry infinite
nobind
persist-key
persist-tun
auth-user-pass
cipher AES-256-GCM
auth SHA256
verb 3

# Certificate will be embedded here by admin
<ca>
# CA certificate content
</ca>
OVPN;
    }

    public function regeneratePassword(User $tenant): string
    {
        $password = Str::random(16);

        $tenant->update(['vpn_password' => $password]);

        $this->syncAuthUsers();

        return $password;
    }
}
