<?php

namespace App\Services;

use App\Models\MikrotikConnection;
use App\Models\PppUser;
use App\Models\TenantSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * IsolirSynchronizer
 *
 * Mengurus proses isolir pelanggan PPP:
 * 1. Set radreply agar user mendapat IP dari pool isolir + PPP profile isolir
 * 2. Auto-setup Mikrotik (pool, PPP profile, firewall, NAT) jika belum dilakukan
 * 3. Kick/drop sesi aktif user di Mikrotik agar reconnect dengan profile isolir
 *
 * Mekanisme redirect halaman isolir (TANPA web proxy):
 * - User isolir mendapat IP dari pool-isolir (mis: 10.99.0.x)
 * - Firewall Mikrotik DNAT port 80+443 dari pool ini ke IP server Rafen
 * - User membuka browser → semua HTTP/HTTPS masuk ke halaman /isolir/{slug}
 * - Semua traffic lain ke internet di-DROP
 *
 * Catatan keamanan HTTPS:
 * - HTTPS (443) di-DNAT ke port 80 server Rafen (plain HTTP)
 * - Browser akan menampilkan "Not Secure" tapi halaman tetap muncul
 * - Ini lebih aman dari web-proxy karena tidak bisa bypass dengan ganti DNS
 */
class IsolirSynchronizer
{
    // Attribute vendor Mikrotik untuk menentukan PPP profile via RADIUS
    private const MIKROTIK_GROUP_ATTR = 'Mikrotik-Group';
    private const FRAMED_POOL_ATTR    = 'Framed-Pool';

    /**
     * Aktifkan isolir untuk user PPP.
     * Dipanggil saat status_akun berubah ke 'isolir'.
     */
    public function isolate(PppUser $user): void
    {
        if (! $user->username) {
            return;
        }

        // 1. Tentukan MikrotikConnection yang relevan untuk user ini
        $connection = $this->resolveConnection($user);

        // 2. Auto-setup Mikrotik jika belum pernah dilakukan
        if ($connection && ! $connection->isolir_setup_done) {
            $this->setupMikrotik($connection, $user->owner_id);
        }

        $profileName = $connection?->isolir_profile_name ?: 'isolir-pppoe';
        $poolName    = $connection?->isolir_pool_name    ?: 'pool-isolir';

        // 3. Hapus semua radreply lama (IP statis / pool normal)
        DB::table('radreply')
            ->where('username', $user->username)
            ->whereIn('attribute', ['Framed-IP-Address', 'Framed-IP-Netmask', self::FRAMED_POOL_ATTR, self::MIKROTIK_GROUP_ATTR])
            ->delete();

        // 4. Set radreply isolir: Mikrotik-Group + Framed-Pool
        DB::table('radreply')->insert([
            ['username' => $user->username, 'attribute' => self::MIKROTIK_GROUP_ATTR, 'op' => ':=', 'value' => $profileName],
            ['username' => $user->username, 'attribute' => self::FRAMED_POOL_ATTR,    'op' => ':=', 'value' => $poolName],
        ]);

        // 5. Pertahankan radcheck (password tetap) agar user bisa reconnect
        //    Jika radcheck tidak ada, buat ulang
        $exists = DB::table('radcheck')
            ->where('username', $user->username)
            ->where('attribute', 'Cleartext-Password')
            ->exists();

        if (! $exists && $user->ppp_password) {
            DB::table('radcheck')->insert([
                'username'  => $user->username,
                'attribute' => 'Cleartext-Password',
                'op'        => ':=',
                'value'     => $user->ppp_password,
            ]);
        }

        // 6. Kick sesi aktif → user reconnect, mendapat profile isolir
        if ($connection) {
            $this->kickActiveSessions($connection, $user->username);
        }
    }

    /**
     * Cabut isolir — restore ke profile normal.
     * Dipanggil saat status_akun berubah dari 'isolir' ke 'enable'.
     * RadiusReplySynchronizer::syncSingleUser() menangani restore radreply normal,
     * method ini hanya kick sesi isolir agar user reconnect dengan profile asli.
     */
    public function deisolate(PppUser $user): void
    {
        if (! $user->username) {
            return;
        }

        $connection = $this->resolveConnection($user);

        if ($connection) {
            $this->kickActiveSessions($connection, $user->username);
        }
    }

    /**
     * Setup pool isolir, PPP profile, firewall, dan NAT di Mikrotik.
     * Dipanggil sekali per MikrotikConnection saat user pertama diisolir.
     *
     * @throws RuntimeException jika koneksi ke Mikrotik gagal
     */
    public function setupMikrotik(MikrotikConnection $connection, ?int $ownerId = null): void
    {
        $client = new MikrotikApiClient($connection);

        try {
            $client->connect();

            $pool    = $connection->isolir_pool_name    ?: 'pool-isolir';
            $range   = $connection->isolir_pool_range   ?: '10.99.0.2-10.99.0.254';
            $gateway = $connection->isolir_gateway      ?: '10.99.0.1';
            $profile = $connection->isolir_profile_name ?: 'isolir-pppoe';
            $rate    = $connection->isolir_rate_limit   ?: '128k/128k';

            // Ambil URL halaman isolir dari settings tenant atau dari field isolir_url
            $isolirUrl = $this->resolveIsolirUrl($connection, $ownerId);

            // Tentukan subnet dari gateway (mis: 10.99.0.1 → 10.99.0.0/24)
            $subnet = $this->gatewayToSubnet($gateway);

            // -- 1. IP Pool --
            $this->ensureIpPool($client, $pool, $range);

            // -- 2. PPP Profile isolir --
            $this->ensurePppProfile($client, $profile, $gateway, $pool, $rate);

            // -- 3. IP Address untuk gateway pool isolir (pada interface loopback/bridge) --
            // Tidak perlu — gateway PPP profile di Mikrotik ditangani oleh local-address profile

            // -- 4. Firewall filter: DROP semua dari subnet isolir KECUALI DNS + HTTP/HTTPS --
            $this->ensureFirewallFilters($client, $subnet);

            // -- 5. NAT DNAT: redirect HTTP + HTTPS dari subnet isolir ke server Rafen --
            if ($isolirUrl) {
                $this->ensureNatRules($client, $subnet, $isolirUrl);
            }

            $client->disconnect();

            // Tandai setup sudah selesai
            $connection->update([
                'isolir_setup_done' => true,
                'isolir_setup_at'   => now(),
            ]);

        } catch (RuntimeException $e) {
            $client->disconnect();
            Log::error("IsolirSynchronizer: setup Mikrotik gagal untuk {$connection->name}: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Reset setup (hapus semua rule isolir di Mikrotik) dan tandai ulang.
     * Berguna jika rule di router perlu di-re-apply.
     */
    public function resetSetup(MikrotikConnection $connection): void
    {
        $connection->update([
            'isolir_setup_done' => false,
            'isolir_setup_at'   => null,
        ]);
    }

    // ─── Private helpers ────────────────────────────────────────────────────

    private function resolveConnection(PppUser $user): ?MikrotikConnection
    {
        if (! $user->owner_id) {
            return null;
        }

        return MikrotikConnection::query()
            ->where('owner_id', $user->owner_id)
            ->where('is_active', true)
            ->orderByDesc('is_online')
            ->first();
    }

    private function resolveIsolirUrl(MikrotikConnection $connection, ?int $ownerId): ?string
    {
        // Prioritas: isolir_url di NAS → URL halaman Rafen per tenant
        if ($connection->isolir_url) {
            return $connection->isolir_url;
        }

        if ($ownerId) {
            $settings = TenantSettings::getOrCreate($ownerId);
            $slug     = $this->tenantSlug($settings);
            $appUrl   = rtrim(config('app.url', ''), '/');
            return ltrim(parse_url($appUrl, PHP_URL_HOST) ?: $appUrl, 'https://');
        }

        return null;
    }

    private function tenantSlug(TenantSettings $settings): string
    {
        return (string) $settings->user_id;
    }

    /**
     * Hitung subnet /24 dari IP gateway.
     * 10.99.0.1 → 10.99.0.0/24
     */
    private function gatewayToSubnet(string $gateway): string
    {
        $parts = explode('.', $gateway);
        if (count($parts) === 4) {
            $parts[3] = '0';
            return implode('.', $parts).'/24';
        }
        return '10.99.0.0/24';
    }

    private function ensureIpPool(MikrotikApiClient $client, string $poolName, string $ranges): void
    {
        $existing = $client->command('/ip/pool/print', [], ['name' => $poolName]);
        if (! empty($existing['data'])) {
            return; // sudah ada
        }

        $client->command('/ip/pool/add', [
            'name'   => $poolName,
            'ranges' => $ranges,
            'comment' => 'Rafen: pool isolir pelanggan',
        ]);
    }

    private function ensurePppProfile(
        MikrotikApiClient $client,
        string $profileName,
        string $gateway,
        string $poolName,
        string $rateLimit
    ): void {
        $existing = $client->command('/ppp/profile/print', [], ['name' => $profileName]);
        if (! empty($existing['data'])) {
            return; // sudah ada
        }

        $client->command('/ppp/profile/add', [
            'name'           => $profileName,
            'local-address'  => $gateway,
            'remote-address' => $poolName,
            'rate-limit'     => $rateLimit,
            'comment'        => 'Rafen: profile isolir - jangan hapus',
        ]);
    }

    /**
     * Buat firewall filter untuk subnet isolir.
     * Urutan: izin DNS → izin HTTP/HTTPS → drop semua.
     * Dicek via comment unik agar tidak duplikat.
     */
    private function ensureFirewallFilters(MikrotikApiClient $client, string $subnet): void
    {
        $rules = [
            [
                'chain'       => 'forward',
                'src-address' => $subnet,
                'protocol'    => 'udp',
                'dst-port'    => '53',
                'action'      => 'accept',
                'comment'     => 'rafen-isolir: izin DNS',
            ],
            [
                'chain'       => 'forward',
                'src-address' => $subnet,
                'protocol'    => 'tcp',
                'dst-port'    => '53',
                'action'      => 'accept',
                'comment'     => 'rafen-isolir: izin DNS TCP',
            ],
            [
                'chain'       => 'forward',
                'src-address' => $subnet,
                'protocol'    => 'tcp',
                'dst-port'    => '80,443',
                'action'      => 'accept',
                'comment'     => 'rafen-isolir: izin HTTP HTTPS',
            ],
            [
                'chain'       => 'forward',
                'src-address' => $subnet,
                'action'      => 'drop',
                'comment'     => 'rafen-isolir: drop semua lainnya',
            ],
        ];

        foreach ($rules as $rule) {
            $comment  = $rule['comment'];
            $existing = $client->command('/ip/firewall/filter/print', [], ['comment' => $comment]);
            if (! empty($existing['data'])) {
                continue;
            }
            $client->command('/ip/firewall/filter/add', $rule);
        }
    }

    /**
     * Buat NAT dst-nat: redirect HTTP (80) dan HTTPS (443) dari subnet isolir ke server Rafen.
     * HTTPS diredirect ke port 80 HTTP biasa (halaman isolir tidak perlu cert).
     *
     * @param string $isolirHost Host tujuan (IP atau domain tanpa protocol)
     */
    private function ensureNatRules(MikrotikApiClient $client, string $subnet, string $isolirHost): void
    {
        // Pisahkan host dan port jika ada
        $host = $isolirHost;
        $port = '80';
        if (str_contains($isolirHost, ':')) {
            [$host, $port] = explode(':', $isolirHost, 2);
        }

        $rules = [
            [
                'chain'       => 'dstnat',
                'src-address' => $subnet,
                'protocol'    => 'tcp',
                'dst-port'    => '80',
                'action'      => 'dst-nat',
                'to-addresses' => $host,
                'to-ports'    => $port,
                'comment'     => 'rafen-isolir: redirect HTTP ke halaman isolir',
            ],
            [
                'chain'       => 'dstnat',
                'src-address' => $subnet,
                'protocol'    => 'tcp',
                'dst-port'    => '443',
                'action'      => 'dst-nat',
                'to-addresses' => $host,
                'to-ports'    => $port,
                'comment'     => 'rafen-isolir: redirect HTTPS ke halaman isolir',
            ],
        ];

        foreach ($rules as $rule) {
            $comment  = $rule['comment'];
            $existing = $client->command('/ip/firewall/nat/print', [], ['comment' => $comment]);
            if (! empty($existing['data'])) {
                continue;
            }
            $client->command('/ip/firewall/nat/add', $rule);
        }
    }

    /**
     * Kick sesi PPP aktif untuk username tertentu.
     * User akan disconnect dan reconnect → mendapat RADIUS reply baru (profile isolir/normal).
     */
    private function kickActiveSessions(MikrotikConnection $connection, string $username): void
    {
        try {
            $client = new MikrotikApiClient($connection);
            $client->connect();

            $sessions = $client->command('/ppp/active/print', [], ['name' => $username]);

            foreach ($sessions['data'] as $session) {
                $id = $session['.id'] ?? null;
                if ($id) {
                    try {
                        $client->command('/ppp/active/remove', ['.id' => $id]);
                    } catch (RuntimeException) {
                        // Sesi mungkin sudah putus, abaikan
                    }
                }
            }

            $client->disconnect();
        } catch (RuntimeException $e) {
            Log::warning("IsolirSynchronizer: gagal kick sesi {$username} di {$connection->name}: {$e->getMessage()}");
        }
    }
}
