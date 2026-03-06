<?php

namespace App\Services;

use App\Models\BandwidthProfile;
use App\Models\ProfileGroup;
use App\Models\PppUser;
use Illuminate\Support\Facades\DB;

class RadiusReplySynchronizer
{
    /** IP already assigned in this sync run, keyed by group_id → [ip_long => true] */
    private array $reservedIps = [];

    /**
     * Sync radcheck + radreply from ppp_users.
     * Returns count of users synced.
     */
    public function sync(): int
    {
        $this->reservedIps = [];

        // Sync user enable (normal)
        $enabledUsers = PppUser::query()
            ->where('status_akun', 'enable')
            ->whereNotNull('username')
            ->whereNotNull('ppp_password')
            ->with('profile')
            ->get();

        $count = 0;

        foreach ($enabledUsers as $user) {
            // Hapus Mikrotik-Group isolir jika masih ada
            DB::table('radreply')
                ->where('username', $user->username)
                ->where('attribute', 'Mikrotik-Group')
                ->delete();
            $this->syncUser($user);
            $count++;
        }

        // Sync user isolir: pastikan radcheck ada, jangan hapus radreply isolir
        $isolirUsers = PppUser::query()
            ->where('status_akun', 'isolir')
            ->whereNotNull('username')
            ->whereNotNull('ppp_password')
            ->get();

        foreach ($isolirUsers as $user) {
            DB::table('radcheck')->updateOrInsert(
                ['username' => $user->username, 'attribute' => 'Cleartext-Password'],
                ['op' => ':=', 'value' => $user->ppp_password]
            );
        }

        // Hapus radcheck/radreply untuk user yang bukan enable maupun isolir
        $keepUsernames = $enabledUsers->pluck('username')
            ->merge($isolirUsers->pluck('username'))
            ->unique()
            ->all();

        DB::table('radcheck')->whereNotIn('username', $keepUsernames)->delete();
        DB::table('radreply')->whereNotIn('username', $keepUsernames)->delete();

        return $count;
    }

    private function syncUser(PppUser $user): void
    {
        // --- radcheck: password ---
        DB::table('radcheck')->updateOrInsert(
            ['username' => $user->username, 'attribute' => 'Cleartext-Password'],
            ['op' => ':=', 'value' => $user->ppp_password]
        );

        // --- resolve profile group ---
        $group = $this->resolveProfileGroup($user);

        // --- resolve bandwidth profile ---
        $bandwidth = $this->resolveBandwidthProfile($user);

        // --- radreply: IP ---
        $this->syncIpReply($user, $group);

        // --- radreply: rate-limit + queue parent ---
        $this->syncProfileReply($user, $group, $bandwidth);
    }

    private function resolveProfileGroup(PppUser $user): ?ProfileGroup
    {
        // ppp_users.profile_group_id takes precedence
        if ($user->profile_group_id) {
            return ProfileGroup::find($user->profile_group_id);
        }

        // fallback: via ppp_profile → profile_group_id
        if ($user->profile && $user->profile->profile_group_id) {
            return ProfileGroup::find($user->profile->profile_group_id);
        }

        return null;
    }

    /**
     * Sync a single user's radcheck + radreply. Used after save/update in controller.
     */
    public function syncSingleUser(PppUser $user): void
    {
        $user->refresh();

        if (! $user->username) {
            return;
        }

        // User isolir: pertahankan radcheck (agar bisa reconnect) tapi jangan overwrite
        // radreply — IsolirSynchronizer yang menangani radreply isolir.
        if ($user->status_akun === 'isolir') {
            if ($user->ppp_password) {
                DB::table('radcheck')->updateOrInsert(
                    ['username' => $user->username, 'attribute' => 'Cleartext-Password'],
                    ['op' => ':=', 'value' => $user->ppp_password]
                );
            }
            return;
        }

        if ($user->status_akun !== 'enable' || ! $user->ppp_password) {
            // User disable atau belum lengkap — hapus dari RADIUS sepenuhnya
            DB::table('radcheck')->where('username', $user->username)->delete();
            DB::table('radreply')->where('username', $user->username)->delete();
            return;
        }

        // User enable: hapus Mikrotik-Group isolir jika masih ada (sisa dari isolir)
        DB::table('radreply')
            ->where('username', $user->username)
            ->where('attribute', 'Mikrotik-Group')
            ->delete();

        $this->syncUser($user);
    }

    private function syncIpReply(PppUser $user, ?ProfileGroup $group): void
    {
        $username = $user->username;

        // User tipe_ip='dhcp' dengan SQL pool → assign IP dari pool (seperti static tanpa IP)
        // User tipe_ip='dhcp' tanpa profile group → Mikrotik assign dari PPP Profile lokal,
        //   tidak perlu Framed-IP-Address dari RADIUS.
        if ($user->tipe_ip === 'dhcp') {
            // Hapus Framed-IP-Address stale
            DB::table('radreply')
                ->where('username', $username)
                ->whereIn('attribute', ['Framed-IP-Address', 'Framed-IP-Netmask'])
                ->delete();

            if (! $group || $group->ip_pool_mode !== 'sql') {
                // Tidak ada SQL pool — biarkan Mikrotik yang assign via PPP Profile lokal
                return;
            }

            // Ada SQL pool: cek apakah user sudah punya ip_static yang tersimpan
            $existingIp = $user->ip_static;
            if ($existingIp && $existingIp !== 'Automatic') {
                DB::table('radreply')->updateOrInsert(
                    ['username' => $username, 'attribute' => 'Framed-IP-Address'],
                    ['op' => ':=', 'value' => $existingIp]
                );
                DB::table('radreply')->updateOrInsert(
                    ['username' => $username, 'attribute' => 'Framed-IP-Netmask'],
                    ['op' => ':=', 'value' => '255.255.255.255']
                );
                return;
            }

            // Belum ada IP — assign dari SQL pool
            $assignedIp = $this->assignNextIp($group, $user);
            if ($assignedIp) {
                $user->update(['ip_static' => $assignedIp]);
                DB::table('radreply')->updateOrInsert(
                    ['username' => $username, 'attribute' => 'Framed-IP-Address'],
                    ['op' => ':=', 'value' => $assignedIp]
                );
                DB::table('radreply')->updateOrInsert(
                    ['username' => $username, 'attribute' => 'Framed-IP-Netmask'],
                    ['op' => ':=', 'value' => '255.255.255.255']
                );
            }
            return;
        }

        // Static IP assigned
        $ip = $user->ip_static;
        if ($ip && $ip !== 'Automatic') {
            DB::table('radreply')->updateOrInsert(
                ['username' => $username, 'attribute' => 'Framed-IP-Address'],
                ['op' => ':=', 'value' => $ip]
            );
            // Netmask standard PPPoE
            DB::table('radreply')->updateOrInsert(
                ['username' => $username, 'attribute' => 'Framed-IP-Netmask'],
                ['op' => ':=', 'value' => '255.255.255.255']
            );
            // Remove Framed-Pool if static IP is set
            DB::table('radreply')
                ->where('username', $username)
                ->where('attribute', 'Framed-Pool')
                ->delete();
            return;
        }

        // No static IP → remove any stale Framed-IP-Address / Framed-IP-Netmask
        DB::table('radreply')
            ->where('username', $username)
            ->whereIn('attribute', ['Framed-IP-Address', 'Framed-IP-Netmask'])
            ->delete();

        if (! $group) {
            return;
        }

        // Pool mode: group_only → send Framed-Pool name to Mikrotik
        if ($group->ip_pool_mode === 'group_only' && $group->ip_pool_name) {
            DB::table('radreply')->updateOrInsert(
                ['username' => $username, 'attribute' => 'Framed-Pool'],
                ['op' => ':=', 'value' => $group->ip_pool_name]
            );
            DB::table('radreply')
                ->where('username', $username)
                ->whereIn('attribute', ['Framed-IP-Address', 'Framed-IP-Netmask'])
                ->delete();
            return;
        }

        // ip_pool_mode = sql but ip_static not yet assigned — assign now
        if ($group->ip_pool_mode === 'sql') {
            $assignedIp = $this->assignNextIp($group, $user);
            if ($assignedIp) {
                $user->update(['ip_static' => $assignedIp]);
                DB::table('radreply')->updateOrInsert(
                    ['username' => $username, 'attribute' => 'Framed-IP-Address'],
                    ['op' => ':=', 'value' => $assignedIp]
                );
                DB::table('radreply')->updateOrInsert(
                    ['username' => $username, 'attribute' => 'Framed-IP-Netmask'],
                    ['op' => ':=', 'value' => '255.255.255.255']
                );
            }
        }
    }

    private function syncProfileReply(PppUser $user, ?ProfileGroup $group, ?BandwidthProfile $bandwidth): void
    {
        $username = $user->username;

        // Framed-Pool: hanya untuk group_only mode
        if ($group && $group->ip_pool_mode === 'group_only' && $group->ip_pool_name) {
            DB::table('radreply')->updateOrInsert(
                ['username' => $username, 'attribute' => 'Framed-Pool'],
                ['op' => ':=', 'value' => $group->ip_pool_name]
            );
        }

        // Mikrotik-Rate-Limit: dari BandwidthProfile
        // Format: ul_min/dl_min ul_max/dl_max (burst: guaranteed/max)
        // Contoh: 3M/3M 5M/5M → burst-threshold/burst-limit
        if ($bandwidth) {
            $rateLimit = $this->buildRateLimit($bandwidth);
            DB::table('radreply')->updateOrInsert(
                ['username' => $username, 'attribute' => 'Mikrotik-Rate-Limit'],
                ['op' => ':=', 'value' => $rateLimit]
            );
        } else {
            // Tidak ada BandwidthProfile — hapus rate-limit lama jika ada
            DB::table('radreply')
                ->where('username', $username)
                ->where('attribute', 'Mikrotik-Rate-Limit')
                ->delete();
        }

        // Mikrotik-Queue-Parent-Name: dari ProfileGroup.parent_queue
        // Agar queue masuk ke parent yang benar (mis: "0. PPPOe Pelanggan")
        if ($group && $group->parent_queue) {
            DB::table('radreply')->updateOrInsert(
                ['username' => $username, 'attribute' => 'Mikrotik-Queue-Parent-Name'],
                ['op' => ':=', 'value' => $group->parent_queue]
            );
        } else {
            DB::table('radreply')
                ->where('username', $username)
                ->where('attribute', 'Mikrotik-Queue-Parent-Name')
                ->delete();
        }
    }

    /**
     * Build Mikrotik-Rate-Limit string dari BandwidthProfile.
     * Format Mikrotik: rx/tx limit-at/limit-at max-limit/max-limit
     * rx = upload pelanggan (dari perspektif router = upload)
     * tx = download pelanggan
     *
     * Format yang didukung:
     * - Jika min > 0: "ul_minM/dl_minM ul_maxM/dl_maxM" (dengan burst/guaranteed)
     * - Jika min = 0: "ul_maxM/dl_maxM" (simple max only)
     */
    private function buildRateLimit(BandwidthProfile $bp): string
    {
        $ulMax = (int) $bp->upload_max_mbps;
        $dlMax = (int) $bp->download_max_mbps;
        $ulMin = (int) $bp->upload_min_mbps;
        $dlMin = (int) $bp->download_min_mbps;

        // Format dalam bps (Mikrotik default = bps, suffix M = Mbps)
        if ($ulMin > 0 || $dlMin > 0) {
            // ul_max/dl_max ul_min/dl_min (max-limit/guaranteed)
            return "{$ulMax}M/{$dlMax}M {$ulMin}M/{$dlMin}M";
        }

        return "{$ulMax}M/{$dlMax}M";
    }

    private function resolveBandwidthProfile(PppUser $user): ?BandwidthProfile
    {
        // Dari profile user → bandwidth_profile_id
        if ($user->profile && $user->profile->bandwidth_profile_id) {
            return BandwidthProfile::find($user->profile->bandwidth_profile_id);
        }
        return null;
    }

    private function assignNextIp(ProfileGroup $group, PppUser $current): ?string
    {
        $rangeStart = $group->range_start ?: $group->host_min;
        $rangeEnd = $group->range_end ?: $group->host_max;

        if (! $rangeStart || ! $rangeEnd) {
            return null;
        }

        $startLong = ip2long($rangeStart);
        $endLong = ip2long($rangeEnd);

        if ($startLong === false || $endLong === false || $startLong > $endLong) {
            return null;
        }

        // IPs already persisted in DB within this group's range (excluding current user)
        $usedInDb = PppUser::query()
            ->whereNotNull('ip_static')
            ->where('ip_static', '!=', 'Automatic')
            ->whereKeyNot($current->id)
            ->pluck('ip_static')
            ->map(fn (string $ip) => ip2long($ip))
            ->filter(fn ($v) => $v !== false && $v >= $startLong && $v <= $endLong)
            ->unique()
            ->flip()
            ->all();

        // IPs reserved in this sync run for this group
        $reservedThisRun = $this->reservedIps[$group->id] ?? [];

        $usedIps = $usedInDb + $reservedThisRun;

        for ($i = $startLong; $i <= $endLong; $i++) {
            if (! isset($usedIps[$i])) {
                // Reserve this IP for rest of sync
                $this->reservedIps[$group->id][$i] = true;

                return long2ip($i);
            }
        }

        return null;
    }
}
