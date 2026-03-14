<?php

namespace App\Http\Controllers;

use App\Models\HotspotUser;
use App\Models\Invoice;
use App\Models\PppUser;
use App\Models\RadiusAccount;
use App\Models\TenantSettings;
use App\Models\WaConversation;
use App\Models\WaChatMessage;
use App\Models\WaMultiSessionDevice;
use App\Models\WaWebhookLog;
use App\Services\WaGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WaWebhookController extends Controller
{
    /**
     * Handle incoming webhook with single endpoint style.
     *
     * Beberapa instalasi gateway hanya menyediakan satu webhook URL (mis. /webhook/wa).
     * Endpoint ini akan auto-detect event lalu meneruskan ke handler yang sesuai.
     */
    public function ingest(Request $request): JsonResponse
    {
        $payload = $request->all();

        if ($payload === []) {
            return response()->json(['status' => true]);
        }

        $this->resolveOwnerIdForRequest($request, $payload);
        $eventType = $this->resolveIncomingEventType($payload);

        return match ($eventType) {
            'session' => $this->session($request),
            'status' => $this->status($request),
            'auto_reply' => $this->autoReply($request),
            default => $this->message($request),
        };
    }

    /**
     * Handle incoming session events (online/offline).
     *
     * Gateway mengirim POST ke {WEBHOOK_BASE_URL}/webhook/session
     * Payload contoh:
     * {
     *   "session": "session-id",
     *   "status": "online|offline",
     *   ...
     * }
     */
    public function session(Request $request): JsonResponse
    {
        $payload = $request->all();
        $ownerId = $this->resolveOwnerIdForRequest($request, $payload);

        Log::info('WA Webhook session event', $payload);

        try {
            $this->persistSessionEvent($payload, $ownerId);
        } catch (\Throwable $e) {
            Log::warning('WA Webhook: failed to save session log', ['error' => $e->getMessage()]);
        }

        return response()->json(['status' => true]);
    }

    /**
     * Handle incoming messages.
     *
     * Gateway mengirim POST ke {WEBHOOK_BASE_URL}/webhook/message
     * Payload standar wa-gateway:
     * {
     *   "session": "session-id",
     *   "sender": "628xxx@s.whatsapp.net",
     *   "message": "teks pesan",
     *   "isGroup": false,
     *   "receiver": "628xxx@s.whatsapp.net",
     *   "ref_id": null,
     *   ...
     * }
     *
     * Payload compat lintasku (jika dikonfigurasi):
     * {
     *   "message": "teks pesan",
     *   "receiver": "628xxx@s.whatsapp.net",
     *   "message_status": "received",
     *   "quota": null,
     *   "session": "session-id",
     *   "sender": "628xxx@s.whatsapp.net",
     *   "isGroup": false
     * }
     */
    public function message(Request $request): JsonResponse
    {
        return $this->handleMessageLikeEvent($request, 'message');
    }

    /**
     * Handle webhook auto-reply event.
     *
     * Gateway mengirim POST ke {WEBHOOK_BASE_URL}/webhook/auto-reply
     * Payload sama dengan endpoint message.
     */
    public function autoReply(Request $request): JsonResponse
    {
        return $this->handleMessageLikeEvent($request, 'auto_reply');
    }

    /**
     * Handle delivery status event.
     *
     * Gateway mengirim POST ke {WEBHOOK_BASE_URL}/webhook/status
     * Payload contoh:
     * {
     *   "session": "session-id",
     *   "message_id": "BAE5F...",
     *   "message_status": "READ",
     *   "tracking_url": "/message/status?...",
     * }
     */
    public function status(Request $request): JsonResponse
    {
        $payload = $request->all();
        $ownerId = $this->resolveOwnerIdForRequest($request, $payload);

        Log::info('WA Webhook status event', $payload);

        try {
            $trackingContext = $payload['message_id'] ?? $payload['tracking_url'] ?? null;

            if (is_array($trackingContext)) {
                $trackingContext = json_encode($trackingContext);
            }

            WaWebhookLog::create([
                'owner_id' => $ownerId,
                'event_type' => 'status',
                'session_id' => $this->extractSessionId($payload),
                'sender' => $this->extractSender($payload),
                'message' => is_scalar($trackingContext) ? mb_substr((string) $trackingContext, 0, 1000) : null,
                'status' => $this->extractStatus($payload),
                'payload' => $payload,
            ]);
        } catch (\Throwable $e) {
            Log::warning('WA Webhook: failed to save status log', ['error' => $e->getMessage()]);
        }

        return response()->json(['status' => true]);
    }

    protected function handleMessageLikeEvent(Request $request, string $eventType): JsonResponse
    {
        $payload = $request->all();
        $ownerId = $this->resolveOwnerIdForRequest($request, $payload);

        Log::info('WA Webhook '.$eventType.' event', $payload);

        try {
            WaWebhookLog::create([
                'owner_id' => $ownerId,
                'event_type' => $eventType,
                'session_id' => $this->extractSessionId($payload),
                'sender' => $this->extractSender($payload),
                'message' => $this->extractMessageBody($payload),
                'status' => $this->extractStatus($payload),
                'payload' => $payload,
            ]);
        } catch (\Throwable $e) {
            Log::warning('WA Webhook: failed to save '.$eventType.' log', ['error' => $e->getMessage()]);
        }

        // Sync incoming message to WA Chat inbox (only for inbound messages, not outbound)
        if ($eventType === 'message' && $ownerId !== null && ! $this->isTruthy($payload['fromMe'] ?? null)) {
            try {
                $this->syncToConversation($payload, $ownerId);
            } catch (\Throwable $e) {
                Log::warning('WA Webhook: failed to sync conversation', ['error' => $e->getMessage()]);
            }
        }

        try {
            $this->sendConversationalReply($payload, $ownerId, $eventType);
        } catch (\Throwable $e) {
            Log::warning('WA Webhook: auto-reply conversation failed', ['error' => $e->getMessage()]);
        }

        return response()->json(['status' => true]);
    }

    protected function persistSessionEvent(array $payload, ?int $ownerId = null): void
    {
        WaWebhookLog::create([
            'owner_id' => $ownerId,
            'event_type' => 'session',
            'session_id' => $this->extractSessionId($payload),
            'status' => is_scalar($payload['status'] ?? null) ? (string) ($payload['status'] ?? null) : null,
            'payload' => $payload,
        ]);
    }

    protected function extractSessionId(array $payload): ?string
    {
        $sessionId = $payload['session'] ?? $payload['session_id'] ?? $payload['device'] ?? $payload['device_id'] ?? null;

        if (! is_scalar($sessionId)) {
            return null;
        }

        $value = trim((string) $sessionId);

        return $value !== '' ? $value : null;
    }

    protected function extractSender(array $payload): ?string
    {
        // Untuk pesan masuk: sender / from lebih akurat dari receiver/to (receiver = nomor bot sendiri)
        $senderValue = $payload['sender'] ?? $payload['from'] ?? $payload['pushName'] ?? $payload['phone'] ?? null;

        // Fallback: cek data nested (format Baileys/WA-Gateway)
        if ($senderValue === null && isset($payload['data']) && is_array($payload['data'])) {
            $data = is_array($payload['data'][0] ?? null) ? $payload['data'][0] : $payload['data'];
            $senderValue = $data['sender'] ?? $data['from'] ?? $data['phone'] ?? null;
        }

        // Fallback ke receiver/to hanya jika benar-benar tidak ada sender
        if ($senderValue === null) {
            $senderValue = $payload['receiver'] ?? $payload['to'] ?? null;
        }

        if (! is_scalar($senderValue)) {
            return null;
        }

        $senderRaw = trim((string) $senderValue);

        if ($senderRaw === '') {
            return null;
        }

        if (str_contains($senderRaw, ',')) {
            $senderRaw = trim(explode(',', $senderRaw, 2)[0]);
        }

        $senderRaw = preg_replace('/@.*/', '', $senderRaw) ?? $senderRaw;
        $senderDigits = preg_replace('/\D+/', '', $senderRaw) ?? '';
        $sender = $senderDigits !== '' ? $senderDigits : $senderRaw;

        return $sender !== '' ? $sender : null;
    }

    protected function extractMessageBody(array $payload): ?string
    {
        // Format langsung: body / text / caption / message (string)
        foreach (['body', 'text', 'caption'] as $key) {
            if (isset($payload[$key]) && is_scalar($payload[$key]) && (string) $payload[$key] !== '') {
                return mb_substr((string) $payload[$key], 0, 1000);
            }
        }

        $messageBody = $payload['message'] ?? null;

        // Format Baileys: message bisa berupa objek { conversation, extendedTextMessage, imageMessage, ... }
        if (is_array($messageBody)) {
            $ext = is_array($messageBody['extendedTextMessage'] ?? null) ? $messageBody['extendedTextMessage'] : [];
            $img = is_array($messageBody['imageMessage'] ?? null) ? $messageBody['imageMessage'] : [];
            $vid = is_array($messageBody['videoMessage'] ?? null) ? $messageBody['videoMessage'] : [];
            $doc = is_array($messageBody['documentMessage'] ?? null) ? $messageBody['documentMessage'] : [];

            $messageBody = (isset($messageBody['conversation']) && is_scalar($messageBody['conversation']) ? $messageBody['conversation'] : null)
                ?? ($ext['text'] ?? null)
                ?? ($img['caption'] ?? null)
                ?? ($vid['caption'] ?? null)
                ?? ($doc['caption'] ?? null)
                ?? (isset($messageBody['text']) && is_scalar($messageBody['text']) ? $messageBody['text'] : null)
                ?? (isset($messageBody['caption']) && is_scalar($messageBody['caption']) ? $messageBody['caption'] : null)
                ?? (isset($messageBody['message']) && is_scalar($messageBody['message']) ? $messageBody['message'] : null)
                ?? null;

            if (is_array($messageBody)) {
                $messageBody = json_encode($messageBody);
            }
        }

        // Format nested via data[]
        if ($messageBody === null && isset($payload['data']) && is_array($payload['data'])) {
            $data = is_array($payload['data'][0] ?? null) ? $payload['data'][0] : $payload['data'];

            foreach (['body', 'text', 'caption'] as $key) {
                if (isset($data[$key]) && is_scalar($data[$key]) && (string) $data[$key] !== '') {
                    return mb_substr((string) $data[$key], 0, 1000);
                }
            }

            $nestedMsg = $data['message'] ?? null;
            if (is_array($nestedMsg)) {
                $ext = is_array($nestedMsg['extendedTextMessage'] ?? null) ? $nestedMsg['extendedTextMessage'] : [];
                $img = is_array($nestedMsg['imageMessage'] ?? null) ? $nestedMsg['imageMessage'] : [];
                $vid = is_array($nestedMsg['videoMessage'] ?? null) ? $nestedMsg['videoMessage'] : [];
                $nestedMsg = (isset($nestedMsg['conversation']) && is_scalar($nestedMsg['conversation']) ? $nestedMsg['conversation'] : null)
                    ?? ($ext['text'] ?? null)
                    ?? ($img['caption'] ?? null)
                    ?? ($vid['caption'] ?? null)
                    ?? (isset($nestedMsg['text']) && is_scalar($nestedMsg['text']) ? $nestedMsg['text'] : null)
                    ?? (isset($nestedMsg['caption']) && is_scalar($nestedMsg['caption']) ? $nestedMsg['caption'] : null)
                    ?? null;
            }
            if (is_scalar($nestedMsg) && (string) $nestedMsg !== '') {
                $messageBody = $nestedMsg;
            }
        }

        if (! is_scalar($messageBody) || (string) $messageBody === '') {
            return null;
        }

        return mb_substr((string) $messageBody, 0, 1000);
    }

    protected function extractStatus(array $payload): ?string
    {
        $status = $payload['message_status'] ?? $payload['status'] ?? $payload['msg_status'] ?? $payload['state'] ?? null;

        if (! is_scalar($status)) {
            return null;
        }

        $value = trim((string) $status);

        return $value !== '' ? $value : null;
    }

    protected function sendConversationalReply(array $payload, ?int $ownerId, string $eventType): void
    {
        if ($eventType !== 'message' || $ownerId === null) {
            return;
        }

        if ($this->isTruthy($payload['fromMe'] ?? null)) {
            return;
        }

        if ($this->isGroupMessage($payload)) {
            return;
        }

        $incomingMessage = trim((string) ($this->extractMessageBody($payload) ?? ''));
        if ($incomingMessage === '') {
            return;
        }

        $sender = $this->extractSender($payload);
        if ($sender === null) {
            return;
        }

        if (! $this->acquireAutoReplyCooldown($ownerId, $sender, $incomingMessage)) {
            return;
        }

        $settings = TenantSettings::query()->where('user_id', $ownerId)->first();
        if (! $settings || ! $settings->hasWaConfigured()) {
            return;
        }

        $service = WaGatewayService::forTenant($settings);
        if (! $service) {
            return;
        }

        $customerContext = $this->resolveCustomerContext($ownerId, $sender);
        $intent = $this->detectIntent($incomingMessage);
        $replyMessage = $this->buildConversationalReply($intent, $settings, $customerContext);

        if ($replyMessage === '') {
            return;
        }

        $service->sendMessage($sender, $replyMessage, [
            'event' => 'auto_reply_outbound',
            'name' => $customerContext['name'] ?? null,
            'customer_id' => $customerContext['customer_id'] ?? null,
            'username' => $customerContext['username'] ?? null,
            'intent' => $intent,
        ]);
    }

    protected function buildConversationalReply(string $intent, TenantSettings $settings, array $customerContext): string
    {
        $businessName = trim((string) ($settings->business_name ?? ''));
        $businessLabel = $businessName !== '' ? $businessName : 'layanan kami';
        $csNumber = trim((string) ($settings->business_phone ?? ''));
        $customerName = trim((string) ($customerContext['name'] ?? ''));
        $displayName = $customerName !== '' ? $customerName : 'Bapak/Ibu';

        if ($intent === 'check_invoice') {
            $invoice = $customerContext['invoice'] ?? null;

            if ($invoice instanceof Invoice) {
                if (empty($invoice->payment_token)) {
                    $invoice->update(['payment_token' => Invoice::generatePaymentToken()]);
                }

                $paymentLink = route('customer.invoice', $invoice->payment_token);
                $dueDate = $invoice->due_date ? $invoice->due_date->format('d/m/Y') : '-';
                $total = 'Rp '.number_format((float) $invoice->total, 0, ',', '.');

                return $this->pickReplyVariant([
                    "Baik {$displayName}, saya bantu cek ya.\nSaat ini masih ada tagihan aktif:\n- No Invoice: {$invoice->invoice_number}\n- Total: {$total}\n- Jatuh Tempo: {$dueDate}\n\nBisa dibayar lewat link berikut:\n{$paymentLink}\n\nKalau sudah bayar, tinggal balas: sudah bayar.",
                    "Siap {$displayName}, ini detail tagihan Anda:\n- Invoice: {$invoice->invoice_number}\n- Nominal: {$total}\n- Jatuh Tempo: {$dueDate}\n\nPembayaran online:\n{$paymentLink}\n\nSetelah transfer, kirim konfirmasi di chat ini ya.",
                ]);
            }

            if (($customerContext['found'] ?? false) === true) {
                return $this->pickReplyVariant([
                    "Terima kasih {$displayName}, untuk saat ini tidak ada tagihan yang belum dibayar.",
                    "Baik {$displayName}, saya cek data Anda. Saat ini tidak ada tagihan aktif.",
                ]);
            }

            return 'Baik, saya bantu cek tagihan. Mohon kirim ID pelanggan Anda dulu supaya data tidak tertukar.';
        }

        if ($intent === 'confirm_payment') {
            $customerId = trim((string) ($customerContext['customer_id'] ?? ''));
            $customerHint = $customerId !== '' ? "ID {$customerId}" : 'ID pelanggan Anda';

            $base = "Terima kasih infonya {$displayName}.\nAgar cepat tervalidasi, mohon kirim foto bukti transfer beserta {$customerHint}.";

            if ($csNumber !== '') {
                $base .= "\nTim kami juga standby di {$csNumber}.";
            }

            return $base;
        }

        if ($intent === 'payment_format') {
            $customerId = trim((string) ($customerContext['customer_id'] ?? ''));
            $invoice = $customerContext['invoice'] ?? null;
            $invoiceLabel = $invoice instanceof Invoice ? $invoice->invoice_number : '-';
            $total = $invoice instanceof Invoice ? 'Rp '.number_format((float) $invoice->total, 0, ',', '.') : '-';
            $customerIdLine = $customerId !== '' ? $customerId : '(isi ID pelanggan)';

            return "Siap {$displayName}, berikut format konfirmasi pembayaran yang paling cepat diproses:\n\n"
                ."ID Pelanggan: {$customerIdLine}\n"
                ."No Invoice: {$invoiceLabel}\n"
                ."Nominal Transfer: {$total}\n"
                ."Tanggal/Jam Transfer: (contoh 11/03/2026 14:20)\n"
                ."Nama Pengirim Rekening: (isi nama)\n"
                ."Lampiran: foto/screenshot bukti transfer\n\n"
                .'Silakan kirim dalam satu chat agar tim kami bisa verifikasi lebih cepat.';
        }

        if ($intent === 'network_status') {
            if (($customerContext['found'] ?? false) !== true) {
                return "Baik, untuk cek status gangguan saya butuh verifikasi data dulu.\nMohon kirim ID pelanggan Anda ya.";
            }

            $accountStatus = strtolower(trim((string) ($customerContext['account_status'] ?? '')));
            $hasUnpaidInvoice = $customerContext['invoice'] instanceof Invoice;
            $isSessionOnline = (bool) ($customerContext['session_online'] ?? false);
            $uptime = trim((string) ($customerContext['session_uptime'] ?? ''));

            if ($accountStatus === 'isolir') {
                if ($hasUnpaidInvoice) {
                    $invoice = $customerContext['invoice'];
                    $dueDate = $invoice->due_date ? $invoice->due_date->format('d/m/Y') : '-';

                    return "Status akun {$displayName} saat ini *ISOLIR*.\n"
                        ."Masih ada tagihan invoice {$invoice->invoice_number} (jatuh tempo {$dueDate}).\n"
                        .'Setelah pembayaran dikonfirmasi, layanan akan kami aktifkan kembali.';
                }

                return "Status akun {$displayName} saat ini *ISOLIR*.\nMohon hubungi CS agar kami bantu pengecekan penyebab isolirnya.";
            }

            if ($isSessionOnline) {
                $uptimeText = $uptime !== '' ? " (uptime {$uptime})" : '';

                return "Saya cek ya, koneksi {$displayName} terpantau *ONLINE*{$uptimeText}.\n"
                    .'Kalau masih terasa lemot/putus-putus, coba restart router/ONT 1-2 menit. Jika belum normal, balas *bantuan* agar kami lanjutkan pengecekan.';
            }

            return "Saya cek, saat ini sesi internet {$displayName} terpantau *OFFLINE*.\n"
                ."Silakan cek listrik perangkat, kabel ke ONT/router, dan lampu indikator LOS.\n"
                .'Jika masih merah/putus, balas *jadwal teknisi* agar kami bantu penjadwalan kunjungan.';
        }

        if ($intent === 'technician_schedule') {
            $customerId = trim((string) ($customerContext['customer_id'] ?? ''));
            $customerIdLine = $customerId !== '' ? $customerId : '(isi ID pelanggan)';

            $message = "Siap {$displayName}, kami bantu jadwalkan teknisi.\n"
                ."Mohon balas dengan format berikut:\n"
                ."ID: {$customerIdLine}\n"
                ."Alamat lengkap: (isi lokasi)\n"
                ."Keluhan: (contoh LOS merah / internet putus)\n"
                ."Waktu tersedia: (contoh 13:00 - 16:00)\n\n"
                .'Setelah itu tim kami konfirmasi estimasi kunjungan.';

            if ($csNumber !== '') {
                $message .= "\nKontak cepat: {$csNumber}";
            }

            return $message;
        }

        if ($intent === 'support') {
            $base = "Siap {$displayName}, kami bantu ya.\nMohon kirim detail kendalanya (contoh: internet putus, LOS merah, atau lemot) beserta lokasi singkat agar tim teknis cepat cek.";

            if ($csNumber !== '') {
                $base .= "\nJika mendesak, bisa langsung hubungi {$csNumber}.";
            }

            return $base;
        }

        if ($intent === 'greeting') {
            $timeGreeting = $this->timeGreetingLabel();

            return $this->pickReplyVariant([
                "Selamat {$timeGreeting} {$displayName}, terima kasih sudah menghubungi {$businessLabel}.\nKalau ingin cek tagihan, balas: cek tagihan.\nKalau ada kendala internet, balas: bantuan.",
                "Halo {$displayName}, terima kasih sudah chat {$businessLabel}.\nSaya siap bantu. Anda bisa ketik:\n- cek tagihan\n- status gangguan\n- jadwal teknisi\n- format bayar",
            ]);
        }

        return "Pesan Anda sudah kami terima.\nSupaya lebih cepat, Anda bisa ketik salah satu:\n- cek tagihan\n- sudah bayar\n- status gangguan\n- jadwal teknisi\n- format bayar";
    }

    protected function detectIntent(string $message): string
    {
        $normalized = mb_strtolower($message);

        if (preg_match('/\b(cek tagihan|tagihan|invoice|tunggakan|berapa tagihan|bayar berapa)\b/u', $normalized)) {
            return 'check_invoice';
        }

        if (preg_match('/\b(format bayar|format konfirmasi|format bukti|cara bayar|cara konfirmasi|kirim bukti|template konfirmasi|upload bukti)\b/u', $normalized)) {
            return 'payment_format';
        }

        if (preg_match('/\b(sudah bayar|sudah transfer|udah transfer|baru transfer|sudah tf|konfirmasi pembayaran|lunas)\b/u', $normalized)) {
            return 'confirm_payment';
        }

        if (preg_match('/\b(status gangguan|status internet|status jaringan|cek koneksi|cek internet|internet saya|los|offline|online)\b/u', $normalized)) {
            return 'network_status';
        }

        if (preg_match('/\b(jadwal teknisi|teknisi datang|minta teknisi|kunjungan teknisi|jadwal kunjungan)\b/u', $normalized)) {
            return 'technician_schedule';
        }

        if (preg_match('/\b(gangguan|internet putus|lemot|lambat|error|komplain|bantuan|tolong|cs)\b/u', $normalized)) {
            return 'support';
        }

        if (preg_match('/\b(halo|hai|hi|assalamualaikum|pagi|siang|sore|malam)\b/u', $normalized)) {
            return 'greeting';
        }

        return 'fallback';
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveCustomerContext(int $ownerId, string $sender): array
    {
        $phones = $this->resolvePhoneCandidates($sender);

        $pppUser = PppUser::query()
            ->where('owner_id', $ownerId)
            ->whereIn('nomor_hp', $phones)
            ->orderByDesc('id')
            ->first();

        if ($pppUser) {
            $session = $this->findActiveRadiusSession($pppUser->username);

            return [
                'found' => true,
                'type' => 'ppp',
                'id' => $pppUser->id,
                'name' => $pppUser->customer_name,
                'customer_id' => $pppUser->customer_id,
                'username' => $pppUser->username,
                'account_status' => $pppUser->status_akun,
                'session_online' => $session !== null,
                'session_uptime' => $session?->uptime,
                'invoice' => $this->findUnpaidInvoiceForCustomer($ownerId, $pppUser->customer_id, $pppUser->id),
            ];
        }

        $hotspotUser = HotspotUser::query()
            ->where('owner_id', $ownerId)
            ->whereIn('nomor_hp', $phones)
            ->orderByDesc('id')
            ->first();

        if ($hotspotUser) {
            $session = $this->findActiveRadiusSession($hotspotUser->username);

            return [
                'found' => true,
                'type' => 'hotspot',
                'id' => $hotspotUser->id,
                'name' => $hotspotUser->customer_name,
                'customer_id' => $hotspotUser->customer_id,
                'username' => $hotspotUser->username,
                'account_status' => $hotspotUser->status_akun,
                'session_online' => $session !== null,
                'session_uptime' => $session?->uptime,
                'invoice' => $this->findUnpaidInvoiceForCustomer($ownerId, $hotspotUser->customer_id, null),
            ];
        }

        return [
            'found' => false,
            'type' => null,
            'id' => null,
            'name' => null,
            'customer_id' => null,
            'username' => null,
            'account_status' => null,
            'session_online' => false,
            'session_uptime' => null,
            'invoice' => null,
        ];
    }

    protected function findActiveRadiusSession(?string $username): ?RadiusAccount
    {
        $username = trim((string) $username);
        if ($username === '') {
            return null;
        }

        return RadiusAccount::query()
            ->where('username', $username)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first();
    }

    protected function findUnpaidInvoiceForCustomer(int $ownerId, ?string $customerId, ?int $pppUserId): ?Invoice
    {
        $query = Invoice::query()
            ->where('owner_id', $ownerId)
            ->where('status', 'unpaid');

        $customerId = trim((string) $customerId);
        if ($customerId !== '') {
            $query->where('customer_id', $customerId);
        } elseif ($pppUserId !== null) {
            $query->where('ppp_user_id', $pppUserId);
        } else {
            return null;
        }

        return $query
            ->orderBy('due_date')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array<int, string>
     */
    protected function resolvePhoneCandidates(string $phone): array
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return [];
        }

        $candidates = [$digits];

        if (str_starts_with($digits, '62')) {
            $candidates[] = '0'.substr($digits, 2);
        }

        if (str_starts_with($digits, '0')) {
            $candidates[] = '62'.substr($digits, 1);
        }

        return array_values(array_unique(array_filter($candidates, static fn (string $candidate): bool => $candidate !== '')));
    }

    protected function acquireAutoReplyCooldown(int $ownerId, string $sender, string $message): bool
    {
        $key = "wa:auto-reply:cooldown:{$ownerId}:{$sender}";
        $messageHash = sha1(mb_strtolower(trim($message)));
        $payload = Cache::get($key);
        $nowTs = now()->timestamp;

        if (is_array($payload)) {
            $lastHash = (string) ($payload['hash'] ?? '');
            $lastTs = (int) ($payload['at'] ?? 0);

            if ($lastHash === $messageHash && ($nowTs - $lastTs) < 25) {
                return false;
            }
        }

        Cache::put($key, ['hash' => $messageHash, 'at' => $nowTs], now()->addMinutes(2));

        return true;
    }

    protected function isGroupMessage(array $payload): bool
    {
        if ($this->isTruthy($payload['isGroup'] ?? null)) {
            return true;
        }

        $sender = strtolower((string) ($payload['sender'] ?? $payload['from'] ?? ''));

        return str_contains($sender, '@g.us');
    }

    protected function isTruthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }

    protected function timeGreetingLabel(): string
    {
        $hour = (int) now()->format('H');

        return match (true) {
            $hour < 11 => 'pagi',
            $hour < 15 => 'siang',
            $hour < 18 => 'sore',
            default => 'malam',
        };
    }

    /**
     * @param  array<int, string>  $variants
     */
    protected function pickReplyVariant(array $variants): string
    {
        if ($variants === []) {
            return '';
        }

        try {
            $index = random_int(0, count($variants) - 1);
        } catch (\Throwable) {
            $index = 0;
        }

        return $variants[$index] ?? $variants[0];
    }

    protected function resolveIncomingEventType(array $payload): string
    {
        $explicitEvent = strtolower(trim((string) ($payload['event'] ?? '')));

        if (in_array($explicitEvent, ['session', 'status', 'message'], true)) {
            return $explicitEvent;
        }

        if (in_array($explicitEvent, ['auto_reply', 'auto-reply'], true)) {
            return 'auto_reply';
        }

        $type = strtolower(trim((string) ($payload['type'] ?? '')));
        if ($type === 'session') {
            return 'session';
        }

        if (in_array($type, ['status', 'delivery_status'], true)) {
            return 'status';
        }

        $hasMessageBody = $this->extractMessageBody($payload) !== null;
        $statusValue = strtolower((string) ($this->extractStatus($payload) ?? ''));

        if (in_array($statusValue, ['online', 'offline'], true) && ! $hasMessageBody) {
            return 'session';
        }

        if ((isset($payload['tracking_url']) || isset($payload['message_id'])) && ! $hasMessageBody) {
            return 'status';
        }

        // fromMe=true berarti pesan keluar dari bot sendiri (bukan pesan masuk dari pelanggan)
        // Klasifikasikan sebagai auto_reply hanya jika ada indikasi eksplisit (event atau flag),
        // agar tidak mencemari tab WA Masuk dengan pesan keluar bot
        if ($this->isTruthy($payload['fromMe'] ?? null)) {
            return 'auto_reply';
        }

        return 'message';
    }

    protected function resolveOwnerIdForRequest(Request $request, array $payload): ?int
    {
        if ($request->attributes->has('wa_webhook_owner_id_resolved')) {
            $resolvedOwnerId = $request->attributes->get('wa_webhook_owner_id');

            return is_int($resolvedOwnerId) ? $resolvedOwnerId : null;
        }

        $resolvedOwnerId = $this->resolveOwnerId($request, $payload);

        $request->attributes->set('wa_webhook_owner_id_resolved', true);
        $request->attributes->set('wa_webhook_owner_id', $resolvedOwnerId);

        return $resolvedOwnerId;
    }

    protected function resolveOwnerId(Request $request, array $payload): ?int
    {
        $tenantHint = $this->extractTenantHint($request, $payload);
        $incomingSecret = $this->extractWebhookSecret($request, $payload);

        if ($tenantHint !== null) {
            $settings = TenantSettings::query()->where('user_id', $tenantHint)->first();
            if (! $settings) {
                Log::warning('WA Webhook: tenant hint tidak ditemukan', ['tenant' => $tenantHint]);

                return null;
            }

            $configuredSecret = trim((string) ($settings->wa_webhook_secret ?? ''));

            if ($configuredSecret !== '') {
                if ($incomingSecret === '' || ! hash_equals($configuredSecret, $incomingSecret)) {
                    Log::warning('WA Webhook: secret tidak cocok untuk tenant', ['tenant' => $tenantHint]);

                    return null;
                }
            }

            return (int) $settings->user_id;
        }

        if ($incomingSecret !== '') {
            $matchedOwnerIds = TenantSettings::query()
                ->where('wa_webhook_secret', $incomingSecret)
                ->limit(2)
                ->pluck('user_id');

            if ($matchedOwnerIds->count() === 1) {
                return (int) $matchedOwnerIds->first();
            }

            if ($matchedOwnerIds->count() > 1) {
                Log::warning('WA Webhook: secret terduplikasi antar tenant');
            }
        }

        // Fallback: coba resolve tenant via session dari payload.
        // Gateway kadang mengirim session berupa nomor HP (628xxx/085xxx) atau custom session_id.
        // Normalkan dan coba cocokkan ke kolom session_id DAN wa_number.
        $rawSession = is_scalar($payload['session'] ?? null) ? trim((string) ($payload['session'] ?? '')) : '';
        if ($rawSession !== '') {
            $candidates = [$rawSession];
            if (str_starts_with($rawSession, '62')) {
                $candidates[] = '0'.substr($rawSession, 2);
            } elseif (str_starts_with($rawSession, '0')) {
                $candidates[] = '62'.substr($rawSession, 1);
            }

            // Coba cocokkan ke session_id dulu, kemudian ke wa_number
            // Prioritaskan is_default=true jika ada beberapa device dengan wa_number sama
            $device = WaMultiSessionDevice::query()
                ->where(function ($q) use ($candidates) {
                    $q->whereIn('session_id', $candidates)
                      ->orWhereIn('wa_number', $candidates);
                })
                ->orderByDesc('is_default')
                ->first();

            if ($device) {
                return (int) $device->user_id;
            }
        }

        return null;
    }

    protected function extractTenantHint(Request $request, array $payload): ?int
    {
        $tenantHint = $request->route('tenant')
            ?? $request->query('tenant')
            ?? $request->query('tenant_id')
            ?? $request->header('X-Tenant-Id')
            ?? $payload['tenant']
            ?? $payload['tenant_id']
            ?? null;

        if (is_int($tenantHint)) {
            return $tenantHint > 0 ? $tenantHint : null;
        }

        if (is_string($tenantHint) && ctype_digit($tenantHint)) {
            $tenantId = (int) $tenantHint;

            return $tenantId > 0 ? $tenantId : null;
        }

        return null;
    }

    protected function extractWebhookSecret(Request $request, array $payload): string
    {
        $secret = $request->route('secret')
            ?? $request->query('secret')
            ?? $request->query('webhook_secret')
            ?? $request->header('X-Webhook-Secret')
            ?? $payload['secret']
            ?? $payload['webhook_secret']
            ?? '';

        return is_scalar($secret) ? trim((string) $secret) : '';
    }

    private function syncToConversation(array $payload, int $ownerId): void
    {
        if ($this->isGroupMessage($payload)) {
            return;
        }

        $phone = $this->extractSender($payload);
        if ($phone === null || $phone === '') {
            return;
        }

        // Reject Baileys LID format (numeric string >15 digits that doesn't start with 62 or 0)
        // LID examples: 54778012909755 (14+ digits, not a real phone number)
        if (preg_match('/^\d{14,}$/', $phone) && ! str_starts_with($phone, '62') && ! str_starts_with($phone, '0')) {
            Log::info('WA Webhook: skip LID-format sender', ['phone' => $phone]);

            return;
        }

        $messageText = $this->extractMessageBody($payload);

        // Extract media info (image/video/document/audio sent via base64 in payload)
        $mediaType = null;
        $mediaPath = null;
        $mediaMime = null;
        $mediaFilename = null;

        $mediaRaw = $payload['media'] ?? null;
        if (is_array($mediaRaw)) {
            $mediaType = is_scalar($mediaRaw['type'] ?? null) ? (string) $mediaRaw['type'] : null;
            $mediaBase64 = is_string($mediaRaw['data'] ?? null) ? $mediaRaw['data'] : null;
            $mediaMime = is_scalar($mediaRaw['mimetype'] ?? null) ? (string) $mediaRaw['mimetype'] : null;
            $mediaFilename = is_scalar($mediaRaw['filename'] ?? null) ? mb_substr((string) $mediaRaw['filename'], 0, 255) : null;

            if ($mediaType && $mediaBase64) {
                $ext = match ($mediaType) {
                    'image' => 'jpg',
                    'video' => 'mp4',
                    'audio' => 'ogg',
                    'document' => pathinfo($mediaFilename ?? '', PATHINFO_EXTENSION) ?: 'bin',
                    default => 'bin',
                };
                $filename = 'wa-media/'.uniqid('msg_', true).'.'.$ext;
                $fullPath = storage_path('app/public/'.$filename);
                file_put_contents($fullPath, base64_decode($mediaBase64));
                $mediaPath = $filename;
            }
        }

        // Need at least text or media to store the message
        if (($messageText === null || $messageText === '') && $mediaPath === null) {
            return;
        }

        $sessionId = $this->extractSessionId($payload);
        $contactName = null;
        if (isset($payload['pushName']) && is_scalar($payload['pushName'])) {
            $contactName = mb_substr(trim((string) $payload['pushName']), 0, 191) ?: null;
        }

        $conversation = WaConversation::firstOrCreate(
            ['owner_id' => $ownerId, 'contact_phone' => $phone],
            ['contact_name' => $contactName, 'session_id' => $sessionId, 'status' => 'open']
        );

        // Update contact name if we have one and it was unknown
        if ($contactName && ! $conversation->contact_name) {
            $conversation->update(['contact_name' => $contactName]);
        }

        $waMessageId = null;
        if (isset($payload['id']) && is_scalar($payload['id'])) {
            $waMessageId = mb_substr((string) $payload['id'], 0, 255);
        } elseif (isset($payload['message_id']) && is_scalar($payload['message_id'])) {
            $waMessageId = mb_substr((string) $payload['message_id'], 0, 255);
        }

        WaChatMessage::create([
            'conversation_id' => $conversation->id,
            'owner_id' => $ownerId,
            'direction' => 'inbound',
            'message' => $messageText,
            'media_type' => $mediaType,
            'media_path' => $mediaPath,
            'media_mime' => $mediaMime,
            'media_filename' => $mediaFilename,
            'sender_name' => $contactName,
            'wa_message_id' => $waMessageId,
            'created_at' => now(),
        ]);

        $conversation->updateFromIncoming($messageText ?? ($mediaType ? "[$mediaType]" : '[media]'));
    }
}
