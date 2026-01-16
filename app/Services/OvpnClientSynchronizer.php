<?php

namespace App\Services;

use App\Models\OvpnClient;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;

class OvpnClientSynchronizer
{
    public function __construct(private Filesystem $filesystem) {}

    public function sync(OvpnClient $client): void
    {
        $ccdPath = (string) config('ovpn.ccd_path');
        if ($ccdPath === '') {
            throw new RuntimeException('Path CCD belum diatur.');
        }

        $this->filesystem->ensureDirectoryExists($ccdPath);

        if (! $this->filesystem->isWritable($ccdPath)) {
            throw new RuntimeException('Direktori CCD tidak writable.');
        }

        $vpnIp = $client->vpn_ip;
        if (! $vpnIp) {
            throw new RuntimeException('IP VPN belum diisi.');
        }

        $netmask = (string) config('ovpn.netmask');
        if ($netmask === '') {
            throw new RuntimeException('Netmask OpenVPN belum diatur.');
        }

        $commonName = trim((string) $client->common_name);
        if ($commonName === '') {
            throw new RuntimeException('Common Name belum diisi.');
        }

        $filename = $ccdPath.'/'.$commonName;
        $payload = implode("\n", [
            '# Managed by Laravel - do not edit manually',
            "ifconfig-push {$vpnIp} {$netmask}",
            '',
        ]);

        $this->filesystem->put($filename, $payload);
    }

    public function remove(string $commonName): void
    {
        $ccdPath = (string) config('ovpn.ccd_path');
        if ($ccdPath === '') {
            return;
        }

        $filename = $ccdPath.'/'.trim($commonName);
        if ($this->filesystem->exists($filename)) {
            $this->filesystem->delete($filename);
        }
    }
}
