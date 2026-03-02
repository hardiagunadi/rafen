<?php

namespace App\Services;

use App\Models\HotspotUser;
use App\Models\Invoice;
use App\Models\PppUser;
use App\Models\TenantSettings;
use Illuminate\Support\Facades\Log;

class WaNotificationService
{
    /**
     * Notifikasi saat pelanggan PPP atau Hotspot baru didaftarkan.
     */
    public static function notifyRegistration(TenantSettings $settings, PppUser|HotspotUser $user): void
    {
        if (! $settings->wa_notify_registration) {
            return;
        }

        $phone = $user->nomor_hp ?? '';
        if (empty(trim($phone))) {
            return;
        }

        try {
            $service = WaGatewayService::forTenant($settings);
            if (! $service) {
                return;
            }

            $isPpp = $user instanceof PppUser;
            $serviceLabel = $isPpp ? 'PPPoE' : 'Hotspot';
            $profileName = $isPpp
                ? ($user->profile->name ?? '-')
                : ($user->hotspotProfile->name ?? '-');
            $dueDate = $isPpp ? ($user->jatuh_tempo ? \Carbon\Carbon::parse($user->jatuh_tempo)->format('d/m/Y') : '-') : '-';

            $template = $settings->getTemplate('registration');

            $message = str_replace(
                ['{name}', '{username}', '{service}', '{profile}', '{due_date}'],
                [$user->customer_name, $user->username, $serviceLabel, $profileName, $dueDate],
                $template
            );

            $service->sendMessage($phone, $message);
        } catch (\Throwable $e) {
            Log::warning('WA notifyRegistration failed', ['error' => $e->getMessage(), 'user_id' => $user->id]);
        }
    }

    /**
     * Notifikasi saat invoice baru dibuat (tagihan terbit).
     */
    public static function notifyInvoiceCreated(TenantSettings $settings, Invoice $invoice, PppUser $user): void
    {
        if (! $settings->wa_notify_invoice) {
            return;
        }

        $phone = $user->nomor_hp ?? '';
        if (empty(trim($phone))) {
            return;
        }

        try {
            $service = WaGatewayService::forTenant($settings);
            if (! $service) {
                return;
            }

            $template = $settings->getTemplate('invoice');

            $message = str_replace(
                ['{name}', '{invoice_no}', '{total}', '{due_date}'],
                [
                    $invoice->customer_name,
                    $invoice->invoice_number,
                    number_format($invoice->total, 0, ',', '.'),
                    $invoice->due_date ? $invoice->due_date->format('d/m/Y') : '-',
                ],
                $template
            );

            $service->sendMessage($phone, $message);
        } catch (\Throwable $e) {
            Log::warning('WA notifyInvoiceCreated failed', ['error' => $e->getMessage(), 'invoice_id' => $invoice->id]);
        }
    }

    /**
     * Notifikasi saat invoice sudah dibayar / pembayaran dikonfirmasi.
     */
    public static function notifyInvoicePaid(TenantSettings $settings, Invoice $invoice): void
    {
        if (! $settings->wa_notify_payment) {
            return;
        }

        $phone = '';
        if ($invoice->pppUser) {
            $phone = $invoice->pppUser->nomor_hp ?? '';
        }

        if (empty(trim($phone))) {
            return;
        }

        try {
            $service = WaGatewayService::forTenant($settings);
            if (! $service) {
                return;
            }

            $template = $settings->getTemplate('payment');

            $paidAt = $invoice->paid_at
                ? $invoice->paid_at->format('d/m/Y H:i')
                : now()->format('d/m/Y H:i');

            $message = str_replace(
                ['{name}', '{invoice_no}', '{total}', '{paid_at}'],
                [
                    $invoice->customer_name,
                    $invoice->invoice_number,
                    number_format($invoice->total, 0, ',', '.'),
                    $paidAt,
                ],
                $template
            );

            $service->sendMessage($phone, $message);
        } catch (\Throwable $e) {
            Log::warning('WA notifyInvoicePaid failed', ['error' => $e->getMessage(), 'invoice_id' => $invoice->id]);
        }
    }
}
