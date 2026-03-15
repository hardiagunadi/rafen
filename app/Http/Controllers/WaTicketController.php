<?php

namespace App\Http\Controllers;

use App\Models\Outage;
use App\Models\PppUser;
use App\Models\TenantSettings;
use App\Models\User;
use App\Models\WaConversation;
use App\Models\WaTicket;
use App\Models\WaTicketNote;
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

        // Admin, CS, dan NOC mendapat highlight update belum dibaca dari teknisi
        $canSeeUnread = $user->isSuperAdmin()
            || ($user->isAdmin() && ! $user->isSubUser())
            || in_array($user->role, ['cs', 'noc'], true);

        $query = WaTicket::query()
            ->accessibleBy($user)
            ->with(['conversation:id,contact_phone,contact_name', 'assignedTo:id,name,nickname'])
            ->withCount(['notes as unread_count' => fn ($q) => $q->where('read_by_cs', false)])
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

        $data = $tickets->getCollection()->map(function (WaTicket $ticket) use ($canSeeUnread) {
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
                'has_unread_update' => $canSeeUnread && ($ticket->unread_count > 0),
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
            'image' => ['nullable', 'image', 'max:5120'],
            'type' => ['required', 'string', 'in:complaint,installation,troubleshoot,other'],
            'priority' => ['nullable', 'string', 'in:low,normal,high'],
            'customer_type' => ['nullable', 'string', 'in:ppp,hotspot'],
            'customer_id' => ['nullable', 'integer'],
        ]);

        $conversation = WaConversation::findOrFail($data['conversation_id']);

        if (! $user->isSuperAdmin() && $conversation->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        $ownerId = $user->effectiveOwnerId();

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('tickets', 'public');
        }

        $ticket = WaTicket::create([
            'owner_id' => $ownerId,
            'conversation_id' => $conversation->id,
            'customer_type' => $data['customer_type'] ?? null,
            'customer_id' => $data['customer_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'image_path' => $imagePath,
            'type' => $data['type'],
            'priority' => $data['priority'] ?? 'normal',
            'status' => 'open',
        ]);

        // Catat timeline: tiket dibuat
        $ticket->notes()->create([
            'user_id' => $user->id,
            'type' => 'created',
            'meta' => 'Tiket dibuat oleh '.($user->nickname ?? $user->name),
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

        $waTicket->load([
            'conversation:id,contact_phone,contact_name',
            'assignedTo:id,name,nickname',
            'assignedBy:id,name',
            'notes.user:id,name,nickname,role',
        ]);

        // Admin, CS, dan NOC membuka tiket: tandai notif teknisi sebagai sudah dibaca
        $canMarkRead = $user->isSuperAdmin()
            || ($user->isAdmin() && ! $user->isSubUser())
            || in_array($user->role, ['cs', 'noc'], true);
        if ($canMarkRead) {
            $waTicket->notes()->where('read_by_cs', false)->update(['read_by_cs' => true]);
        }

        // Cek outage aktif di area pelanggan terkait tiket ini
        $relatedOutage = null;
        if ($waTicket->customer_type === 'ppp' && $waTicket->customer_id) {
            $pppUser = PppUser::find($waTicket->customer_id);
            if ($pppUser?->odp_id) {
                $relatedOutage = Outage::where('owner_id', $waTicket->owner_id)
                    ->whereIn('status', [Outage::STATUS_OPEN, Outage::STATUS_IN_PROGRESS])
                    ->whereHas('affectedAreas', fn ($q) => $q->where('odp_id', $pppUser->odp_id))
                    ->latest('started_at')
                    ->first();
            }
        }

        return view('wa-chat.ticket-show', compact('waTicket', 'user', 'relatedOutage'));
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

        $oldStatus = $waTicket->status;
        $wasResolved = $oldStatus !== 'resolved' && ($data['status'] ?? null) === 'resolved';

        if ($wasResolved) {
            $data['resolved_at'] = now();
        }

        $waTicket->update(array_filter($data, fn ($v) => $v !== null));

        // Catat timeline: perubahan status
        if (isset($data['status']) && $data['status'] !== $oldStatus) {
            $waTicket->notes()->create([
                'user_id' => $user->id,
                'type' => 'status_change',
                'meta' => $oldStatus.' → '.$data['status'],
                // Jika teknisi yang ubah status → tandai belum dibaca CS
                'read_by_cs' => $user->role !== 'teknisi',
            ]);
        }

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

    public function addNote(Request $request, WaTicket $waTicket): JsonResponse
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

        $request->validate([
            'note' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'image', 'max:5120'],
        ]);

        if (! $request->filled('note') && ! $request->hasFile('image')) {
            return response()->json(['success' => false, 'message' => 'Isi catatan atau pilih foto.'], 422);
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('ticket-notes', 'public');
        }

        $note = $waTicket->notes()->create([
            'user_id' => $user->id,
            'type' => 'note',
            'note' => $request->input('note'),
            'image_path' => $imagePath,
            // Catatan dari teknisi → belum dibaca CS
            'read_by_cs' => $user->role !== 'teknisi',
        ]);

        $note->load('user:id,name,nickname,role');

        return response()->json([
            'success' => true,
            'note' => $this->formatNote($note),
        ]);
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

        // Catat timeline: assignment
        $waTicket->notes()->create([
            'user_id' => $user->id,
            'type' => 'assigned',
            'meta' => 'Di-assign ke '.($assignee->nickname ?? $assignee->name),
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

    private function formatNote(WaTicketNote $note): array
    {
        return [
            'id' => $note->id,
            'type' => $note->type,
            'note' => $note->note,
            'meta' => $note->meta,
            'image_url' => $note->image_path ? asset('storage/'.$note->image_path) : null,
            'user_name' => $note->user ? ($note->user->nickname ?? $note->user->name) : '-',
            'user_role' => $note->user?->role,
            'created_at' => $note->created_at?->format('d/m/Y H:i'),
        ];
    }
}
