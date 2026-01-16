<?php

namespace App\Services;

use App\Models\OvpnClient;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
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

        if (! $this->filesystem->isDirectory($ccdPath)) {
            throw new RuntimeException('Direktori CCD belum ada. Jalankan installer OpenVPN atau buat folder CCD terlebih dahulu.');
        }

        if (! $this->filesystem->isWritable($ccdPath)) {
            throw new RuntimeException('Direktori CCD tidak writable untuk webserver.');
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

    /**
     * @param  Collection<int, OvpnClient>  $clients
     */
    public function syncAuthUsers(Collection $clients): void
    {
        $authPath = (string) config('ovpn.auth_users_path');
        if ($authPath === '') {
            throw new RuntimeException('Path auth users belum diatur.');
        }

        $directory = dirname($authPath);
        if (! $this->filesystem->isDirectory($directory)) {
            throw new RuntimeException('Direktori auth users belum ada.');
        }

        if (! $this->filesystem->isWritable($directory)) {
            throw new RuntimeException('Direktori auth users tidak writable.');
        }

        $lines = $clients
            ->where('is_active', true)
            ->filter(fn (OvpnClient $client) => $client->username && $client->password)
            ->map(fn (OvpnClient $client) => $client->username.' '.$client->password)
            ->values()
            ->all();

        $payload = implode("\n", $lines)."\n";
        $this->filesystem->put($authPath, $payload);
    }
}
