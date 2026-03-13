<?php

namespace App\Services;

use App\Models\TenantSettings;
use App\Models\WaBlastLog;
use App\Models\WaMultiSessionDevice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WaGatewayService
{
    /** Delay in milliseconds between messages (anti-spam) */
    private int $delayMs = 0;

    /** Minimal interval antar pengiriman per tenant untuk antrean lintas proses */
    private int $dispatchIntervalMs = 1200;

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

    private bool $blastMultiDevice = true;

    private bool $blastNaturalVariation = true;

    private int $blastDelayMinMs = 1200;

    private int $blastDelayMaxMs = 2600;

    private ?int $ownerId = null;

    private ?int $sentById = null;

    private ?string $sentByName = null;

    private ?string $sessionId = null;

    public function __construct(
        private string $url,
        private string $token,
        private string $key = ''
    ) {
        $this->minuteStart = microtime(true);
    }

    public static function forTenant(TenantSettings $settings): ?self
    {
        $gatewayUrl = trim((string) config('wa.multi_session.public_url', ''));
        if ($gatewayUrl === '') {
            $gatewayUrl = trim((string) ($settings->wa_gateway_url ?? ''));
        }

        if ($gatewayUrl === '') {
            return null;
        }

        $token = trim((string) config('wa.multi_session.auth_token', ''));
        $key = trim((string) config('wa.multi_session.master_key', ''));

        if ($token === '') {
            $token = trim((string) ($settings->wa_gateway_token ?? ''));
            $key = trim((string) ($settings->wa_gateway_key ?? ''));
        }

        if ($token === '') {
            return null;
        }

        $instance = new self(
            rtrim($gatewayUrl, '/'),
            $token,
            $key
        );

        $configuredDelayMs = max(0, (int) ($settings->wa_antispam_delay_ms ?? 1200));
        $instance->dispatchIntervalMs = max(900, $configuredDelayMs > 0 ? $configuredDelayMs : 1200);

        if ($settings->wa_antispam_enabled) {
            $instance->delayMs = $configuredDelayMs;
            $instance->maxPerMinute = max(0, (int) ($settings->wa_antispam_max_per_minute ?? 20));
        }

        $instance->randomize = (bool) ($settings->wa_msg_randomize ?? true);
        $instance->blastMultiDevice = (bool) ($settings->wa_blast_multi_device ?? true);
        $instance->blastNaturalVariation = (bool) ($settings->wa_blast_message_variation ?? true);
        $instance->blastDelayMinMs = max(300, (int) ($settings->wa_blast_delay_min_ms ?? max(700, $configuredDelayMs)));
        $instance->blastDelayMaxMs = max($instance->blastDelayMinMs, (int) ($settings->wa_blast_delay_max_ms ?? ($instance->blastDelayMinMs + 1200)));
        $instance->ownerId = $settings->user_id ?? null;
        $instance->sessionId = $instance->resolveDefaultTenantSession();

        // Auto-set sent_by dari user yang sedang login (jika ada)
        if ($authUser = auth()->user()) {
            $instance->sentById = $authUser->id;
            $instance->sentByName = $authUser->name;
        }

        return $instance;
    }

    public function setSessionId(?string $sessionId): self
    {
        $trimmed = trim((string) $sessionId);
        $this->sessionId = $trimmed !== '' ? $trimmed : null;

        return $this;
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

        if (! empty($this->sessionId)) {
            $headers['X-Session-Id'] = $this->sessionId;
        }

        return $headers;
    }

    /**
     * Apply anti-spam rate limit before sending a message.
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
    }

    private function applyDispatchQueueDelay(array $context = []): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        if ($this->ownerId === null) {
            return;
        }

        $lockPath = storage_path('framework/cache/wa-dispatch-'.$this->ownerId.'.lock');
        $lockDir = dirname($lockPath);

        if (! is_dir($lockDir)) {
            @mkdir($lockDir, 0775, true);
        }

        $handle = @fopen($lockPath, 'c+');
        if ($handle === false) {
            return;
        }

        try {
            if (! flock($handle, LOCK_EX)) {
                return;
            }

            $nowMs = $this->currentTimeMs();
            rewind($handle);
            $raw = stream_get_contents($handle);
            $nextAllowedMs = is_string($raw) && is_numeric(trim($raw)) ? (int) trim($raw) : 0;

            if ($nextAllowedMs > $nowMs) {
                $waitMs = $nextAllowedMs - $nowMs;
                Log::info('WA queue: waiting dispatch slot', [
                    'owner_id' => $this->ownerId,
                    'wait_ms' => $waitMs,
                    'event' => $context['event'] ?? null,
                ]);
                usleep($waitMs * 1000);
                $nowMs = $this->currentTimeMs();
            }

            $jitterMs = 250;
            try {
                $jitterMs = random_int(120, 520);
            } catch (\Throwable) {
                $jitterMs = 250;
            }

            $reserveUntilMs = $nowMs + $this->resolveDispatchIntervalMs() + $jitterMs;
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, (string) $reserveUntilMs);
            fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function resolveDispatchIntervalMs(): int
    {
        if ($this->dispatchIntervalMs > 0) {
            return $this->dispatchIntervalMs;
        }

        if ($this->delayMs > 0) {
            return max(900, $this->delayMs);
        }

        return 1200;
    }

    private function currentTimeMs(): int
    {
        return (int) floor(microtime(true) * 1000);
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
        // Simpan teks pesan asli (sebelum randomize) ke context agar tercatat di log
        $context['message'] = mb_substr($message, 0, 4000);

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

        $this->applyDispatchQueueDelay($context);
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
                            'session' => $this->resolveSessionId($context),
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
                // 'queued' = gateway v2 menerima dan akan mengirim (sukses)
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

                // Prioritaskan error detail dari pesan individual, fallback ke body message
                $msgError = trim((string) ($msgData['error'] ?? ''));
                $failureReason = $msgError !== ''
                    ? $msgError
                    : (string) ($body['message'] ?? 'Gateway menolak pengiriman.');
                if ($isMessageFailed && $statusValue !== '' && $msgError === '') {
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
                'message' => $context['message'] ?? null,
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
        $sessionPool = $this->resolveBlastSessions();
        $sessionPoolCount = count($sessionPool);

        foreach ($recipients as $index => $recipient) {
            $phone = $recipient['phone'] ?? '';
            $message = $this->applyBlastMessageVariation(
                (string) ($recipient['message'] ?? ''),
                $recipient['name'] ?? null,
                (int) $index
            );
            $context = ['event' => 'blast', 'name' => $recipient['name'] ?? null];

            if (empty($message)) {
                $failed++;
                $results[] = ['phone' => $phone, 'status' => false, 'reason' => 'Pesan kosong'];

                continue;
            }

            $sent = false;
            $usedSession = null;
            for ($attempt = 0; $attempt < max(1, $sessionPoolCount); $attempt++) {
                $sessionIndex = ((int) $index + $attempt) % max(1, $sessionPoolCount);
                $sessionId = $sessionPool[$sessionIndex] ?? $this->resolveSessionId();
                $usedSession = $sessionId;
                $sent = $this->sendMessage($phone, $message, array_merge($context, ['session_id' => $sessionId]));
                if ($sent) {
                    break;
                }
            }

            if ($sent) {
                $success++;
                $results[] = ['phone' => $this->normalizePhone($phone), 'status' => true, 'session' => $usedSession];
            } else {
                $normalized = $this->normalizePhone($phone);
                $reason = empty(trim($phone))
                    ? 'Nomor HP kosong'
                    : (! $this->isValidPhone($normalized) ? 'Format nomor tidak valid sebagai nomor WA' : 'Gagal terkirim');
                $failed++;
                $results[] = ['phone' => $phone, 'status' => false, 'reason' => $reason, 'session' => $usedSession];
            }

            $this->applyBlastInterMessageDelay();
        }

        return [
            'success' => $success,
            'failed' => $failed,
            'results' => $results,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function resolveBlastSessions(): array
    {
        $fallback = [$this->resolveSessionId()];

        if (! $this->blastMultiDevice || $this->ownerId === null) {
            return $fallback;
        }

        $devices = WaMultiSessionDevice::query()
            ->forOwner($this->ownerId)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get(['session_id']);

        if ($devices->count() < 2) {
            return $fallback;
        }

        $connectedSessions = [];
        foreach ($devices as $device) {
            $sessionId = trim((string) ($device->session_id ?? ''));
            if ($sessionId === '') {
                continue;
            }

            $status = $this->sessionStatus($sessionId);
            $connectionStatus = strtolower((string) data_get($status, 'data.status', ''));

            if (($status['status'] ?? false) === true && $connectionStatus === 'connected') {
                $connectedSessions[] = $sessionId;
            }
        }

        if ($connectedSessions === []) {
            return $fallback;
        }

        return array_values(array_unique($connectedSessions));
    }

    private function applyBlastInterMessageDelay(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $minMs = max(0, $this->blastDelayMinMs);
        $maxMs = max($minMs, $this->blastDelayMaxMs);

        try {
            $delayMs = random_int($minMs, $maxMs);
        } catch (\Throwable) {
            $delayMs = $minMs;
        }

        if ($delayMs > 0) {
            usleep($delayMs * 1000);
        }
    }

    private function applyBlastMessageVariation(string $message, ?string $name, int $index): string
    {
        $baseMessage = trim($message);
        if ($baseMessage === '' || ! $this->blastNaturalVariation) {
            return $baseMessage;
        }

        $recipient = trim((string) $name);
        $recipient = $recipient !== '' ? $recipient : 'Bapak/Ibu';

        $openings = [
            'Halo '.$recipient.',',
            'Selamat siang '.$recipient.',',
            'Permisi '.$recipient.',',
        ];
        $closings = [
            'Terima kasih atas perhatian Anda.',
            'Jika ada pertanyaan, silakan balas pesan ini.',
            'Tim kami siap membantu jika diperlukan.',
        ];

        $opening = $openings[$index % count($openings)];
        $closing = $closings[$index % count($closings)];

        return $opening."\n\n".$baseMessage."\n\n".$closing;
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
            '/api/v2/sessions/status?session='.$this->resolveSessionId(),
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

    public function startSession(?string $sessionId = null): array
    {
        return $this->callSessionEndpoint('/api/v2/sessions/start', $sessionId);
    }

    public function stopSession(?string $sessionId = null): array
    {
        return $this->callSessionEndpoint('/api/v2/sessions/stop', $sessionId);
    }

    public function restartSession(?string $sessionId = null): array
    {
        return $this->callSessionEndpoint('/api/v2/sessions/restart', $sessionId);
    }

    public function sessionStatus(?string $sessionId = null): array
    {
        $targetSession = $sessionId ?: $this->resolveSessionId();

        try {
            $response = Http::timeout(10)
                ->withHeaders($this->buildHeaders())
                ->get($this->url.'/api/v2/sessions/status', [
                    'session' => $targetSession,
                ]);

            if ($response->successful()) {
                $body = $response->json();

                return [
                    'status' => true,
                    'message' => 'Status sesi berhasil diambil.',
                    'data' => $body['data'] ?? $body,
                    'http_status' => $response->status(),
                ];
            }

            return [
                'status' => false,
                'message' => 'Gagal membaca status sesi (HTTP '.$response->status().').',
                'data' => $response->json() ?? $response->body(),
                'http_status' => $response->status(),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => false,
                'message' => 'Tidak dapat membaca status sesi: '.$e->getMessage(),
                'http_status' => 0,
                'network_error' => true,
            ];
        }
    }

    private function callSessionEndpoint(string $path, ?string $sessionId = null): array
    {
        $targetSession = $sessionId ?: $this->resolveSessionId();

        try {
            $response = Http::timeout(15)
                ->withHeaders($this->buildHeaders())
                ->post($this->url.$path, [
                    'session' => $targetSession,
                ]);

            if ($response->successful()) {
                $body = $response->json();

                return [
                    'status' => true,
                    'message' => (string) ($body['message'] ?? 'Berhasil.'),
                    'data' => $body['data'] ?? $body,
                    'http_status' => $response->status(),
                ];
            }

            return [
                'status' => false,
                'message' => 'Permintaan gagal (HTTP '.$response->status().').',
                'data' => $response->json() ?? $response->body(),
                'http_status' => $response->status(),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => false,
                'message' => 'Tidak dapat menghubungi gateway sesi: '.$e->getMessage(),
                'http_status' => 0,
                'network_error' => true,
            ];
        }
    }

    private function resolveSessionId(array $context = []): string
    {
        $contextSession = trim((string) ($context['session_id'] ?? ''));

        if ($contextSession !== '') {
            return $contextSession;
        }

        if (! empty($this->sessionId)) {
            return $this->sessionId;
        }

        if ($this->ownerId !== null) {
            return 'tenant-'.$this->ownerId;
        }

        return 'default';
    }

    private function resolveDefaultTenantSession(): ?string
    {
        if ($this->ownerId === null) {
            return null;
        }

        $defaultDevice = WaMultiSessionDevice::query()
            ->forOwner($this->ownerId)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();

        if ($defaultDevice) {
            return $defaultDevice->session_id;
        }

        return 'tenant-'.$this->ownerId;
    }
}
