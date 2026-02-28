<?php

namespace App\Services;

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

        $users = PppUser::query()
            ->where('status_akun', 'enable')
            ->whereNotNull('username')
            ->whereNotNull('ppp_password')
            ->with('profile')
            ->get();

        $count = 0;

        foreach ($users as $user) {
            $this->syncUser($user);
            $count++;
        }

        // Remove radcheck/radreply for users no longer active
        $activeUsernames = $users->pluck('username')->all();
        DB::table('radcheck')->whereNotIn('username', $activeUsernames)->delete();
        DB::table('radreply')->whereNotIn('username', $activeUsernames)->delete();

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

        // --- radreply: IP ---
        $this->syncIpReply($user, $group);

        // --- radreply: PPP profile (rate limit) via Mikrotik-Rate-Limit or Framed-Pool ---
        $this->syncProfileReply($user, $group);
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

    private function syncIpReply(PppUser $user, ?ProfileGroup $group): void
    {
        $username = $user->username;

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

    private function syncProfileReply(PppUser $user, ?ProfileGroup $group): void
    {
        // Only send Framed-Pool when mode is group_only AND pool name is set.
        // For sql mode the IP is already assigned as Framed-IP-Address — no Framed-Pool needed.
        if (! $group || $group->ip_pool_mode !== 'group_only' || ! $group->ip_pool_name) {
            return;
        }

        DB::table('radreply')->updateOrInsert(
            ['username' => $user->username, 'attribute' => 'Framed-Pool'],
            ['op' => ':=', 'value' => $group->ip_pool_name]
        );
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
