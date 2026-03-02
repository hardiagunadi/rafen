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
            WaWebhookLog::create([
                'event_type' => 'session',
                'session_id' => $payload['session'] ?? $payload['device'] ?? null,
                'status'     => $payload['status'] ?? null,
                'payload'    => $payload,
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
            $sender  = $payload['sender'] ?? $payload['receiver'] ?? null;
            $msgBody = $payload['message'] ?? null;

            // Jika message adalah array/object (bukan plain text), ambil teks-nya
            if (is_array($msgBody)) {
                $msgBody = $msgBody['text'] ?? $msgBody['caption'] ?? json_encode($msgBody);
            }

            WaWebhookLog::create([
                'event_type' => 'message',
                'session_id' => $payload['session'] ?? $payload['device'] ?? null,
                'sender'     => $sender ? preg_replace('/@.*/', '', $sender) : null,
                'message'    => is_string($msgBody) ? mb_substr($msgBody, 0, 1000) : null,
                'payload'    => $payload,
            ]);
        } catch (\Throwable $e) {
            Log::warning('WA Webhook: failed to save message log', ['error' => $e->getMessage()]);
        }

        return response()->json(['status' => true]);
    }
}
