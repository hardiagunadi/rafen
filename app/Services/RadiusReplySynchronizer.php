<?php

namespace App\Services;

use App\Models\BandwidthProfile;
use App\Models\ProfileGroup;
use App\Models\PppProfile;
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

        // Load all relevant data upfront
        $enabledUsers = PppUser::query()
            ->where('status_akun', 'enable')
            ->whereNotNull('username')
            ->whereNotNull('ppp_password')
            ->with(['profile.bandwidthProfile', 'profile.profileGroup'])
            ->get();

        $isolirUsers = PppUser::query()
            ->where('status_akun', 'isolir')
            ->whereNotNull('username')
            ->whereNotNull('ppp_password')
            ->get();

        // Bulk radcheck for enabled users
        $radcheckRows = [];
        foreach ($enabledUsers as $user) {
            $radcheckRows[] = ['username' => $user->username, 'attribute' => 'Cleartext-Password', 'op' => ':=', 'value' => $user->ppp_password];
        }
        // Bulk radcheck for isolir users
        foreach ($isolirUsers as $user) {
            $radcheckRows[] = ['username' => $user->username, 'attribute' => 'Cleartext-Password', 'op' => ':=', 'value' => $user->ppp_password];
        }

        if ($radcheckRows) {
            DB::table('radcheck')->upsert($radcheckRows, ['username', 'attribute'], ['op', 'value']);
        }

        // Build radreply rows for enabled users
        $radreplyRows    = [];
        $deleteIpFor     = []; // usernames where Framed-IP should be cleared
        $deletePoolFor   = []; // usernames where Framed-Pool should be cleared
        $deleteGroupFor  = []; // usernames where Mikrotik-Group should be cleared
        $deleteQueueFor  = []; // usernames where Mikrotik-Queue-Parent-Name should be cleared
        $deleteRateFor   = []; // usernames where Mikrotik-Rate-Limit should be cleared

        foreach ($enabledUsers as $user) {
            $group     = $this->resolveProfileGroup($user);
            $bandwidth = $this->resolveBandwidthProfile($user);
            $username  = $user->username;

            // Mikrotik-Group
            if ($group && $group->name) {
                $radreplyRows[] = ['username' => $username, 'attribute' => 'Mikrotik-Group', 'op' => ':=', 'value' => $group->name];
            } else {
                $deleteGroupFor[] = $username;
            }

            // Framed-Pool (group_only)
            if ($group && $group->ip_pool_mode === 'group_only' && $group->ip_pool_name) {
                $radreplyRows[] = ['username' => $username, 'attribute' => 'Framed-Pool', 'op' => ':=', 'value' => $group->ip_pool_name];
            } else {
                $deletePoolFor[] = $username;
            }

            // Rate-limit
            if ($bandwidth) {
                $radreplyRows[] = ['username' => $username, 'attribute' => 'Mikrotik-Rate-Limit', 'op' => ':=', 'value' => $this->buildRateLimit($bandwidth)];
            } else {
                $deleteRateFor[] = $username;
            }

            // Queue parent
            $parentQueue = ($group && $group->parent_queue) ? $group->parent_queue : ($user->profile?->parent_queue ?: null);
            if ($parentQueue) {
                $radreplyRows[] = ['username' => $username, 'attribute' => 'Mikrotik-Queue-Parent-Name', 'op' => ':=', 'value' => $parentQueue];
            } else {
                $deleteQueueFor[] = $username;
            }

            // IP address
            $ipRows = $this->buildIpReplyRows($user, $group);
            if ($ipRows !== null) {
                array_push($radreplyRows, ...$ipRows);
            } else {
                $deleteIpFor[] = $username;
            }
        }

        // Bulk upsert radreply
        foreach (array_chunk($radreplyRows, 500) as $chunk) {
            DB::table('radreply')->upsert($chunk, ['username', 'attribute'], ['op', 'value']);
        }

        // Clean up stale attributes for enabled users
        if ($deleteGroupFor) {
            DB::table('radreply')->whereIn('username', $deleteGroupFor)->where('attribute', 'Mikrotik-Group')->delete();
        }
        if ($deletePoolFor) {
            DB::table('radreply')->whereIn('username', $deletePoolFor)->where('attribute', 'Framed-Pool')->delete();
        }
        if ($deleteRateFor) {
            DB::table('radreply')->whereIn('username', $deleteRateFor)->where('attribute', 'Mikrotik-Rate-Limit')->delete();
        }
        if ($deleteQueueFor) {
            DB::table('radreply')->whereIn('username', $deleteQueueFor)->where('attribute', 'Mikrotik-Queue-Parent-Name')->delete();
        }
        if ($deleteIpFor) {
            DB::table('radreply')->whereIn('username', $deleteIpFor)->whereIn('attribute', ['Framed-IP-Address', 'Framed-IP-Netmask'])->delete();
        }

        // Remove radcheck/radreply for users that are neither enable nor isolir
        $keepUsernames = $enabledUsers->pluck('username')
            ->merge($isolirUsers->pluck('username'))
            ->unique()
            ->all();

        if ($keepUsernames) {
            DB::table('radcheck')->whereNotIn('username', $keepUsernames)->delete();
            DB::table('radreply')->whereNotIn('username', $keepUsernames)->delete();
        } else {
            DB::table('radcheck')->delete();
            DB::table('radreply')->delete();
        }

        return $enabledUsers->count();
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
        $this->syncProfileReply($user, $group, $bandwidth, $user->profile);
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

    private function syncProfileReply(PppUser $user, ?ProfileGroup $group, ?BandwidthProfile $bandwidth, ?PppProfile $profile): void
    {
        $username = $user->username;

        // Mikrotik-Group: nama PPP profile di Mikrotik = nama ProfileGroup
        // Wajib dikirim agar Mikrotik pakai profile yang benar (local-address, dll)
        if ($group && $group->name) {
            DB::table('radreply')->updateOrInsert(
                ['username' => $username, 'attribute' => 'Mikrotik-Group'],
                ['op' => ':=', 'value' => $group->name]
            );
        } else {
            DB::table('radreply')
                ->where('username', $username)
                ->where('attribute', 'Mikrotik-Group')
                ->delete();
        }

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

        // Mikrotik-Queue-Parent-Name: prioritas ProfileGroup.parent_queue,
        // fallback ke PppProfile.parent_queue jika ProfileGroup tidak set.
        // Jika keduanya kosong, hapus attribute.
        $parentQueue = ($group && $group->parent_queue)
            ? $group->parent_queue
            : ($profile?->parent_queue ?: null);

        if ($parentQueue) {
            DB::table('radreply')->updateOrInsert(
                ['username' => $username, 'attribute' => 'Mikrotik-Queue-Parent-Name'],
                ['op' => ':=', 'value' => $parentQueue]
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
     *
     * Format lengkap Mikrotik-Rate-Limit (RouterOS):
     *   rx/tx [burst-rx/burst-tx [burst-threshold-rx/burst-threshold-tx
     *          [burst-time-rx/burst-time-tx [priority [limit-at-rx/limit-at-tx]]]]]
     *
     * - Jika min > 0: kirim 6 field dengan limit-at di posisi ke-6
     *   → "maxU/maxD 0/0 0/0 0/0 8 minU/minD"
     * - Jika min = 0: kirim hanya max
     *   → "maxU/maxD"
     */
    private function buildRateLimit(BandwidthProfile $bp): string
    {
        $ulMax = (int) $bp->upload_max_mbps;
        $dlMax = (int) $bp->download_max_mbps;
        $ulMin = (int) $bp->upload_min_mbps;
        $dlMin = (int) $bp->download_min_mbps;

        if ($ulMin > 0 || $dlMin > 0) {
            // max / no-burst / no-burst-threshold / no-burst-time / priority=8 / limit-at
            return "{$ulMax}M/{$dlMax}M 0/0 0/0 0/0 8 {$ulMin}M/{$dlMin}M";
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
