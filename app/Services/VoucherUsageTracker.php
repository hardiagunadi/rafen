<?php

namespace App\Services;

use App\Models\Voucher;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class VoucherUsageTracker
{
    /**
     * Cek radacct untuk voucher unused yang sudah punya sesi aktif (acctstoptime IS NULL).
     * Set status=used, used_at dari acctstarttime, expired_at dari masa_aktif profil.
     * Returns jumlah voucher yang diupdate.
     */
    public function markUsedFromRadacct(): int
    {
        $activeUsernames = DB::table('radacct')
            ->whereNull('acctstoptime')
            ->distinct()
            ->pluck('username')
            ->filter()
            ->values()
            ->all();

        if (empty($activeUsernames)) {
            return 0;
        }

        $unusedVouchers = Voucher::query()
            ->whereIn('username', $activeUsernames)
            ->where('status', 'unused')
            ->with('hotspotProfile')
            ->get();

        if ($unusedVouchers->isEmpty()) {
            return 0;
        }

        // Ambil acctstarttime per username sekaligus (1 query)
        $startTimes = DB::table('radacct')
            ->whereIn('username', $unusedVouchers->pluck('username')->all())
            ->whereNull('acctstoptime')
            ->orderBy('acctstarttime')
            ->get(['username', 'acctstarttime'])
            ->keyBy('username');

        $now = Carbon::now();

        foreach ($unusedVouchers as $voucher) {
            $usedAt    = isset($startTimes[$voucher->username])
                ? Carbon::parse($startTimes[$voucher->username]->acctstarttime)
                : $now;

            $expiredAt = $voucher->hotspotProfile?->computeExpiredAt($usedAt);

            $voucher->update([
                'status'     => 'used',
                'used_at'    => $usedAt,
                'expired_at' => $expiredAt,
            ]);
        }

        return $unusedVouchers->count();
    }
}
