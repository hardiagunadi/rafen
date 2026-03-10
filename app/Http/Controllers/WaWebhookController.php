<?php

namespace App\Http\Controllers;

use App\Models\WaWebhookLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WaWebhookController extends Controller
{
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

        Log::info('WA Webhook session event', $payload);

        try {
            $this->persistSessionEvent($payload);
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

        Log::info('WA Webhook status event', $payload);

        try {
            $trackingContext = $payload['message_id'] ?? $payload['tracking_url'] ?? null;

            if (is_array($trackingContext)) {
                $trackingContext = json_encode($trackingContext);
            }

            WaWebhookLog::create([
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

        Log::info('WA Webhook '.$eventType.' event', $payload);

        try {
            WaWebhookLog::create([
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

        return response()->json(['status' => true]);
    }

    protected function persistSessionEvent(array $payload): void
    {
        WaWebhookLog::create([
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
        $senderValue = $payload['sender'] ?? $payload['from'] ?? $payload['phone'] ?? $payload['receiver'] ?? $payload['to'] ?? null;

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
        $messageBody = $payload['message'] ?? $payload['text'] ?? $payload['caption'] ?? null;

        if (is_array($messageBody)) {
            $messageBody = $messageBody['text'] ?? $messageBody['caption'] ?? $messageBody['message'] ?? json_encode($messageBody);
        }

        if ($messageBody === null && isset($payload['data']) && is_array($payload['data'])) {
            $payloadData = $payload['data'];

            if (isset($payloadData[0]) && is_array($payloadData[0])) {
                $firstData = $payloadData[0];
                $messageBody = $firstData['message'] ?? $firstData['text'] ?? $firstData['caption'] ?? null;
            } else {
                $messageBody = $payloadData['message'] ?? $payloadData['text'] ?? $payloadData['caption'] ?? null;
            }
        }

        if (is_array($messageBody)) {
            $messageBody = $messageBody['text'] ?? $messageBody['caption'] ?? $messageBody['message'] ?? json_encode($messageBody);
        }

        if (! is_scalar($messageBody)) {
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
}
