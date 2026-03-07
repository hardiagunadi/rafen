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

    /**
     * Sync a single hotspot user's radcheck + radreply. Used after save/update in controller.
     */
    public function syncSingleUser(HotspotUser $user): void
    {
        $user->refresh();

        if (! $user->username) {
            return;
        }

        if ($user->status_akun !== 'enable' || ! $user->hotspot_password) {
            DB::table('radcheck')->where('username', $user->username)->delete();
            DB::table('radreply')->where('username', $user->username)->delete();
            return;
        }

        $user->load(['hotspotProfile.bandwidthProfile', 'hotspotProfile.profileGroup']);

        DB::table('radcheck')->updateOrInsert(
            ['username' => $user->username, 'attribute' => 'Cleartext-Password'],
            ['op' => ':=', 'value' => $user->hotspot_password]
        );

        $sharedUsers = $user->hotspotProfile?->shared_users ?? 1;
        DB::table('radcheck')->updateOrInsert(
            ['username' => $user->username, 'attribute' => 'Simultaneous-Use'],
            ['op' => ':=', 'value' => (string) $sharedUsers]
        );

        $rateLimit = $this->resolveRateLimit($user->hotspotProfile?->bandwidthProfile);
        if ($rateLimit) {
            DB::table('radreply')->updateOrInsert(
                ['username' => $user->username, 'attribute' => 'Mikrotik-Rate-Limit'],
                ['op' => ':=', 'value' => $rateLimit]
            );
        } else {
            DB::table('radreply')->where('username', $user->username)->where('attribute', 'Mikrotik-Rate-Limit')->delete();
        }

        $group = $user->hotspotProfile?->profileGroup
            ?? ($user->profile_group_id ? \App\Models\ProfileGroup::find($user->profile_group_id) : null);

        if ($group && $group->ip_pool_mode === 'group_only' && $group->ip_pool_name) {
            DB::table('radreply')->updateOrInsert(
                ['username' => $user->username, 'attribute' => 'Framed-Pool'],
                ['op' => ':=', 'value' => $group->ip_pool_name]
            );
        } else {
            DB::table('radreply')->where('username', $user->username)->where('attribute', 'Framed-Pool')->delete();
        }

        $parentQueue = ($group && $group->parent_queue)
            ? $group->parent_queue
            : ($user->hotspotProfile?->parent_queue ?: null);

        if ($parentQueue) {
            DB::table('radreply')->updateOrInsert(
                ['username' => $user->username, 'attribute' => 'Mikrotik-Queue-Parent-Name'],
                ['op' => ':=', 'value' => $parentQueue]
            );
        } else {
            DB::table('radreply')->where('username', $user->username)->where('attribute', 'Mikrotik-Queue-Parent-Name')->delete();
        }
    }

    private function syncHotspotUsers(): int
    {
        $users = HotspotUser::query()
            ->where('status_akun', 'enable')
            ->whereNotNull('username')
            ->whereNotNull('hotspot_password')
            ->with(['hotspotProfile.bandwidthProfile', 'hotspotProfile.profileGroup'])
            ->get();

        $radcheckRows  = [];
        $radreplyRows  = [];
        $deletePoolFor  = [];
        $deleteRateFor  = [];
        $deleteQueueFor = [];

        foreach ($users as $user) {
            $username = $user->username;

            // radcheck rows
            $radcheckRows[] = ['username' => $username, 'attribute' => 'Cleartext-Password', 'op' => ':=', 'value' => $user->hotspot_password];
            $sharedUsers    = $user->hotspotProfile?->shared_users ?? 1;
            $radcheckRows[] = ['username' => $username, 'attribute' => 'Simultaneous-Use', 'op' => ':=', 'value' => (string) $sharedUsers];

            // rate limit
            $rateLimit = $this->resolveRateLimit($user->hotspotProfile?->bandwidthProfile);
            if ($rateLimit) {
                $radreplyRows[] = ['username' => $username, 'attribute' => 'Mikrotik-Rate-Limit', 'op' => ':=', 'value' => $rateLimit];
            } else {
                $deleteRateFor[] = $username;
            }

            // Framed-Pool
            $group = $user->hotspotProfile?->profileGroup
                ?? ($user->profile_group_id ? \App\Models\ProfileGroup::find($user->profile_group_id) : null);

            if ($group && $group->ip_pool_mode === 'group_only' && $group->ip_pool_name) {
                $radreplyRows[] = ['username' => $username, 'attribute' => 'Framed-Pool', 'op' => ':=', 'value' => $group->ip_pool_name];
            } else {
                $deletePoolFor[] = $username;
            }

            // Queue parent
            $parentQueue = ($group && $group->parent_queue)
                ? $group->parent_queue
                : ($user->hotspotProfile?->parent_queue ?: null);

            if ($parentQueue) {
                $radreplyRows[] = ['username' => $username, 'attribute' => 'Mikrotik-Queue-Parent-Name', 'op' => ':=', 'value' => $parentQueue];
            } else {
                $deleteQueueFor[] = $username;
            }
        }

        // Bulk upsert
        foreach (array_chunk($radcheckRows, 500) as $chunk) {
            DB::table('radcheck')->upsert($chunk, ['username', 'attribute'], ['op', 'value']);
        }
        foreach (array_chunk($radreplyRows, 500) as $chunk) {
            DB::table('radreply')->upsert($chunk, ['username', 'attribute'], ['op', 'value']);
        }

        // Clean up stale attributes
        if ($deleteRateFor) {
            DB::table('radreply')->whereIn('username', $deleteRateFor)->where('attribute', 'Mikrotik-Rate-Limit')->delete();
        }
        if ($deletePoolFor) {
            DB::table('radreply')->whereIn('username', $deletePoolFor)->where('attribute', 'Framed-Pool')->delete();
        }
        if ($deleteQueueFor) {
            DB::table('radreply')->whereIn('username', $deleteQueueFor)->where('attribute', 'Mikrotik-Queue-Parent-Name')->delete();
        }

        // Remove disabled/deleted hotspot users from radcheck/radreply
        $activeUsernames     = $users->pluck('username')->all();
        $allHotspotUsernames = HotspotUser::pluck('username')->all();
        $toRemove            = array_diff($allHotspotUsernames, $activeUsernames);
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

        $radcheckRows  = [];
        $radreplyRows  = [];
        $deleteRateFor = [];
        $deletePoolFor = [];
        $deleteQueueFor = [];

        foreach ($vouchers as $voucher) {
            $username = $voucher->username;

            $radcheckRows[] = ['username' => $username, 'attribute' => 'Cleartext-Password', 'op' => ':=', 'value' => $voucher->password];
            $sharedUsers    = $voucher->hotspotProfile?->shared_users ?? 1;
            $radcheckRows[] = ['username' => $username, 'attribute' => 'Simultaneous-Use', 'op' => ':=', 'value' => (string) $sharedUsers];

            $rateLimit = $this->resolveRateLimit($voucher->hotspotProfile?->bandwidthProfile);
            if ($rateLimit) {
                $radreplyRows[] = ['username' => $username, 'attribute' => 'Mikrotik-Rate-Limit', 'op' => ':=', 'value' => $rateLimit];
            } else {
                $deleteRateFor[] = $username;
            }

            $group = $voucher->hotspotProfile?->profileGroup
                ?? ($voucher->profile_group_id ? \App\Models\ProfileGroup::find($voucher->profile_group_id) : null);

            if ($group && $group->ip_pool_mode === 'group_only' && $group->ip_pool_name) {
                $radreplyRows[] = ['username' => $username, 'attribute' => 'Framed-Pool', 'op' => ':=', 'value' => $group->ip_pool_name];
            } else {
                $deletePoolFor[] = $username;
            }

            $parentQueue = ($group && $group->parent_queue)
                ? $group->parent_queue
                : ($voucher->hotspotProfile?->parent_queue ?: null);

            if ($parentQueue) {
                $radreplyRows[] = ['username' => $username, 'attribute' => 'Mikrotik-Queue-Parent-Name', 'op' => ':=', 'value' => $parentQueue];
            } else {
                $deleteQueueFor[] = $username;
            }
        }

        foreach (array_chunk($radcheckRows, 500) as $chunk) {
            DB::table('radcheck')->upsert($chunk, ['username', 'attribute'], ['op', 'value']);
        }
        foreach (array_chunk($radreplyRows, 500) as $chunk) {
            DB::table('radreply')->upsert($chunk, ['username', 'attribute'], ['op', 'value']);
        }

        if ($deleteRateFor) {
            DB::table('radreply')->whereIn('username', $deleteRateFor)->where('attribute', 'Mikrotik-Rate-Limit')->delete();
        }
        if ($deletePoolFor) {
            DB::table('radreply')->whereIn('username', $deletePoolFor)->where('attribute', 'Framed-Pool')->delete();
        }
        if ($deleteQueueFor) {
            DB::table('radreply')->whereIn('username', $deleteQueueFor)->where('attribute', 'Mikrotik-Queue-Parent-Name')->delete();
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
