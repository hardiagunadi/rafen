<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOvpnClientRequest;
use App\Http\Requests\UpdateOvpnClientRequest;
use App\Models\MikrotikConnection;
use App\Models\OvpnClient;
use App\Services\OvpnClientSynchronizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
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

        $configuredHost = (string) config('ovpn.host');
        $detectedIp = $configuredHost !== '' ? null : $this->detectPublicIp();

        return view('settings.ovpn', [
            'ovpn' => [
                'host' => $configuredHost,
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
            'detectedIp' => $detectedIp,
            'clients' => $clients,
            'routers' => $routers,
        ]);
    }

    private function detectPublicIp(): ?string
    {
        // Coba baca dari file cache sementara agar tidak query setiap request
        $cacheFile = storage_path('app/detected_public_ip.txt');
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 300) {
            $cached = trim((string) file_get_contents($cacheFile));
            if ($cached !== '') {
                return $cached;
            }
        }

        $ip = null;

        // 1. Coba hostname -I (ambil IP pertama yang bukan loopback/private — cocok untuk VPS)
        $output = shell_exec('curl -s --max-time 3 https://api.ipify.org 2>/dev/null');
        if ($output !== null) {
            $candidate = trim($output);
            if (filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $ip = $candidate;
            }
        }

        // 2. Fallback: ambil dari hostname -I lalu filter IP publik
        if ($ip === null) {
            $output = shell_exec('hostname -I 2>/dev/null');
            if ($output !== null) {
                foreach (explode(' ', trim($output)) as $candidate) {
                    $candidate = trim($candidate);
                    if (filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        $ip = $candidate;
                        break;
                    }
                }
            }
        }

        // Simpan ke cache
        if ($ip !== null) {
            @file_put_contents($cacheFile, $ip);
        }

        return $ip;
    }

    public function store(StoreOvpnClientRequest $request, OvpnClientSynchronizer $synchronizer): JsonResponse|RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);
        $data['common_name'] = $this->resolveCommonName($data['common_name'] ?? null, $data['name']);
        $data['username'] = $this->resolveUsername($data['username'] ?? null, $data['common_name']);
        $data['password'] = $this->resolvePassword($data['password'] ?? null);
        $data['vpn_ip'] = $data['vpn_ip'] ?? $this->allocateVpnIp();

        if (! $data['vpn_ip']) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'IP VPN pool sudah habis atau belum diatur.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            return redirect()
                ->route('settings.ovpn')
                ->with('error', 'IP VPN pool sudah habis atau belum diatur.');
        }

        $client = OvpnClient::create($data);
        $client->load('mikrotikConnection');

        try {
            $synchronizer->sync($client);
            $synchronizer->syncAuthUsers(OvpnClient::query()->get());
            $client->update(['last_synced_at' => now()]);
            $client->refresh();
        } catch (Throwable $exception) {
            if ($request->wantsJson()) {
                return response()->json([
                    'warning' => 'Client tersimpan, tetapi sync CCD gagal: '.$exception->getMessage(),
                    'client' => $this->clientPayload($client),
                ]);
            }

            return redirect()
                ->route('settings.ovpn')
                ->with('error', 'Client tersimpan, tetapi sync CCD gagal: '.$exception->getMessage());
        }

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'Client OpenVPN berhasil dibuat.',
                'client' => $this->clientPayload($client),
            ]);
        }

        return redirect()
            ->route('settings.ovpn')
            ->with('status', 'Client OpenVPN berhasil dibuat.');
    }

    public function update(UpdateOvpnClientRequest $request, OvpnClient $ovpnClient, OvpnClientSynchronizer $synchronizer): JsonResponse|RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', $ovpnClient->is_active);

        $previousCommonName = $ovpnClient->common_name;
        $ovpnClient->update($data);
        $ovpnClient->load('mikrotikConnection');

        try {
            if ($previousCommonName && $previousCommonName !== $ovpnClient->common_name) {
                $synchronizer->remove($previousCommonName);
            }
            $synchronizer->sync($ovpnClient);
            $synchronizer->syncAuthUsers(OvpnClient::query()->get());
            $ovpnClient->update(['last_synced_at' => now()]);
            $ovpnClient->refresh();
        } catch (Throwable $exception) {
            if ($request->wantsJson()) {
                return response()->json([
                    'warning' => 'Client diperbarui, tetapi sync CCD gagal: '.$exception->getMessage(),
                    'client' => $this->clientPayload($ovpnClient),
                ]);
            }

            return redirect()
                ->route('settings.ovpn')
                ->with('error', 'Client diperbarui, tetapi sync CCD gagal: '.$exception->getMessage());
        }

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'Client OpenVPN diperbarui.',
                'client' => $this->clientPayload($ovpnClient),
            ]);
        }

        return redirect()
            ->route('settings.ovpn')
            ->with('status', 'Client OpenVPN diperbarui.');
    }

    public function destroy(OvpnClient $ovpnClient, OvpnClientSynchronizer $synchronizer): JsonResponse|RedirectResponse
    {
        if ($ovpnClient->common_name) {
            $synchronizer->remove($ovpnClient->common_name);
        }

        $ovpnClient->delete();
        $synchronizer->syncAuthUsers(OvpnClient::query()->get());

        if (request()->wantsJson()) {
            return response()->json(['status' => 'Client OpenVPN dihapus.']);
        }

        return redirect()
            ->route('settings.ovpn')
            ->with('status', 'Client OpenVPN dihapus.');
    }

    public function sync(OvpnClient $ovpnClient, OvpnClientSynchronizer $synchronizer): JsonResponse|RedirectResponse
    {
        try {
            $synchronizer->sync($ovpnClient);
            $ovpnClient->update(['last_synced_at' => now()]);
            $ovpnClient->refresh();

            if (request()->wantsJson()) {
                return response()->json([
                    'status' => 'Sinkronisasi CCD berhasil.',
                    'last_synced_at' => $ovpnClient->last_synced_at?->format('Y-m-d H:i:s'),
                ]);
            }

            return redirect()
                ->route('settings.ovpn')
                ->with('status', 'Sinkronisasi CCD berhasil.');
        } catch (Throwable $exception) {
            if (request()->wantsJson()) {
                return response()->json(['error' => 'Sinkronisasi CCD gagal: '.$exception->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            return redirect()
                ->route('settings.ovpn')
                ->with('error', 'Sinkronisasi CCD gagal: '.$exception->getMessage());
        }
    }

    private function clientPayload(OvpnClient $client): array
    {
        return [
            'id'                   => $client->id,
            'name'                 => $client->name,
            'common_name'          => $client->common_name,
            'vpn_ip'               => $client->vpn_ip,
            'username'             => $client->username,
            'password'             => $client->password,
            'is_active'            => $client->is_active,
            'last_synced_at'       => $client->last_synced_at?->format('Y-m-d H:i:s'),
            'mikrotik_connection'  => $client->mikrotikConnection?->name,
            'update_url'           => route('settings.ovpn.clients.update', $client),
            'destroy_url'          => route('settings.ovpn.clients.destroy', $client),
            'sync_url'             => route('settings.ovpn.clients.sync', $client),
        ];
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
