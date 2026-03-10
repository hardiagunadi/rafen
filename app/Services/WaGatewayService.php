<?php

namespace App\Services;

use App\Models\TenantSettings;
use App\Models\WaBlastLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WaGatewayService
{
    /** Delay in milliseconds between messages (anti-spam) */
    private int $delayMs = 0;

    /** Max messages per minute (0 = unlimited) */
    private int $maxPerMinute = 0;

    /** Counter for rate limiting */
    private int $sentThisMinute = 0;

    private float $minuteStart = 0;

    /**
     * Append random invisible characters at end of each message so identical
     * template messages sent to many recipients produce unique hashes, helping
     * bypass WhatsApp's duplicate-content detection.
     *
     * Technique: combinations of Unicode zero-width characters (U+200B, U+200C,
     * U+200D) are invisible to recipients but make the raw string unique.
     */
    private bool $randomize = false;

    private ?int $ownerId = null;

    private ?int $sentById = null;

    private ?string $sentByName = null;

    public function __construct(
        private string $url,
        private string $token,
        private string $key = ''
    ) {
        $this->minuteStart = microtime(true);
    }

    public static function forTenant(TenantSettings $settings): ?self
    {
        $gatewayUrl = trim((string) ($settings->wa_gateway_url ?? ''));
        if ($gatewayUrl === '') {
            return null;
        }

        $token = trim((string) ($settings->wa_gateway_token ?? ''));
        $key = trim((string) ($settings->wa_gateway_key ?? ''));

        if ($token === '') {
            return null;
        }

        $instance = new self(
            rtrim($gatewayUrl, '/'),
            $token,
            $key
        );

        if ($settings->wa_antispam_enabled) {
            $instance->delayMs = max(0, (int) ($settings->wa_antispam_delay_ms ?? 1000));
            $instance->maxPerMinute = max(0, (int) ($settings->wa_antispam_max_per_minute ?? 20));
        }

        $instance->randomize = (bool) ($settings->wa_msg_randomize ?? true);
        $instance->ownerId = $settings->user_id ?? null;

        // Auto-set sent_by dari user yang sedang login (jika ada)
        if ($authUser = auth()->user()) {
            $instance->sentById = $authUser->id;
            $instance->sentByName = $authUser->name;
        }

        return $instance;
    }

    /**
     * Build request headers — Authorization token is required, key is optional.
     */
    private function buildHeaders(): array
    {
        $headers = ['Content-Type' => 'application/json'];

        if (! empty($this->token)) {
            $headers['Authorization'] = $this->token;
        }

        if (! empty($this->key)) {
            $headers['key'] = $this->key;
        }

        return $headers;
    }

    /**
     * Apply anti-spam delay before sending a message.
     */
    private function applyAntiSpamDelay(): void
    {
        // Rate limit: max messages per minute
        if ($this->maxPerMinute > 0) {
            $elapsed = microtime(true) - $this->minuteStart;

            if ($elapsed >= 60) {
                $this->sentThisMinute = 0;
                $this->minuteStart = microtime(true);
            } elseif ($this->sentThisMinute >= $this->maxPerMinute) {
                $waitSeconds = (int) ceil(60 - $elapsed);
                Log::info("WA Anti-Spam: rate limit reached ({$this->maxPerMinute}/min), waiting {$waitSeconds}s");
                sleep($waitSeconds);
                $this->sentThisMinute = 0;
                $this->minuteStart = microtime(true);
            }
        }

        // Fixed delay between messages
        if ($this->delayMs > 0 && $this->sentThisMinute > 0) {
            usleep($this->delayMs * 1000);
        }
    }

    /**
     * Append a unique invisible suffix made of zero-width Unicode characters.
     *
     * Uses U+200B (ZWSP), U+200C (ZWNJ), U+200D (ZWJ) as a base-3 encoding of
     * a random 16-bit value → produces 10 invisible chars that look identical to
     * the user but yield a different byte sequence (and thus a different hash)
     * for every recipient.
     */
    private function appendRandomRef(string $message): string
    {
        $zwChars = ["\u{200B}", "\u{200C}", "\u{200D}"];
        $value = random_int(0, 59048); // 3^10 - 1

        $suffix = '';
        for ($i = 0; $i < 10; $i++) {
            $suffix .= $zwChars[$value % 3];
            $value = (int) ($value / 3);
        }

        return $message.$suffix;
    }

    /**
     * Normalize phone number to Indonesian format (62xxx)
     */
    public function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[\s\-\(\)]/', '', $phone);
        $phone = ltrim($phone, '+');

        if (str_starts_with($phone, '0')) {
            $phone = '62'.substr($phone, 1);
        }

        return $phone;
    }

    /**
     * Validate that a normalized phone number looks like a valid WA number.
     * Rules: starts with 62, total length 10–15 digits, digits only.
     */
    public function isValidPhone(string $normalized): bool
    {
        return (bool) preg_match('/^62\d{8,13}$/', $normalized);
    }

    /**
     * Send a single WhatsApp message.
     */
    public function sendMessage(string $phone, string $message, array $context = []): bool
    {
        if (empty(trim($phone))) {
            Log::info('WA skip: nomor HP kosong', $context);
            $this->writeLog('skip', '', '', $context, 'Nomor HP kosong');

            return false;
        }

        if (trim($this->token) === '') {
            $reason = 'Token perangkat WA belum diisi.';
            Log::warning('WA skip: token perangkat kosong', $context);
            $this->writeLog('failed', $phone, '', $context, $reason);

            return false;
        }

        $normalized = $this->normalizePhone($phone);

        if (! $this->isValidPhone($normalized)) {
            $reason = 'Format nomor tidak valid sebagai nomor WA (harus 62xxxxxxxx, 10-15 digit)';
            Log::info('WA skip: format nomor tidak valid sebagai nomor WA', array_merge($context, [
                'phone_raw' => $phone,
                'phone_normalized' => $normalized,
                'reason' => $reason,
            ]));
            $this->writeLog('skip', $phone, $normalized, $context, $reason);

            return false;
        }

        $phone = $normalized;

        $this->applyAntiSpamDelay();

        if ($this->randomize) {
            $message = $this->appendRandomRef($message);
        }

        $contextRefId = trim((string) ($context['ref_id'] ?? ''));
        $refId = $contextRefId !== ''
            ? $contextRefId
            : ('rafen-'.date('YmdHis').'-'.bin2hex(random_bytes(4)));

        try {
            $response = Http::timeout(15)
                ->withHeaders($this->buildHeaders())
                ->post($this->url.'/api/v2/send-message', [
                    'data' => [
                        [
                            'phone' => $phone,
                            'message' => $message,
                            'isGroup' => false,
                            'ref_id' => $refId,
                        ],
                    ],
                ]);

            $this->sentThisMinute++;

            if ($response->successful()) {
                $body = $response->json();
                $msgData = $body['data']['messages'][0] ?? [];
                $msgRefId = $msgData['ref_id'] ?? $refId;
                $msgStatus = $msgData['status'] ?? null;
                $statusValue = strtolower((string) $msgStatus);
                $isGatewayStatusOk = (bool) ($body['status'] ?? false);
                $isMessageFailed = in_array($statusValue, ['failed', 'error', 'undelivered'], true);

                if ($isGatewayStatusOk && ! $isMessageFailed) {
                    Log::info('WA sent', array_merge($context, [
                        'phone' => $phone,
                        'ref_id' => $msgRefId,
                        'msg_status' => $msgStatus,
                        'note' => 'Gateway hanya konfirmasi pesan diterima server (fire-and-forget). Delivery ke perangkat tidak dapat dikonfirmasi — nomor mungkin tidak terdaftar WA jika pelanggan tidak menerima.',
                    ]));

                    $this->writeLog('sent', $phone, $phone, $context, null, $msgRefId);

                    return true;
                }

                $failureReason = (string) ($body['message'] ?? 'Gateway menolak pengiriman.');
                if ($isMessageFailed && $statusValue !== '') {
                    $failureReason = 'Status gateway: '.$statusValue;
                }

                Log::warning('WA Gateway: send-message rejected', array_merge($context, [
                    'phone' => $phone,
                    'ref_id' => $msgRefId,
                    'msg_status' => $msgStatus,
                    'response' => $body,
                ]));
                $this->writeLog('failed', $phone, $phone, $context, $failureReason, $msgRefId);

                return false;
            }

            $errReason = 'HTTP error dari gateway: '.$response->status();
            Log::warning('WA Gateway: send-message HTTP error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'phone' => $phone,
            ]);
            $this->writeLog('failed', $phone, $phone, $context, $errReason, $refId);

            return false;
        } catch (\Throwable $e) {
            Log::warning('WA Gateway: send-message exception', [
                'error' => $e->getMessage(),
                'phone' => $phone,
            ]);
            $this->writeLog('failed', $phone, $phone, $context, 'Exception: '.$e->getMessage(), $refId);

            return false;
        }
    }

    /**
     * Write a log entry to wa_blast_logs table.
     */
    private function writeLog(string $status, string $phone, string $phoneNormalized, array $context, ?string $reason, ?string $refId = null): void
    {
        try {
            WaBlastLog::create([
                'owner_id' => $this->ownerId,
                'sent_by_id' => $this->sentById,
                'sent_by_name' => $this->sentByName,
                'event' => $context['event'] ?? 'unknown',
                'phone' => $phone ?: null,
                'phone_normalized' => $phoneNormalized ?: null,
                'status' => $status,
                'reason' => $reason,
                'invoice_number' => $context['invoice_number'] ?? null,
                'invoice_id' => $context['invoice_id'] ?? null,
                'user_id' => $context['user_id'] ?? null,
                'username' => $context['username'] ?? null,
                'customer_name' => $context['name'] ?? null,
                'ref_id' => $refId,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('WA: gagal menulis wa_blast_logs', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Send bulk WhatsApp messages with anti-spam delay.
     *
     * @param  array<array{phone: string, message: string}>  $recipients
     * @return array{success: int, failed: int, results: array}
     */
    public function sendBulk(array $recipients): array
    {
        $success = 0;
        $failed = 0;
        $results = [];

        foreach ($recipients as $recipient) {
            $phone = $recipient['phone'] ?? '';
            $message = $recipient['message'] ?? '';
            $context = ['event' => 'blast', 'name' => $recipient['name'] ?? null];

            if (empty($message)) {
                $failed++;
                $results[] = ['phone' => $phone, 'status' => false, 'reason' => 'Pesan kosong'];

                continue;
            }

            $sent = $this->sendMessage($phone, $message, $context);

            if ($sent) {
                $success++;
                $results[] = ['phone' => $this->normalizePhone($phone), 'status' => true];
            } else {
                $normalized = $this->normalizePhone($phone);
                $reason = empty(trim($phone))
                    ? 'Nomor HP kosong'
                    : (! $this->isValidPhone($normalized) ? 'Format nomor tidak valid sebagai nomor WA' : 'Gagal terkirim');
                $failed++;
                $results[] = ['phone' => $phone, 'status' => false, 'reason' => $reason];
            }
        }

        return [
            'success' => $success,
            'failed' => $failed,
            'results' => $results,
        ];
    }

    /**
     * Test connectivity by checking device info.
     * Tries several common endpoints; reports auth errors vs network errors distinctly.
     */
    public function testConnection(): array
    {
        $candidates = [
            '/api/device/info',
            '/api/v2/device/info',
            '/api/devices',
            '/status',
        ];

        $lastError = '';

        foreach ($candidates as $path) {
            try {
                $response = Http::timeout(10)
                    ->withHeaders($this->buildHeaders())
                    ->get($this->url.$path);

                if ($response->successful()) {
                    return [
                        'status' => true,
                        'message' => 'Koneksi berhasil (endpoint: '.$path.')',
                        'http_status' => $response->status(),
                        'data' => $response->json(),
                    ];
                }

                // 401/403 = gateway reachable but token wrong
                if (in_array($response->status(), [401, 403])) {
                    return [
                        'status' => false,
                        'message' => 'Gateway dapat dijangkau tetapi token/key ditolak (HTTP '.$response->status().'). Periksa Token atau Key Anda.',
                        'http_status' => $response->status(),
                        'data' => $response->body(),
                    ];
                }

                if ($response->status() === 404 && str_contains(strtolower($response->body()), 'token not found')) {
                    return [
                        'status' => false,
                        'message' => 'Gateway dapat dijangkau tetapi token perangkat tidak ditemukan. Periksa Token WhatsApp Anda.',
                        'http_status' => $response->status(),
                        'data' => $response->body(),
                    ];
                }

                $lastError = 'HTTP '.$response->status().' pada '.$path;
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                if (str_contains($e->getMessage(), 'Could not resolve') ||
                    str_contains($e->getMessage(), 'Connection refused') ||
                    str_contains($e->getMessage(), 'timed out')) {
                    return [
                        'status' => false,
                        'message' => 'Tidak dapat terhubung ke gateway: '.$e->getMessage(),
                        'http_status' => 0,
                        'network_error' => true,
                    ];
                }
            }
        }

        return [
            'status' => false,
            'message' => 'Gateway tidak merespons pada endpoint yang diketahui. '.$lastError,
            'http_status' => 0,
        ];
    }
}
