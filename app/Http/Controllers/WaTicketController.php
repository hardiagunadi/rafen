<?php

namespace App\Http\Controllers;

use App\Models\TenantSettings;
use App\Models\User;
use App\Models\WaConversation;
use App\Models\WaTicket;
use App\Services\WaGatewayService;
use App\Traits\LogsActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WaTicketController extends Controller
{
    use LogsActivity;
    private const CS_ROLES = ['administrator', 'noc', 'it_support', 'cs'];

    public function index(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user->isSuperAdmin() && ! in_array($user->role, self::CS_ROLES, true) && $user->role !== 'teknisi') {
            abort(403);
        }

        return view('wa-chat.tickets');
    }

    public function datatable(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user->isSuperAdmin() && ! in_array($user->role, self::CS_ROLES, true) && $user->role !== 'teknisi') {
            abort(403);
        }

        $query = WaTicket::query()
            ->accessibleBy($user)
            ->with(['conversation:id,contact_phone,contact_name', 'assignedTo:id,name,nickname'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('assigned_to')) {
            $query->where('assigned_to_id', $request->input('assigned_to'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $tickets = $query->paginate(25);

        $data = $tickets->getCollection()->map(function (WaTicket $ticket) {
            return [
                'id' => $ticket->id,
                'title' => $ticket->title,
                'type' => $ticket->type,
                'status' => $ticket->status,
                'priority' => $ticket->priority,
                'contact' => $ticket->conversation
                    ? ($ticket->conversation->contact_name ?? $ticket->conversation->contact_phone)
                    : '-',
                'assigned_to' => $ticket->assignedTo
                    ? ($ticket->assignedTo->nickname ?? $ticket->assignedTo->name)
                    : '-',
                'created_at' => $ticket->created_at?->format('d/m/Y H:i'),
                'actions_url' => route('wa-tickets.show', $ticket),
            ];
        });

        return response()->json([
            'data' => $data,
            'current_page' => $tickets->currentPage(),
            'last_page' => $tickets->lastPage(),
            'total' => $tickets->total(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user->isSuperAdmin() && ! in_array($user->role, self::CS_ROLES, true)) {
            abort(403);
        }

        $data = $request->validate([
            'conversation_id' => ['required', 'integer', 'exists:wa_conversations,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'string', 'in:complaint,installation,troubleshoot,other'],
            'priority' => ['nullable', 'string', 'in:low,normal,high'],
        ]);

        $conversation = WaConversation::findOrFail($data['conversation_id']);

        if (! $user->isSuperAdmin() && $conversation->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        $ownerId = $user->effectiveOwnerId();

        $ticket = WaTicket::create([
            'owner_id' => $ownerId,
            'conversation_id' => $conversation->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
            'priority' => $data['priority'] ?? 'normal',
            'status' => 'open',
        ]);

        // Notify customer via WA (optional)
        try {
            $settings = TenantSettings::where('user_id', $ownerId)->first();
            if ($settings && $settings->hasWaConfigured()) {
                $service = WaGatewayService::forTenant($settings);
                if ($service) {
                    $msg = "Halo, tiket pengaduan Anda telah kami terima.\n\nNo. Tiket: #{$ticket->id}\nJudul: {$ticket->title}\n\nTim kami akan segera menanganinya. Terima kasih.";
                    $service->sendMessage($conversation->contact_phone, $msg, ['event' => 'ticket_created']);
                }
            }
        } catch (\Throwable) {
            // Non-blocking
        }

        $this->logActivity('created', 'WaTicket', $ticket->id, $ticket->title, $ownerId);

        return response()->json(['success' => true, 'ticket_id' => $ticket->id]);
    }

    public function show(WaTicket $waTicket)
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user->isSuperAdmin() && ! in_array($user->role, self::CS_ROLES, true) && $user->role !== 'teknisi') {
            abort(403);
        }

        if (! $user->isSuperAdmin() && $waTicket->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        if ($user->role === 'teknisi' && $waTicket->assigned_to_id !== $user->id) {
            abort(403);
        }

        $waTicket->load(['conversation:id,contact_phone,contact_name', 'assignedTo:id,name,nickname', 'assignedBy:id,name']);

        return view('wa-chat.ticket-show', compact('waTicket'));
    }

    public function update(Request $request, WaTicket $waTicket): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user->isSuperAdmin() && ! in_array($user->role, self::CS_ROLES, true) && $user->role !== 'teknisi') {
            abort(403);
        }

        if (! $user->isSuperAdmin() && $waTicket->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        if ($user->role === 'teknisi' && $waTicket->assigned_to_id !== $user->id) {
            abort(403);
        }

        $data = $request->validate([
            'status' => ['nullable', 'string', 'in:open,in_progress,resolved,closed'],
            'priority' => ['nullable', 'string', 'in:low,normal,high'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $wasResolved = $waTicket->status !== 'resolved' && ($data['status'] ?? null) === 'resolved';

        if ($wasResolved) {
            $data['resolved_at'] = now();
        }

        $waTicket->update(array_filter($data, fn ($v) => $v !== null));

        // Notify customer if resolved
        if ($wasResolved) {
            try {
                $ownerId = $waTicket->owner_id;
                $settings = TenantSettings::where('user_id', $ownerId)->first();
                if ($settings && $settings->hasWaConfigured() && $waTicket->conversation) {
                    $service = WaGatewayService::forTenant($settings);
                    if ($service) {
                        $msg = "Tiket #{$waTicket->id} ({$waTicket->title}) telah diselesaikan.\n\nJika masih ada kendala, silakan hubungi kami kembali. Terima kasih.";
                        $service->sendMessage($waTicket->conversation->contact_phone, $msg, ['event' => 'ticket_resolved']);
                    }
                }
            } catch (\Throwable) {
                // Non-blocking
            }
        }

        return response()->json(['success' => true]);
    }

    public function assign(Request $request, WaTicket $waTicket): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user->isSuperAdmin() && ! in_array($user->role, self::CS_ROLES, true)) {
            abort(403);
        }

        if (! $user->isSuperAdmin() && $waTicket->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        $data = $request->validate([
            'assigned_to_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $assignee = User::findOrFail($data['assigned_to_id']);

        if (! $user->isSuperAdmin() && $assignee->effectiveOwnerId() !== $user->effectiveOwnerId()) {
            return response()->json(['success' => false, 'message' => 'Teknisi bukan anggota tenant ini.'], 422);
        }

        $waTicket->update([
            'assigned_to_id' => $assignee->id,
            'assigned_by_id' => $user->id,
            'status' => $waTicket->status === 'open' ? 'in_progress' : $waTicket->status,
        ]);

        // Notify teknisi via WA
        try {
            $ownerId = $waTicket->owner_id;
            $settings = TenantSettings::where('user_id', $ownerId)->first();
            if ($settings && $settings->hasWaConfigured() && $assignee->phone) {
                $service = WaGatewayService::forTenant($settings);
                if ($service) {
                    $contact = $waTicket->conversation
                        ? ($waTicket->conversation->contact_name ?? $waTicket->conversation->contact_phone)
                        : '-';
                    $msg = "Halo {$assignee->name},\n\nAnda mendapat penugasan tiket baru:\n\nNo. Tiket: #{$waTicket->id}\nJudul: {$waTicket->title}\nPelanggan: {$contact}\nPrioritas: {$waTicket->priority}\n\nSilakan ditindaklanjuti. Terima kasih.";
                    $service->sendMessage($assignee->phone, $msg, ['event' => 'ticket_assigned']);
                }
            }
        } catch (\Throwable) {
            // Non-blocking
        }

        $this->logActivity('assigned', 'WaTicket', $waTicket->id, $waTicket->title, $waTicket->owner_id);

        return response()->json(['success' => true]);
    }

    public function destroy(WaTicket $waTicket): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user->isSuperAdmin() && ! in_array($user->role, self::CS_ROLES, true)) {
            abort(403);
        }

        if (! $user->isSuperAdmin() && $waTicket->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        $this->logActivity('deleted', 'WaTicket', $waTicket->id, $waTicket->title, $waTicket->owner_id);
        $waTicket->delete();

        return response()->json(['success' => true]);
    }
}
