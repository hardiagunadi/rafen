<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePppUserRequest;
use App\Http\Requests\UpdatePppUserRequest;
use App\Models\Invoice;
use App\Models\PppProfile;
use App\Models\PppUser;
use App\Models\ProfileGroup;
use App\Models\TenantSettings;
use App\Models\User;
use App\Services\IsolirSynchronizer;
use App\Services\MikrotikApiClient;
use App\Services\RadiusReplySynchronizer;
use App\Services\WaNotificationService;
use App\Traits\LogsActivity;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PppUserController extends Controller
{
    use LogsActivity;
    public function generateCustomerId(Request $request): JsonResponse
    {
        $ownerId = $request->input('owner_id') ? (int) $request->input('owner_id') : $request->user()->effectiveOwnerId();
        return response()->json(['customer_id' => PppUser::generateCustomerId($ownerId)]);
    }

    public function datatable(Request $request): JsonResponse
    {
        $currentUser = $request->user();
        $draw   = (int) $request->input('draw', 1);
        $start  = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        $search       = $request->input('search.value', '');
        $filterIsolir = $request->input('filter_isolir', '');
        $filterTagihan = $request->input('filter_tagihan', '');
        $filterOnProcess = $request->input('filter_on_process', '');

        $query = PppUser::query()
            ->with(['owner', 'profile', 'invoices' => fn ($q) => $q->where('status', 'unpaid')->latest()->limit(1)])
            ->accessibleBy($currentUser);

        if ($filterIsolir === '1') {
            $query->where('status_akun', 'isolir');
        }

        if ($filterTagihan === '1') {
            $query->whereHas('invoices', fn ($q) => $q->overdue());
        }

        if ($filterOnProcess === '1') {
            $query->where('status_registrasi', 'on_process');
        }

        $total = (clone $query)->count();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_id', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            });
        }

        $filtered = (clone $query)->count();

        $users = $query->latest()->skip($start)->take($length > 0 ? $length : 10)->get();

        // Fetch active sessions for this batch of users in one query
        $usernames = $users->pluck('username')->filter()->values()->all();
        $activeSessions = \App\Models\RadiusAccount::whereIn('username', $usernames)
            ->where('is_active', true)
            ->get()
            ->keyBy('username');

        $data = $users->map(function (PppUser $user) use ($activeSessions) {
            $invoice = $user->invoices->first();
            $session = $activeSessions->get($user->username);
            $canRenew = $invoice && $invoice->created_at->equalTo($invoice->updated_at);
            $canPay   = (bool) $invoice;

            $statusBadge = '';
            if ($user->status_registrasi) {
                $statusBadge = '<span class="badge badge-success mr-1">'.strtoupper(substr($user->status_registrasi, 0, 3)).'</span>';
            }
            $tipe = $statusBadge.strtoupper(str_replace('_', '/', (string) $user->tipe_service));

            $ip = $user->tipe_ip === 'static' ? ($user->ip_static ?? '-') : 'Automatic';

            $updated  = $user->updated_at?->format('Y-m-d H:i') ?? '-';
            $due      = $user->jatuh_tempo
                ? '<a href="#" class="text-primary font-weight-bold">'.$user->jatuh_tempo->format('Y-m-d H:i').'</a>'
                : '<span class="text-muted">-</span>';

            $renewBtn = $invoice
                ? '<button class="btn btn-primary" data-ajax-post="'.route('invoices.renew', $invoice).'" data-confirm="Perpanjang layanan tanpa pembayaran?" '.($canRenew ? '' : 'disabled').'><i class="fas fa-bolt"></i> Renew</button>'
                : '<button class="btn btn-light" disabled><i class="fas fa-bolt"></i> Renew</button>';

            $bayarBtn = $invoice
                ? '<button class="btn btn-success" data-ajax-post="'.route('invoices.pay', $invoice).'" data-confirm="Bayar dan perpanjang layanan sekarang?" '.($canPay ? '' : 'disabled').'><i class="fas fa-check"></i> Bayar</button>'
                : '<button class="btn btn-light" disabled><i class="fas fa-check"></i> Bayar</button>';

            $invoiceMenuItem = $invoice
                ? '<button class="dropdown-item text-danger" data-ajax-delete="'.route('invoices.destroy', $invoice).'" data-confirm="Hapus tagihan ini?"><i class="fas fa-file-invoice mr-1"></i>Hapus Tagihan</button>'
                : '<span class="dropdown-item text-muted"><i class="fas fa-file-invoice mr-1"></i>Hapus Tagihan</span>';

            $aksi = '<div class="btn-group btn-group-sm">'.
                '<a href="'.route('ppp-users.edit', $user).'" class="btn btn-warning text-white" title="Edit"><i class="fas fa-pen"></i></a>'.
                '<button type="button" class="btn btn-warning dropdown-toggle dropdown-toggle-split text-white" data-toggle="dropdown"></button>'.
                '<div class="dropdown-menu dropdown-menu-right">'.
                    $invoiceMenuItem.
                    '<div class="dropdown-divider"></div>'.
                    '<button class="dropdown-item text-danger" data-ajax-delete="'.route('ppp-users.destroy', $user).'" data-confirm="Hapus user PPP ini?"><i class="fas fa-user-times mr-1"></i>Hapus User</button>'.
                '</div>'.
                '</div>';

            return [
                'checkbox'    => '<input type="checkbox" name="ids[]" value="'.$user->id.'">',
                'customer_id' => '<a href="#" class="toggle-status-btn badge badge-'.($user->status_akun === 'enable' ? 'success' : 'danger').'" data-toggle-url="'.route('ppp-users.toggle-status', $user).'" title="Klik untuk '.($user->status_akun === 'enable' ? 'disable' : 'enable').'">'.($user->customer_id ?? '-').'</a>',
                'nama'        => (function () use ($user, $session) {
                    if ($session) {
                        $tooltipText = 'CONNECTED | '.$session->caller_id.' | Online: '.$session->uptime;
                    } else {
                        $tooltipText = 'DISCONNECTED';
                    }
                    $tooltipText .= ' — Klik untuk edit';
                    $iconColor = $session ? 'text-success' : 'text-secondary';
                    return '<a href="'.route('ppp-users.edit', $user).'" class="font-weight-bold text-uppercase text-dark">'.$user->customer_name.'</a>'
                        .' <i class="fas fa-info-circle '.$iconColor.'" data-toggle="tooltip" data-placement="top" title="'.e($tooltipText).'"></i>';
                })(),
                'tipe'        => $tipe,
                'paket'       => $user->profile?->name ?? '-',
                'ip'          => $ip,
                'diperpanjang'=> $updated,
                'jatuh_tempo' => $due,
                'owner'       => $user->owner?->email ?? $user->owner?->name ?? '-',
                'renew_print' => '<div class="btn-group btn-group-sm">'.$renewBtn.$bayarBtn.'</div>',
                'aksi'        => '<div class="text-right">'.$aksi.'</div>',
            ];
        });

        return response()->json([
            'draw'            => $draw,
            'recordsTotal'    => $total,
            'recordsFiltered' => $filtered,
            'data'            => $data,
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $perPage = (int) $request->input('per_page', 10);
        $search = $request->input('search');
        $currentUser = $request->user();

        $query = PppUser::query()->with(['owner', 'profileGroup', 'profile', 'invoices' => function ($q) {
            $q->latest();
        }]);

        // Apply tenant data isolation
        $query->accessibleBy($currentUser);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_id', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            });
        }

        $users = $query->latest()->paginate($perPage > 0 ? $perPage : 10)->withQueryString();
        $users->getCollection()->each(function (PppUser $user): void {
            $this->ensureInvoiceWindow($user);
            $this->enforceOverdueAction($user);
        });

        $now = now();
        $stats = [
            'registrasi_bulan_ini' => PppUser::query()->accessibleBy($currentUser)->whereMonth('created_at', $now->month)->whereYear('created_at', $now->year)->count(),
            'renewal_bulan_ini' => PppUser::query()->accessibleBy($currentUser)->whereMonth('updated_at', $now->month)->whereYear('updated_at', $now->year)->count(),
            'pelanggan_isolir' => PppUser::query()->accessibleBy($currentUser)->where('status_akun', 'isolir')->count(),
            'akun_disable' => PppUser::query()->accessibleBy($currentUser)->where('status_akun', 'disable')->count(),
        ];

        return view('ppp_users.index', compact('users', 'stats', 'perPage', 'search'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $currentUser = auth()->user();

        return view('ppp_users.create', [
            'owners'   => $currentUser->isSuperAdmin() ? User::query()->orderBy('name')->get() : collect([$currentUser]),
            'groups'   => ProfileGroup::query()->orderBy('name')->get(),
            'profiles' => PppProfile::query()->accessibleBy($currentUser)->orderBy('name')->get(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePppUserRequest $request): RedirectResponse
    {
        $data = $this->prepareData($request->validated());

        $user = PppUser::create($data);

        if ($data['status_registrasi'] === 'on_process') {
            // ON PROCESS: buat invoice (jika belum bayar) tapi tahan WA registrasi
            $invoice = null;
            if ($data['status_bayar'] === 'belum_bayar') {
                $invoice = $this->createInvoiceForUser($user, null, false, true);
            }
            $settings = TenantSettings::getOrCreate((int) $user->owner_id);
            WaNotificationService::notifyOnProcess($settings, $user->load('profile'), $invoice);
        } else {
            if ($data['status_bayar'] === 'belum_bayar') {
                $this->createInvoiceForUser($user, null, false, true);
            }
            $settings = TenantSettings::getOrCreate((int) $user->owner_id);
            WaNotificationService::notifyRegistration($settings, $user->load('profile'));
        }

        app(RadiusReplySynchronizer::class)->syncSingleUser($user);

        if ($user->status_akun === 'isolir') {
            app(IsolirSynchronizer::class)->isolate($user);
        }

        $this->logActivity('created', 'PppUser', $user->id, $user->customer_name, (int) $user->owner_id);

        return redirect()->route('ppp-users.index')->with('status', 'User PPP ditambahkan.');
    }

    /**
     * Display the specified resource.
     */
    public function show(PppUser $pppUser): RedirectResponse
    {
        return redirect()->route('ppp-users.edit', $pppUser);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PppUser $pppUser): View
    {
        $currentUser = auth()->user();

        if (! $currentUser->isSuperAdmin() && $pppUser->owner_id !== $currentUser->effectiveOwnerId()) {
            abort(403);
        }

        return view('ppp_users.edit', [
            'pppUser'  => $pppUser,
            'owners'   => $currentUser->isSuperAdmin() ? User::query()->orderBy('name')->get() : collect([$currentUser]),
            'groups'   => ProfileGroup::query()->orderBy('name')->get(),
            'profiles' => PppProfile::query()->accessibleBy($currentUser)->orderBy('name')->get(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePppUserRequest $request, PppUser $pppUser): RedirectResponse
    {
        $originalStatus = $pppUser->status_bayar;
        $originalStatus = $pppUser->status_bayar;
        $originalStatusAkun = $pppUser->status_akun;
        $originalStatusRegistrasi = $pppUser->status_registrasi;
        $originalDue = $pppUser->jatuh_tempo;
        $data = $this->prepareData($request->validated(), $pppUser);

        $pppUser->update($data);

        // ON PROCESS → AKTIF: trigger invoice + WA registrasi
        if ($originalStatusRegistrasi === 'on_process' && $pppUser->status_registrasi === 'aktif') {
            if ($pppUser->status_bayar === 'belum_bayar' && ! $pppUser->invoices()->exists()) {
                $this->createInvoiceForUser($pppUser, null, false, true);
            }
            $settings = TenantSettings::getOrCreate((int) $pppUser->owner_id);
            WaNotificationService::notifyRegistration($settings, $pppUser->load('profile'));
        }

        if ($data['status_bayar'] === 'belum_bayar' && $originalStatus !== 'belum_bayar') {
            $this->createInvoiceForUser($pppUser);
        }

        if ($data['status_bayar'] === 'belum_bayar' && $originalDue !== $pppUser->jatuh_tempo) {
            $this->createInvoiceForUser($pppUser, $pppUser->jatuh_tempo ? Carbon::parse($pppUser->jatuh_tempo)->endOfDay() : null, true);
        }

        if ($data['status_bayar'] === 'sudah_bayar' && $originalStatus !== 'sudah_bayar') {
            $this->markInvoicePaid($pppUser);
        }

        app(RadiusReplySynchronizer::class)->syncSingleUser($pppUser);

        if ($pppUser->status_akun === 'isolir' && $originalStatusAkun !== 'isolir') {
            // Baru masuk isolir: setup radreply isolir + kick sesi aktif
            app(IsolirSynchronizer::class)->isolate($pppUser);
        } elseif ($pppUser->status_akun !== 'isolir' && $originalStatusAkun === 'isolir') {
            // Keluar dari isolir: kick sesi isolir agar reconnect normal
            app(IsolirSynchronizer::class)->deisolate($pppUser);
        }

        $this->logActivity('updated', 'PppUser', $pppUser->id, $pppUser->customer_name, (int) $pppUser->owner_id);

        return redirect()->route('ppp-users.index')->with('status', 'User PPP diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PppUser $pppUser): JsonResponse|RedirectResponse
    {
        $currentUser = auth()->user();

        if ($currentUser->role === 'teknisi') {
            abort(403);
        }

        if (! $currentUser->isSuperAdmin() && $pppUser->owner_id !== $currentUser->effectiveOwnerId()) {
            abort(403);
        }

        $this->logActivity('deleted', 'PppUser', $pppUser->id, $pppUser->customer_name, (int) $pppUser->owner_id);
        $pppUser->delete();

        if (request()->wantsJson()) {
            return response()->json(['status' => 'User PPP dihapus.']);
        }

        return redirect()->route('ppp-users.index')->with('status', 'User PPP dihapus.');
    }

    public function toggleStatus(PppUser $pppUser): JsonResponse
    {
        $currentUser = auth()->user();

        if (! $currentUser->isSuperAdmin() && $pppUser->owner_id !== $currentUser->effectiveOwnerId()) {
            abort(403);
        }

        $newStatus = $pppUser->status_akun === 'enable' ? 'disable' : 'enable';
        $pppUser->update(['status_akun' => $newStatus]);

        app(RadiusReplySynchronizer::class)->syncSingleUser($pppUser);

        return response()->json(['status' => $newStatus]);
    }

    public function invoiceDatatable(Request $request, PppUser $pppUser): JsonResponse
    {
        $currentUser = $request->user();

        if (! $currentUser->isSuperAdmin() && $pppUser->owner_id !== $currentUser->effectiveOwnerId()) {
            abort(403);
        }

        $draw   = (int) $request->input('draw', 1);
        $start  = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        $search = $request->input('search.value', '');

        $query = Invoice::query()->where('ppp_user_id', $pppUser->id)->with('owner');

        $total = (clone $query)->count();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('paket_langganan', 'like', "%{$search}%");
            });
        }

        $filtered = (clone $query)->count();

        $orderCol = (int) ($request->input('order.0.column', 0));
        $orderDir = $request->input('order.0.dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $cols = ['id', 'invoice_number', 'paket_langganan', 'total', 'created_at', 'due_date'];
        $query->orderBy($cols[$orderCol] ?? 'id', $orderDir);

        $invoices = $query->skip($start)->take($length > 0 ? $length : 10)->get();

        $data = $invoices->map(function (Invoice $invoice) {
            $statusBadge = $invoice->status === 'paid'
                ? '<span class="badge badge-success">Lunas</span>'
                : '<span class="badge badge-warning">Belum Bayar</span>';

            $aksi = '<div class="btn-group btn-group-sm">'
                .'<a href="'.route('invoices.show', $invoice).'" class="btn btn-info btn-sm" title="Detail"><i class="fas fa-eye"></i></a>'
                .'<a href="'.route('invoices.nota', $invoice).'" target="_blank" class="btn btn-secondary btn-sm" title="Nota"><i class="fas fa-print"></i></a>'
                .'</div>';

            return [
                'id'             => $invoice->id,
                'invoice_number' => $invoice->invoice_number.' '.$statusBadge,
                'paket_langganan'=> $invoice->paket_langganan ?? '-',
                'total'          => 'Rp '.number_format((float) $invoice->total, 0, ',', '.'),
                'created_at'     => $invoice->created_at?->format('M d, Y') ?? '-',
                'due_date'       => $invoice->due_date?->format('M d, Y') ?? '-',
                'owner'          => $invoice->owner?->name ?? '-',
                'aksi'           => $aksi,
            ];
        });

        return response()->json([
            'draw'            => $draw,
            'recordsTotal'    => $total,
            'recordsFiltered' => $filtered,
            'data'            => $data,
        ]);
    }

    public function dialupDatatable(Request $request, PppUser $pppUser): JsonResponse
    {
        $currentUser = $request->user();

        if (! $currentUser->isSuperAdmin() && $pppUser->owner_id !== $currentUser->effectiveOwnerId()) {
            abort(403);
        }

        $draw   = (int) $request->input('draw', 1);
        $start  = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        $search = $request->input('search.value', '');

        $query = DB::table('radacct')
            ->where('username', $pppUser->username)
            ->orderBy('radacctid', 'desc')
            ->limit(100);

        $total = DB::table('radacct')->where('username', $pppUser->username)->count();
        $total = min($total, 100);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('nasipaddress', 'like', "%{$search}%")
                    ->orWhere('acctterminatecause', 'like', "%{$search}%")
                    ->orWhere('callingstationid', 'like', "%{$search}%");
            });
        }

        $filtered = (clone $query)->count();

        $rows = $query->skip($start)->take($length > 0 ? $length : 10)->get();

        $data = $rows->map(function ($row) {
            $uploadBytes = (int) ($row->acctinputoctets ?? 0);
            $downloadBytes = (int) ($row->acctoutputoctets ?? 0);

            $formatBytes = function (int $bytes): string {
                if ($bytes >= 1073741824) return round($bytes / 1073741824, 2).' GB';
                if ($bytes >= 1048576) return round($bytes / 1048576, 2).' MB';
                if ($bytes >= 1024) return round($bytes / 1024, 2).' KB';
                return $bytes.' B';
            };

            $upSecs = (int) ($row->acctsessiontime ?? 0);
            $uptime = sprintf('%dh %dm %ds', intdiv($upSecs, 3600), intdiv($upSecs % 3600, 60), $upSecs % 60);

            return [
                'radacctid' => $row->radacctid,
                'uptime'    => $uptime,
                'start'     => $row->acctstarttime ? \Carbon\Carbon::parse($row->acctstarttime)->format('M/d/Y H:i') : '-',
                'stop'      => $row->acctstoptime  ? \Carbon\Carbon::parse($row->acctstoptime)->format('M/d/Y H:i')  : '-',
                'nas'       => $row->calledstationid ?: $row->nasipaddress,
                'upload'    => '<i class="fas fa-upload text-success mr-1"></i>'.$formatBytes($uploadBytes),
                'download'  => '<i class="fas fa-download text-info mr-1"></i>'.$formatBytes($downloadBytes),
                'terminate' => '<em>'.($row->acctterminatecause ?: '-').'</em>',
            ];
        });

        return response()->json([
            'draw'            => $draw,
            'recordsTotal'    => $total,
            'recordsFiltered' => $filtered,
            'data'            => $data,
        ]);
    }

    public function addInvoice(PppUser $pppUser): JsonResponse
    {
        $currentUser = auth()->user();

        if (! $currentUser->isSuperAdmin() && $pppUser->owner_id !== $currentUser->effectiveOwnerId()) {
            abort(403);
        }

        $this->createInvoiceForUser($pppUser, null, true);

        return response()->json(['status' => 'Tagihan berhasil ditambahkan.']);
    }

    public function disconnect(PppUser $pppUser): JsonResponse
    {
        $currentUser = auth()->user();

        if (! $currentUser->isSuperAdmin() && $pppUser->owner_id !== $currentUser->effectiveOwnerId()) {
            abort(403);
        }

        try {
            $pppUser->load('owner');
            $connections = \App\Models\MikrotikConnection::query()
                ->accessibleBy($currentUser)
                ->get();

            foreach ($connections as $conn) {
                try {
                    $client = app(MikrotikApiClient::class, ['connection' => $conn]);
                    $client->connect();
                    $active = $client->command('/ppp/active/print', ['?name' => $pppUser->username]);
                    foreach ($active['data'] ?? [] as $session) {
                        if (isset($session['.id'])) {
                            $client->command('/ppp/active/remove', ['=.id' => $session['.id']]);
                        }
                    }
                    $client->disconnect();
                } catch (\Throwable) {
                    // skip unreachable routers
                }
            }
        } catch (\Throwable $e) {
            return response()->json(['status' => 'Gagal memutus koneksi: '.$e->getMessage()], 500);
        }

        return response()->json(['status' => 'Koneksi berhasil diputus.']);
    }

    public function bulkDestroy(Request $request): JsonResponse|RedirectResponse
    {
        $currentUser = auth()->user();
        $ids = $request->input('ids', []);
        if (! empty($ids)) {
            $query = PppUser::query()->whereIn('id', $ids)->accessibleBy($currentUser);
            $query->each(function (PppUser $u): void {
                $this->logActivity('deleted', 'PppUser', $u->id, $u->customer_name, (int) $u->owner_id);
            });
            $query->delete();
        }

        if ($request->wantsJson()) {
            return response()->json(['status' => 'User PPP terpilih dihapus.']);
        }

        return redirect()->route('ppp-users.index')->with('status', 'User PPP terpilih dihapus.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepareData(array $data, ?PppUser $existing = null): array
    {
        // Auto-generate customer_id jika kosong
        if (empty($data['customer_id'])) {
            $ownerId = isset($data['owner_id']) ? (int) $data['owner_id'] : ($existing?->owner_id ? (int) $existing->owner_id : null);
            $data['customer_id'] = PppUser::generateCustomerId($ownerId);
        }

        if (($data['tipe_ip'] ?? '') !== 'static') {
            $data['profile_group_id'] = null;
            $data['ip_static'] = null;
        }

        if (! empty($data['nomor_hp'])) {
            $data['nomor_hp'] = $this->normalizePhone($data['nomor_hp']);
        }

        if (($data['metode_login'] ?? '') === 'username_equals_password') {
            $data['ppp_password'] = $data['username'] ?? $data['ppp_password'] ?? null;
            $data['password_clientarea'] = $data['password_clientarea'] ?? $data['username'] ?? null;
        }

        $data['durasi_promo_bulan'] = $data['durasi_promo_bulan'] ?? 0;
        $data['biaya_instalasi'] = $data['biaya_instalasi'] ?? 0;
        $ownerId = $data['owner_id'] ?? $existing?->owner_id;
        $data['jatuh_tempo'] = $this->resolveDueDate($data['jatuh_tempo'] ?? null, $existing, $ownerId ? (int) $ownerId : null);
        $data = $this->assignSqlPoolIp($data, $existing);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function assignSqlPoolIp(array $data, ?PppUser $existing = null): array
    {
        $profileGroupId = $data['profile_group_id'] ?? $existing?->profile_group_id;
        if (! $profileGroupId) {
            return $data;
        }

        $ipType = $data['tipe_ip'] ?? $existing?->tipe_ip;
        if ($ipType !== 'static') {
            return $data;
        }

        $currentIp = $data['ip_static'] ?? $existing?->ip_static;
        if (! empty($currentIp)) {
            return $data;
        }

        $group = ProfileGroup::query()
            ->select('id', 'ip_pool_mode', 'range_start', 'range_end', 'host_min', 'host_max')
            ->find($profileGroupId);

        if (! $group || $group->ip_pool_mode !== 'sql') {
            return $data;
        }

        $nextIp = $this->nextAvailableSqlPoolIp($group, $existing);
        if ($nextIp === null) {
            throw ValidationException::withMessages([
                'ip_static' => 'SQL IP Pool sudah habis atau belum memiliki range IP yang valid.',
            ]);
        }

        $data['ip_static'] = $nextIp;

        return $data;
    }

    private function nextAvailableSqlPoolIp(ProfileGroup $group, ?PppUser $existing = null): ?string
    {
        [$rangeStart, $rangeEnd] = $this->resolvePoolRange($group);
        if (! $rangeStart || ! $rangeEnd) {
            return null;
        }

        $startLong = $this->ipToLong($rangeStart);
        $endLong = $this->ipToLong($rangeEnd);
        if ($startLong === null || $endLong === null || $startLong > $endLong) {
            return null;
        }

        $usedIps = PppUser::query()
            ->where('profile_group_id', $group->id)
            ->whereNotNull('ip_static')
            ->when($existing, fn ($query) => $query->whereKeyNot($existing->id))
            ->pluck('ip_static')
            ->map(fn (string $ip) => $this->ipToLong($ip))
            ->filter(fn (?int $ip) => $ip !== null)
            ->unique()
            ->all();

        $usedLookup = array_fill_keys($usedIps, true);

        for ($current = $startLong; $current <= $endLong; $current++) {
            if (! isset($usedLookup[$current])) {
                return $this->longToIp($current);
            }
        }

        return null;
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function resolvePoolRange(ProfileGroup $group): array
    {
        if ($group->range_start && $group->range_end) {
            return [$group->range_start, $group->range_end];
        }

        return [$group->host_min, $group->host_max];
    }

    private function ipToLong(string $ip): ?int
    {
        $long = ip2long($ip);
        if ($long === false) {
            return null;
        }

        return $long < 0 ? $long + (2 ** 32) : $long;
    }

    private function longToIp(int $long): string
    {
        if ($long > 2147483647) {
            $long -= 2 ** 32;
        }

        return long2ip($long);
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($phone, '0')) {
            $phone = '62'.substr($phone, 1);
        } elseif (! str_starts_with($phone, '62')) {
            $phone = '62'.$phone;
        }

        return $phone;
    }

    private function resolveDueDate(?string $input, ?PppUser $existing = null, ?int $ownerId = null): ?Carbon
    {
        if ($input) {
            return Carbon::parse($input)->endOfDay();
        }

        if ($existing) {
            return $existing->jatuh_tempo;
        }

        // Jika tenant punya billing_date, hitung jatuh tempo berdasarkan itu
        if ($ownerId) {
            $settings = TenantSettings::getOrCreate($ownerId);
            $billingDay = $settings->billing_date;

            if ($billingDay) {
                $billingDay = max(1, min(28, (int) $billingDay));
                $candidate = now()->startOfDay()->setDay($billingDay)->endOfDay();

                // Jika kandidat sudah sama dengan atau sebelum hari ini, pakai bulan depan
                if ($candidate->lte(now())) {
                    $candidate = $candidate->addMonthNoOverflow();
                }

                return $candidate;
            }
        }

        return now()->addMonthNoOverflow()->endOfDay();
    }

    private function createInvoiceForUser(PppUser $user, ?Carbon $dueOverride = null, bool $forceNew = false, bool $applyProrata = false): ?Invoice
    {
        if ($forceNew) {
            $user->invoices()->where('status', 'unpaid')->delete();
        } else {
            $hasUnpaid = $user->invoices()->where('status', 'unpaid')->exists();
            if ($hasUnpaid) {
                return null;
            }
        }

        $profile = $user->profile;
        if (! $profile) {
            return null;
        }

        $promoMonths = (int) ($user->durasi_promo_bulan ?? 0);
        $promoActive = $user->promo_aktif && $promoMonths > 0 && $user->created_at && $user->created_at->diffInMonths(now()) < $promoMonths;
        $hargaAsli  = $promoActive ? $profile->harga_promo : $profile->harga_modal;
        $basePrice  = $hargaAsli;
        $prorataApplied = false;

        // Prorata otomatis: hanya berlaku untuk invoice pertama saat pendaftaran baru
        if ($applyProrata && $user->prorata_otomatis && $user->jatuh_tempo) {
            $dueDateForProrata = Carbon::parse($user->jatuh_tempo)->startOfDay();
            $today             = now()->startOfDay();
            $sisaHari          = $today->diffInDays($dueDateForProrata, false); // negatif jika sudah lewat

            // Hitung total hari satu periode (mundur masa_aktif bulan dari jatuh_tempo)
            $masaAktif    = max(1, (int) $profile->masa_aktif);
            $periodeStart = $dueDateForProrata->copy()->subMonthsNoOverflow($masaAktif)->addDay();
            $totalHari    = $periodeStart->diffInDays($dueDateForProrata) + 1;

            // Prorata hanya berlaku jika sisa hari >= 3 dan lebih kecil dari total periode
            if ($sisaHari >= 3 && $totalHari > 0 && $sisaHari < $totalHari) {
                $basePrice      = round($hargaAsli * ($sisaHari / $totalHari), 2);
                $prorataApplied = true;
            }
        }

        // Tagihkan PPN hanya jika flag aktif di user
        $ppnPercent = $user->tagihkan_ppn ? (float) $profile->ppn : 0.0;
        $ppnAmount  = round($basePrice * ($ppnPercent / 100), 2);
        $total      = $basePrice + $ppnAmount;

        $prefix = TenantSettings::getOrCreate($user->owner_id)->invoice_prefix ?? 'INV';
        $invoiceNumber = Invoice::generateNumber($user->owner_id, $prefix);
        $dueDate = $dueOverride
            ? $dueOverride
            : ($user->jatuh_tempo ? Carbon::parse($user->jatuh_tempo)->endOfDay() : now()->addMonthNoOverflow()->endOfDay());

        Invoice::create([
            'invoice_number' => $invoiceNumber,
            'ppp_user_id' => $user->id,
            'ppp_profile_id' => $user->ppp_profile_id,
            'owner_id' => $user->owner_id,
            'customer_id' => $user->customer_id,
            'customer_name' => $user->customer_name,
            'tipe_service' => $user->tipe_service,
            'paket_langganan' => $profile->name,
            'harga_dasar' => $basePrice,
            'harga_asli' => $hargaAsli,
            'ppn_percent' => $ppnPercent,
            'ppn_amount' => $ppnAmount,
            'total' => $total,
            'promo_applied' => $promoActive,
            'prorata_applied' => $prorataApplied,
            'due_date' => $dueDate,
            'status' => 'unpaid',
        ]);

        return $user->invoices()->latest()->first();
    }

    private function markInvoicePaid(PppUser $user): void
    {
        $invoice = $user->invoices()->where('status', 'unpaid')->latest()->first();
        if ($invoice) {
            $invoice->update(['status' => 'paid']);
            $user->update(['status_bayar' => 'sudah_bayar']);
        }
    }

    private function ensureInvoiceWindow(PppUser $user): void
    {
        if ($user->status_bayar !== 'belum_bayar') {
            return;
        }

        $due = $user->jatuh_tempo ? Carbon::parse($user->jatuh_tempo)->endOfDay() : null;
        if (! $due) {
            return;
        }

        $now = now();
        $windowStart = $due->copy()->subDays(15);
        $hasUnpaid = $user->invoices()->where('status', 'unpaid')->exists();

        if (! $hasUnpaid && $now->betweenIncluded($windowStart, $due)) {
            $this->createInvoiceForUser($user);
        }
    }

    private function enforceOverdueAction(PppUser $user): void
    {
        if (! $user->jatuh_tempo) {
            return;
        }

        $due = Carbon::parse($user->jatuh_tempo)->endOfDay();
        if (now()->greaterThan($due) && $user->aksi_jatuh_tempo === 'isolir' && $user->status_akun !== 'isolir') {
            $user->update(['status_akun' => 'isolir']);
            // Sync RADIUS + setup Mikrotik + kick sesi aktif
            app(RadiusReplySynchronizer::class)->syncSingleUser($user);
            app(IsolirSynchronizer::class)->isolate($user);
        }
    }
}
