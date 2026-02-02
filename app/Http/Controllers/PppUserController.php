<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePppUserRequest;
use App\Http\Requests\UpdatePppUserRequest;
use App\Models\Invoice;
use App\Models\PppProfile;
use App\Models\PppUser;
use App\Models\ProfileGroup;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PppUserController extends Controller
{
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
        return view('ppp_users.create', [
            'owners' => User::query()->orderBy('name')->get(),
            'groups' => ProfileGroup::query()->orderBy('name')->get(),
            'profiles' => PppProfile::query()->orderBy('name')->get(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePppUserRequest $request): RedirectResponse
    {
        $data = $this->prepareData($request->validated());

        $user = PppUser::create($data);

        if ($data['status_bayar'] === 'belum_bayar') {
            $this->createInvoiceForUser($user);
        }

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
        return view('ppp_users.edit', [
            'pppUser' => $pppUser,
            'owners' => User::query()->orderBy('name')->get(),
            'groups' => ProfileGroup::query()->orderBy('name')->get(),
            'profiles' => PppProfile::query()->orderBy('name')->get(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePppUserRequest $request, PppUser $pppUser): RedirectResponse
    {
        $originalStatus = $pppUser->status_bayar;
        $originalStatus = $pppUser->status_bayar;
        $originalDue = $pppUser->jatuh_tempo;
        $data = $this->prepareData($request->validated(), $pppUser);

        $pppUser->update($data);

        if ($data['status_bayar'] === 'belum_bayar' && $originalStatus !== 'belum_bayar') {
            $this->createInvoiceForUser($pppUser);
        }

        if ($data['status_bayar'] === 'belum_bayar' && $originalDue !== $pppUser->jatuh_tempo) {
            $this->createInvoiceForUser($pppUser, $pppUser->jatuh_tempo ? Carbon::parse($pppUser->jatuh_tempo)->endOfDay() : null, true);
        }

        if ($data['status_bayar'] === 'sudah_bayar' && $originalStatus !== 'sudah_bayar') {
            $this->markInvoicePaid($pppUser);
        }

        return redirect()->route('ppp-users.index')->with('status', 'User PPP diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PppUser $pppUser): RedirectResponse
    {
        $pppUser->delete();

        return redirect()->route('ppp-users.index')->with('status', 'User PPP dihapus.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $ids = $request->input('ids', []);
        if (! empty($ids)) {
            PppUser::query()->whereIn('id', $ids)->delete();
        }

        return redirect()->route('ppp-users.index')->with('status', 'User PPP terpilih dihapus.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepareData(array $data, ?PppUser $existing = null): array
    {
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
        $data['jatuh_tempo'] = $this->resolveDueDate($data['jatuh_tempo'] ?? null, $existing);
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

    private function resolveDueDate(?string $input, ?PppUser $existing = null): ?Carbon
    {
        if ($input) {
            return Carbon::parse($input)->endOfDay();
        }

        if ($existing) {
            return $existing->jatuh_tempo;
        }

        return now()->addMonthNoOverflow()->endOfDay();
    }

    private function createInvoiceForUser(PppUser $user, ?Carbon $dueOverride = null, bool $forceNew = false): void
    {
        if ($forceNew) {
            $user->invoices()->where('status', 'unpaid')->delete();
        } else {
            $hasUnpaid = $user->invoices()->where('status', 'unpaid')->exists();
            if ($hasUnpaid) {
                return;
            }
        }

        $profile = $user->profile;
        if (! $profile) {
            return;
        }

        $promoMonths = (int) ($user->durasi_promo_bulan ?? 0);
        $promoActive = $user->promo_aktif && $promoMonths > 0 && $user->created_at && $user->created_at->diffInMonths(now()) < $promoMonths;
        $basePrice = $promoActive ? $profile->harga_promo : $profile->harga_modal;
        $ppnPercent = (float) $profile->ppn;
        $ppnAmount = round($basePrice * ($ppnPercent / 100), 2);
        $total = $basePrice + $ppnAmount;

        $invoiceNumber = $this->generateInvoiceNumber();
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
            'ppn_percent' => $ppnPercent,
            'ppn_amount' => $ppnAmount,
            'total' => $total,
            'promo_applied' => $promoActive,
            'due_date' => $dueDate,
            'status' => 'unpaid',
        ]);
    }

    private function generateInvoiceNumber(): string
    {
        do {
            $number = 'INV-'.str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT);
        } while (Invoice::where('invoice_number', $number)->exists());

        return $number;
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
        }
    }
}
