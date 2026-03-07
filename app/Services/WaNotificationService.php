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
            $customerId = $user->customer_id ?? $user->username ?? '-';
            $harga = $isPpp
                ? ($user->profile ? 'Rp ' . number_format($user->profile->harga ?? 0, 0, ',', '.') : '-')
                : '-';
            $csNumber = $settings->business_phone ?? '-';

            $template = $settings->getTemplate('registration');

            $message = str_replace(
                ['{name}', '{username}', '{service}', '{profile}', '{due_date}', '{customer_id}', '{total}', '{cs_number}'],
                [$user->customer_name, $user->username, $serviceLabel, $profileName, $dueDate, $customerId, $harga, $csNumber],
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

            $customerId  = $invoice->customer_id ?? ($user->customer_id ?? '-');
            $profileName = $invoice->paket_langganan ?? '-';
            $serviceType = $invoice->tipe_service ? strtoupper($invoice->tipe_service) : '-';
            $csNumber    = $settings->business_phone ?? '-';

            // Bank accounts
            $bankAccounts = $user->owner?->bankAccounts()->active()->get()
                ?? \App\Models\BankAccount::where('user_id', $invoice->owner_id)->where('is_active', true)->get();
            $bankLines = $bankAccounts->map(fn($b) => $b->bank_name . ' ' . $b->account_number . ' a/n ' . $b->account_name)->join("\n");

            $message = str_replace(
                ['{name}', '{invoice_no}', '{total}', '{due_date}', '{customer_id}', '{profile}', '{service}', '{cs_number}', '{bank_account}'],
                [
                    $invoice->customer_name,
                    $invoice->invoice_number,
                    'Rp ' . number_format($invoice->total, 0, ',', '.'),
                    $invoice->due_date ? $invoice->due_date->format('d/m/Y') : '-',
                    $customerId,
                    $profileName,
                    $serviceType,
                    $csNumber,
                    $bankLines,
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

            $paidAt      = $invoice->paid_at ? $invoice->paid_at->format('d/m/Y H:i') : now()->format('d/m/Y H:i');
            $customerId  = $invoice->customer_id ?? ($invoice->pppUser->customer_id ?? '-');
            $profileName = $invoice->paket_langganan ?? '-';
            $serviceType = $invoice->tipe_service ? strtoupper($invoice->tipe_service) : '-';
            $csNumber    = $settings->business_phone ?? '-';

            $message = str_replace(
                ['{name}', '{invoice_no}', '{total}', '{paid_at}', '{customer_id}', '{profile}', '{service}', '{cs_number}'],
                [
                    $invoice->customer_name,
                    $invoice->invoice_number,
                    'Rp ' . number_format($invoice->total, 0, ',', '.'),
                    $paidAt,
                    $customerId,
                    $profileName,
                    $serviceType,
                    $csNumber,
                ],
                $template
            );

            $service->sendMessage($phone, $message);
        } catch (\Throwable $e) {
            Log::warning('WA notifyInvoicePaid failed', ['error' => $e->getMessage(), 'invoice_id' => $invoice->id]);
        }
    }
}
