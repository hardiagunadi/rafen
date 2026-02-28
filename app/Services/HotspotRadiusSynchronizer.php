<?php

namespace App\Services;

use App\Models\HotspotUser;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;

class HotspotRadiusSynchronizer
{
    /**
     * Sync hotspot_users + vouchers to radcheck/radreply.
     * Returns total entries synced.
     */
    public function sync(): int
    {
        $count = 0;

        $count += $this->syncHotspotUsers();
        $count += $this->syncVouchers();

        return $count;
    }

    private function syncHotspotUsers(): int
    {
        $users = HotspotUser::query()
            ->where('status_akun', 'enable')
            ->whereNotNull('username')
            ->whereNotNull('hotspot_password')
            ->with(['hotspotProfile.bandwidthProfile', 'hotspotProfile.profileGroup'])
            ->get();

        foreach ($users as $user) {
            // radcheck: password
            DB::table('radcheck')->updateOrInsert(
                ['username' => $user->username, 'attribute' => 'Cleartext-Password'],
                ['op' => ':=', 'value' => $user->hotspot_password]
            );

            // radcheck: simultaneous-use (shared_users dari profil, default 1)
            $sharedUsers = $user->hotspotProfile?->shared_users ?? 1;
            DB::table('radcheck')->updateOrInsert(
                ['username' => $user->username, 'attribute' => 'Simultaneous-Use'],
                ['op' => ':=', 'value' => (string) $sharedUsers]
            );

            // radreply: rate limit
            $rateLimit = $this->resolveRateLimit($user->hotspotProfile?->bandwidthProfile);
            if ($rateLimit) {
                DB::table('radreply')->updateOrInsert(
                    ['username' => $user->username, 'attribute' => 'Mikrotik-Rate-Limit'],
                    ['op' => ':=', 'value' => $rateLimit]
                );
            }

            // radreply: Framed-Pool (group_only dengan ip_pool_name)
            $group = $user->hotspotProfile?->profileGroup
                ?? ($user->profile_group_id ? \App\Models\ProfileGroup::find($user->profile_group_id) : null);

            if ($group && $group->ip_pool_mode === 'group_only' && $group->ip_pool_name) {
                DB::table('radreply')->updateOrInsert(
                    ['username' => $user->username, 'attribute' => 'Framed-Pool'],
                    ['op' => ':=', 'value' => $group->ip_pool_name]
                );
            } else {
                DB::table('radreply')
                    ->where('username', $user->username)
                    ->where('attribute', 'Framed-Pool')
                    ->delete();
            }
        }

        // Remove disabled/deleted hotspot users from radcheck/radreply
        $activeUsernames = $users->pluck('username')->all();

        // Only remove entries that came from hotspot_users (not ppp_users)
        $allHotspotUsernames = HotspotUser::pluck('username')->all();
        $toRemove = array_diff($allHotspotUsernames, $activeUsernames);
        if ($toRemove) {
            DB::table('radcheck')->whereIn('username', $toRemove)->delete();
            DB::table('radreply')->whereIn('username', $toRemove)->delete();
        }

        return $users->count();
    }

    private function syncVouchers(): int
    {
        // Only sync unused vouchers — used/expired should still be able to auth
        // until they are cleaned up. Include both unused and used (but not expired).
        $vouchers = Voucher::query()
            ->whereIn('status', ['unused', 'used'])
            ->whereNotNull('username')
            ->whereNotNull('password')
            ->with(['hotspotProfile.bandwidthProfile', 'hotspotProfile.profileGroup'])
            ->get();

        foreach ($vouchers as $voucher) {
            // radcheck: password
            DB::table('radcheck')->updateOrInsert(
                ['username' => $voucher->username, 'attribute' => 'Cleartext-Password'],
                ['op' => ':=', 'value' => $voucher->password]
            );

            // radcheck: simultaneous-use
            $sharedUsers = $voucher->hotspotProfile?->shared_users ?? 1;
            DB::table('radcheck')->updateOrInsert(
                ['username' => $voucher->username, 'attribute' => 'Simultaneous-Use'],
                ['op' => ':=', 'value' => (string) $sharedUsers]
            );

            // radreply: rate limit
            $rateLimit = $this->resolveRateLimit($voucher->hotspotProfile?->bandwidthProfile);
            if ($rateLimit) {
                DB::table('radreply')->updateOrInsert(
                    ['username' => $voucher->username, 'attribute' => 'Mikrotik-Rate-Limit'],
                    ['op' => ':=', 'value' => $rateLimit]
                );
            }

            // radreply: Framed-Pool
            $group = $voucher->hotspotProfile?->profileGroup
                ?? ($voucher->profile_group_id ? \App\Models\ProfileGroup::find($voucher->profile_group_id) : null);

            if ($group && $group->ip_pool_mode === 'group_only' && $group->ip_pool_name) {
                DB::table('radreply')->updateOrInsert(
                    ['username' => $voucher->username, 'attribute' => 'Framed-Pool'],
                    ['op' => ':=', 'value' => $group->ip_pool_name]
                );
            } else {
                DB::table('radreply')
                    ->where('username', $voucher->username)
                    ->where('attribute', 'Framed-Pool')
                    ->delete();
            }
        }

        // Remove expired vouchers from radcheck/radreply
        $expiredUsernames = Voucher::where('status', 'expired')->pluck('username')->all();
        if ($expiredUsernames) {
            DB::table('radcheck')->whereIn('username', $expiredUsernames)->delete();
            DB::table('radreply')->whereIn('username', $expiredUsernames)->delete();
        }

        return $vouchers->count();
    }

    private function resolveRateLimit(?\App\Models\BandwidthProfile $bw): ?string
    {
        if (! $bw) {
            return null;
        }

        $up   = (int) ($bw->upload_max_mbps ?? 0);
        $down = (int) ($bw->download_max_mbps ?? 0);

        if ($up <= 0 && $down <= 0) {
            return null;
        }

        return "{$up}M/{$down}M";
    }
}
