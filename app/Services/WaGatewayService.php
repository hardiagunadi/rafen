<?php

namespace App\Services;

use App\Models\TenantSettings;
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

    public function __construct(
        private string $url,
        private string $token,
        private string $key = ''
    ) {
        $this->minuteStart = microtime(true);
    }

    public static function forTenant(TenantSettings $settings): ?self
    {
        if (empty($settings->wa_gateway_url)) {
            return null;
        }

        $token = $settings->wa_gateway_token ?? '';
        $key   = $settings->wa_gateway_key ?? '';

        if (empty($token) && empty($key)) {
            return null;
        }

        $instance = new self(
            rtrim($settings->wa_gateway_url, '/'),
            $token,
            $key
        );

        if ($settings->wa_antispam_enabled) {
            $instance->delayMs      = max(0, (int) ($settings->wa_antispam_delay_ms ?? 1000));
            $instance->maxPerMinute = max(0, (int) ($settings->wa_antispam_max_per_minute ?? 20));
        }

        $instance->randomize = (bool) ($settings->wa_msg_randomize ?? true);

        return $instance;
    }

    /**
     * Build request headers — send Authorization (token) and/or KEY header.
     */
    private function buildHeaders(): array
    {
        $headers = ['Content-Type' => 'application/json'];

        if (! empty($this->token)) {
            $headers['Authorization'] = $this->token;
        }

        if (! empty($this->key)) {
            $headers['KEY'] = $this->key;
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
                $this->minuteStart    = microtime(true);
            } elseif ($this->sentThisMinute >= $this->maxPerMinute) {
                $waitSeconds = (int) ceil(60 - $elapsed);
                Log::info("WA Anti-Spam: rate limit reached ({$this->maxPerMinute}/min), waiting {$waitSeconds}s");
                sleep($waitSeconds);
                $this->sentThisMinute = 0;
                $this->minuteStart    = microtime(true);
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
        $value   = random_int(0, 59048); // 3^10 - 1

        $suffix = '';
        for ($i = 0; $i < 10; $i++) {
            $suffix .= $zwChars[$value % 3];
            $value  = (int) ($value / 3);
        }

        return $message . $suffix;
    }

    /**
     * Normalize phone number to Indonesian format (62xxx)
     */
    public function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[\s\-\(\)]/', '', $phone);
        $phone = ltrim($phone, '+');

        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        }

        return $phone;
    }

    /**
     * Send a single WhatsApp message.
     */
    public function sendMessage(string $phone, string $message): bool
    {
        $phone = $this->normalizePhone($phone);

        if (empty($phone)) {
            return false;
        }

        $this->applyAntiSpamDelay();

        if ($this->randomize) {
            $message = $this->appendRandomRef($message);
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders($this->buildHeaders())
                ->post($this->url . '/api/v2/send-message', [
                    'data' => [
                        [
                            'phone'   => $phone,
                            'message' => $message,
                            'isGroup' => false,
                        ],
                    ],
                ]);

            $this->sentThisMinute++;

            if ($response->successful()) {
                $body = $response->json();
                return $body['status'] ?? false;
            }

            Log::warning('WA Gateway: send-message HTTP error', [
                'status' => $response->status(),
                'body'   => $response->body(),
                'phone'  => $phone,
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::warning('WA Gateway: send-message exception', [
                'error' => $e->getMessage(),
                'phone' => $phone,
            ]);

            return false;
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
        $failed  = 0;
        $results = [];

        foreach ($recipients as $recipient) {
            $phone   = $recipient['phone'] ?? '';
            $message = $recipient['message'] ?? '';

            if (empty($phone) || empty($message)) {
                $failed++;
                $results[] = ['phone' => $phone, 'status' => false, 'reason' => 'Empty phone or message'];
                continue;
            }

            $sent = $this->sendMessage($phone, $message);

            if ($sent) {
                $success++;
                $results[] = ['phone' => $this->normalizePhone($phone), 'status' => true];
            } else {
                $failed++;
                $results[] = ['phone' => $this->normalizePhone($phone), 'status' => false, 'reason' => 'Send failed'];
            }
        }

        return [
            'success' => $success,
            'failed'  => $failed,
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
                    ->get($this->url . $path);

                if ($response->successful()) {
                    return [
                        'status'      => true,
                        'message'     => 'Koneksi berhasil (endpoint: ' . $path . ')',
                        'http_status' => $response->status(),
                        'data'        => $response->json(),
                    ];
                }

                // 401/403 = gateway reachable but token wrong
                if (in_array($response->status(), [401, 403])) {
                    return [
                        'status'      => false,
                        'message'     => 'Gateway dapat dijangkau tetapi token/key ditolak (HTTP ' . $response->status() . '). Periksa Token atau Key Anda.',
                        'http_status' => $response->status(),
                        'data'        => $response->body(),
                    ];
                }

                $lastError = 'HTTP ' . $response->status() . ' pada ' . $path;
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                if (str_contains($e->getMessage(), 'Could not resolve') ||
                    str_contains($e->getMessage(), 'Connection refused') ||
                    str_contains($e->getMessage(), 'timed out')) {
                    return [
                        'status'        => false,
                        'message'       => 'Tidak dapat terhubung ke gateway: ' . $e->getMessage(),
                        'http_status'   => 0,
                        'network_error' => true,
                    ];
                }
            }
        }

        return [
            'status'      => false,
            'message'     => 'Gateway tidak merespons pada endpoint yang diketahui. ' . $lastError,
            'http_status' => 0,
        ];
    }
}
