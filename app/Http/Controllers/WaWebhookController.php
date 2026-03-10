<?php

namespace App\Http\Controllers;

use App\Models\WaWebhookLog;
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
    public function session(Request $request)
    {
        $payload = $request->all();

        Log::info('WA Webhook session event', $payload);

        try {
            $sessionId = $payload['session'] ?? $payload['session_id'] ?? $payload['device'] ?? $payload['device_id'] ?? null;
            $status = $payload['status'] ?? $payload['state'] ?? null;

            WaWebhookLog::create([
                'event_type' => 'session',
                'session_id' => is_scalar($sessionId) ? (string) $sessionId : null,
                'status' => is_scalar($status) ? (string) $status : null,
                'payload' => $payload,
            ]);
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
    public function message(Request $request)
    {
        $payload = $request->all();

        Log::info('WA Webhook message event', $payload);

        try {
            $sessionId = $payload['session'] ?? $payload['session_id'] ?? $payload['device'] ?? $payload['device_id'] ?? null;
            $senderValue = $payload['sender'] ?? $payload['from'] ?? $payload['phone'] ?? $payload['receiver'] ?? $payload['to'] ?? null;
            $msgBody = $payload['message'] ?? $payload['text'] ?? $payload['caption'] ?? null;
            $status = $payload['message_status'] ?? $payload['status'] ?? $payload['msg_status'] ?? null;

            if (is_string($senderValue) && str_contains($senderValue, ',')) {
                $senderValue = trim(explode(',', $senderValue, 2)[0]);
            }

            if (is_array($msgBody)) {
                $msgBody = $msgBody['text'] ?? $msgBody['caption'] ?? json_encode($msgBody);
            }

            if ($msgBody === null && isset($payload['data'])) {
                $msgData = $payload['data'];
                if (is_array($msgData) && isset($msgData[0]) && is_array($msgData[0])) {
                    $msgBody = $msgData[0]['message'] ?? $msgData[0]['text'] ?? $msgData[0]['caption'] ?? null;
                }
            }

            if (is_array($msgBody)) {
                $msgBody = $msgBody['text'] ?? $msgBody['caption'] ?? json_encode($msgBody);
            }

            $sender = null;
            if (is_scalar($senderValue)) {
                $senderRaw = preg_replace('/@.*/', '', (string) $senderValue);
                $senderDigits = preg_replace('/\D+/', '', $senderRaw);
                $sender = $senderDigits !== '' ? $senderDigits : $senderRaw;
                if ($sender === '') {
                    $sender = null;
                }
            }

            WaWebhookLog::create([
                'event_type' => 'message',
                'session_id' => is_scalar($sessionId) ? (string) $sessionId : null,
                'sender' => $sender,
                'message' => is_string($msgBody) ? mb_substr($msgBody, 0, 1000) : null,
                'status' => is_scalar($status) ? (string) $status : null,
                'payload' => $payload,
            ]);
        } catch (\Throwable $e) {
            Log::warning('WA Webhook: failed to save message log', ['error' => $e->getMessage()]);
        }

        return response()->json(['status' => true]);
    }
}
