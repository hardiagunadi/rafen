<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOvpnClientRequest;
use App\Http\Requests\UpdateOvpnClientRequest;
use App\Models\MikrotikConnection;
use App\Models\OvpnClient;
use App\Services\OvpnClientSynchronizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class OvpnSettingsController extends Controller
{
    public function index(): View
    {
        $clients = OvpnClient::query()
            ->with('mikrotikConnection')
            ->orderBy('name')
            ->get();
        $routers = MikrotikConnection::query()
            ->orderBy('name')
            ->get();

        return view('settings.ovpn', [
            'ovpn' => [
                'host' => (string) config('ovpn.host'),
                'port' => (string) config('ovpn.port'),
                'proto' => (string) config('ovpn.proto'),
                'username' => (string) config('ovpn.username'),
                'password' => (string) config('ovpn.password'),
                'network' => (string) config('ovpn.network'),
                'netmask' => (string) config('ovpn.netmask'),
                'pool_start' => (string) config('ovpn.pool_start'),
                'pool_end' => (string) config('ovpn.pool_end'),
                'route_dst' => (string) config('ovpn.route_dst'),
            ],
            'clients' => $clients,
            'routers' => $routers,
        ]);
    }

    public function store(StoreOvpnClientRequest $request, OvpnClientSynchronizer $synchronizer): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);
        $data['common_name'] = $this->resolveCommonName($data['common_name'] ?? null, $data['name']);
        $data['username'] = $this->resolveUsername($data['username'] ?? null, $data['common_name']);
        $data['password'] = $this->resolvePassword($data['password'] ?? null);
        $data['vpn_ip'] = $data['vpn_ip'] ?? $this->allocateVpnIp();

        if (! $data['vpn_ip']) {
            return redirect()
                ->route('settings.ovpn')
                ->with('error', 'IP VPN pool sudah habis atau belum diatur.');
        }

        $client = OvpnClient::create($data);

        try {
            $synchronizer->sync($client);
            $synchronizer->syncAuthUsers(OvpnClient::query()->get());
            $client->update(['last_synced_at' => now()]);
        } catch (Throwable $exception) {
            return redirect()
                ->route('settings.ovpn')
                ->with('error', 'Client tersimpan, tetapi sync CCD gagal: '.$exception->getMessage());
        }

        return redirect()
            ->route('settings.ovpn')
            ->with('status', 'Client OpenVPN berhasil dibuat.');
    }

    public function update(UpdateOvpnClientRequest $request, OvpnClient $ovpnClient, OvpnClientSynchronizer $synchronizer): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', $ovpnClient->is_active);

        $previousCommonName = $ovpnClient->common_name;
        $ovpnClient->update($data);

        try {
            if ($previousCommonName && $previousCommonName !== $ovpnClient->common_name) {
                $synchronizer->remove($previousCommonName);
            }
            $synchronizer->sync($ovpnClient);
            $synchronizer->syncAuthUsers(OvpnClient::query()->get());
            $ovpnClient->update(['last_synced_at' => now()]);
        } catch (Throwable $exception) {
            return redirect()
                ->route('settings.ovpn')
                ->with('error', 'Client diperbarui, tetapi sync CCD gagal: '.$exception->getMessage());
        }

        return redirect()
            ->route('settings.ovpn')
            ->with('status', 'Client OpenVPN diperbarui.');
    }

    public function destroy(OvpnClient $ovpnClient, OvpnClientSynchronizer $synchronizer): RedirectResponse
    {
        if ($ovpnClient->common_name) {
            $synchronizer->remove($ovpnClient->common_name);
        }

        $ovpnClient->delete();
        $synchronizer->syncAuthUsers(OvpnClient::query()->get());

        return redirect()
            ->route('settings.ovpn')
            ->with('status', 'Client OpenVPN dihapus.');
    }

    public function sync(OvpnClient $ovpnClient, OvpnClientSynchronizer $synchronizer): RedirectResponse
    {
        try {
            $synchronizer->sync($ovpnClient);
            $ovpnClient->update(['last_synced_at' => now()]);

            return redirect()
                ->route('settings.ovpn')
                ->with('status', 'Sinkronisasi CCD berhasil.');
        } catch (Throwable $exception) {
            return redirect()
                ->route('settings.ovpn')
                ->with('error', 'Sinkronisasi CCD gagal: '.$exception->getMessage());
        }
    }

    private function resolveCommonName(?string $commonName, string $fallback): string
    {
        $base = trim((string) ($commonName ?: $fallback));
        $slug = Str::slug($base, '-');
        if ($slug === '') {
            $slug = 'client';
        }

        $candidate = $slug;
        $counter = 1;

        while (OvpnClient::query()->where('common_name', $candidate)->exists()) {
            $counter++;
            $candidate = $slug.'-'.$counter;
        }

        return $candidate;
    }

    private function resolveUsername(?string $username, string $fallback): string
    {
        $base = trim((string) ($username ?: $fallback));
        $slug = Str::slug($base, '-');
        if ($slug === '') {
            $slug = 'ovpn';
        }

        $candidate = $slug;
        $counter = 1;

        while (OvpnClient::query()->where('username', $candidate)->exists()) {
            $counter++;
            $candidate = $slug.'-'.$counter;
        }

        return $candidate;
    }

    private function resolvePassword(?string $password): string
    {
        if ($password) {
            return $password;
        }

        do {
            $candidate = Str::random(12);
        } while (OvpnClient::query()->where('password', $candidate)->exists());

        return $candidate;
    }

    private function allocateVpnIp(): ?string
    {
        $rangeStart = (string) config('ovpn.pool_start');
        $rangeEnd = (string) config('ovpn.pool_end');

        $startLong = $this->ipToLong($rangeStart);
        $endLong = $this->ipToLong($rangeEnd);

        if ($startLong === null || $endLong === null || $startLong > $endLong) {
            return null;
        }

        $used = OvpnClient::query()
            ->whereNotNull('vpn_ip')
            ->pluck('vpn_ip')
            ->map(fn (string $ip) => $this->ipToLong($ip))
            ->filter(fn (?int $ip) => $ip !== null)
            ->unique()
            ->all();

        $usedLookup = array_fill_keys($used, true);

        for ($current = $startLong; $current <= $endLong; $current++) {
            if (! isset($usedLookup[$current])) {
                return $this->longToIp($current);
            }
        }

        return null;
    }

    private function ipToLong(string $ip): ?int
    {
        $long = ip2long($ip);
        if ($long === false) {
            return null;
        }

        return $long < 0 ? $long + (2 ** 32) : $long;
    }

    private function longToIp(int $long): string
    {
        if ($long > 2147483647) {
            $long -= 2 ** 32;
        }

        return long2ip($long);
    }
}
