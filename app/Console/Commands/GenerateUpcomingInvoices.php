<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\PppUser;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateUpcomingInvoices extends Command
{
    protected $signature = 'invoice:generate-upcoming
                            {--days=14 : Jumlah hari sebelum jatuh tempo untuk generate invoice}
                            {--dry-run : Tampilkan tanpa membuat invoice}';

    protected $description = 'Generate invoice untuk user PPP yang jatuh temponya dalam N hari ke depan';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        $now = now();
        $windowEnd = $now->copy()->addDays($days)->endOfDay();

        $this->info("Mencari user dengan jatuh_tempo <= {$windowEnd->toDateString()} (termasuk overdue)...");

        // Ambil semua PPP users yang:
        // - status_bayar = 'belum_bayar'
        // - jatuh_tempo ada dan <= windowEnd (termasuk yang sudah overdue)
        // - belum punya unpaid invoice
        $users = PppUser::query()
            ->where('status_bayar', 'belum_bayar')
            ->whereNotNull('jatuh_tempo')
            ->where('jatuh_tempo', '<=', $windowEnd->toDateString())
            ->with(['profile', 'invoices' => fn ($q) => $q->where('status', 'unpaid')])
            ->get();

        $generated = 0;
        $skipped   = 0;

        foreach ($users as $user) {
            // Sudah ada unpaid invoice → skip
            if ($user->invoices->isNotEmpty()) {
                $skipped++;
                $this->line("  [skip] {$user->username} — sudah ada invoice unpaid.");
                continue;
            }

            if (! $user->profile) {
                $skipped++;
                $this->line("  [skip] {$user->username} — tidak ada profil PPP.");
                continue;
            }

            if ($dryRun) {
                $this->info("  [dry-run] Akan generate invoice untuk: {$user->username} (jatuh_tempo: {$user->jatuh_tempo})");
                $generated++;
                continue;
            }

            $this->createInvoice($user);
            $generated++;
            $this->info("  [OK] Invoice dibuat untuk: {$user->username} (jatuh_tempo: {$user->jatuh_tempo})");
        }

        $this->newLine();
        $this->info("Selesai. Generated: {$generated}, Skipped: {$skipped}.");

        return self::SUCCESS;
    }

    private function createInvoice(PppUser $user): void
    {
        $profile = $user->profile;

        $promoMonths = (int) ($user->durasi_promo_bulan ?? 0);
        $promoActive = $user->promo_aktif
            && $promoMonths > 0
            && $user->created_at
            && $user->created_at->diffInMonths(now()) < $promoMonths;

        $basePrice  = $promoActive ? $profile->harga_promo : $profile->harga_modal;
        $ppnPercent = (float) $profile->ppn;
        $ppnAmount  = round($basePrice * ($ppnPercent / 100), 2);
        $total      = $basePrice + $ppnAmount;
        $dueDate    = Carbon::parse($user->jatuh_tempo)->endOfDay();

        Invoice::create([
            'invoice_number'  => $this->generateInvoiceNumber(),
            'ppp_user_id'     => $user->id,
            'ppp_profile_id'  => $user->ppp_profile_id,
            'owner_id'        => $user->owner_id,
            'customer_id'     => $user->customer_id,
            'customer_name'   => $user->customer_name,
            'tipe_service'    => $user->tipe_service,
            'paket_langganan' => $profile->name,
            'harga_dasar'     => $basePrice,
            'ppn_percent'     => $ppnPercent,
            'ppn_amount'      => $ppnAmount,
            'total'           => $total,
            'promo_applied'   => $promoActive,
            'due_date'        => $dueDate,
            'status'          => 'unpaid',
        ]);
    }

    private function generateInvoiceNumber(): string
    {
        do {
            $number = 'INV-'.str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT);
        } while (Invoice::where('invoice_number', $number)->exists());

        return $number;
    }
}
