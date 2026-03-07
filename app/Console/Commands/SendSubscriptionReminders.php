<?php

namespace App\Console\Commands;

use App\Models\TenantSettings;
use App\Models\User;
use App\Services\WaGatewayService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendSubscriptionReminders extends Command
{
    protected $signature = 'subscription:send-reminders';

    protected $description = 'Kirim WA reminder perpanjangan subscription ke tenant yang akan habis (7 hari & 1 hari)';

    public function handle(): int
    {
        $today = now()->startOfDay();

        // Kirim reminder untuk 7 hari dan 1 hari sebelum expired
        $reminderDays = [7, 1];

        $sent  = 0;
        $skipped = 0;

        foreach ($reminderDays as $daysLeft) {
            $targetDate = $today->copy()->addDays($daysLeft);

            $tenants = User::query()
                ->where('is_super_admin', false)
                ->whereNull('parent_id')
                ->where('subscription_status', 'active')
                ->whereNotNull('subscription_expires_at')
                ->whereDate('subscription_expires_at', $targetDate)
                ->get();

            foreach ($tenants as $tenant) {
                $result = $this->sendReminder($tenant, $daysLeft);
                $result ? $sent++ : $skipped++;
            }
        }

        $this->info("Subscription reminders: {$sent} sent, {$skipped} skipped.");

        return self::SUCCESS;
    }

    private function sendReminder(User $tenant, int $daysLeft): bool
    {
        $phone = $tenant->phone ?? '';
        if (empty(trim($phone))) {
            return false;
        }

        try {
            $settings = TenantSettings::getOrCreate($tenant->id);
            $service  = WaGatewayService::forTenant($settings);

            if (! $service) {
                return false;
            }

            $expiryDate  = $tenant->subscription_expires_at->format('d/m/Y');
            $planName    = $tenant->subscriptionPlan?->name ?? 'Langganan';
            $renewUrl    = config('app.url') . '/subscription/renew';

            if ($daysLeft <= 1) {
                $message = "⚠️ *Pengingat Langganan*\n\n"
                    . "Yth. *{$tenant->name}*,\n\n"
                    . "Langganan *{$planName}* Anda akan berakhir *BESOK* ({$expiryDate}).\n\n"
                    . "Segera perpanjang agar layanan tidak terganggu:\n{$renewUrl}\n\n"
                    . "Terima kasih.";
            } else {
                $message = "🔔 *Pengingat Langganan*\n\n"
                    . "Yth. *{$tenant->name}*,\n\n"
                    . "Langganan *{$planName}* Anda akan berakhir dalam *{$daysLeft} hari* ({$expiryDate}).\n\n"
                    . "Perpanjang sekarang:\n{$renewUrl}\n\n"
                    . "Terima kasih.";
            }

            $service->sendMessage($phone, $message);

            Log::info('Subscription reminder sent', ['tenant_id' => $tenant->id, 'days_left' => $daysLeft]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Subscription reminder failed', ['tenant_id' => $tenant->id, 'error' => $e->getMessage()]);
            return false;
        }
    }
}
