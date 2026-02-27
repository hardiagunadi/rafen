<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHotspotUserRequest;
use App\Http\Requests\UpdateHotspotUserRequest;
use App\Models\HotspotProfile;
use App\Models\HotspotUser;
use App\Models\ProfileGroup;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HotspotUserController extends Controller
{
    public function index(Request $request): View
    {
        $perPage = (int) $request->input('per_page', 10);
        $search = $request->input('search');
        $currentUser = $request->user();

        $query = HotspotUser::query()->with(['owner', 'hotspotProfile', 'profileGroup']);

        $query->accessibleBy($currentUser);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_id', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            });
        }

        $users = $query->latest()->paginate($perPage > 0 ? $perPage : 10)->withQueryString();
        $users->getCollection()->each(fn (HotspotUser $user) => $this->enforceOverdueAction($user));

        $now = now();
        $stats = [
            'registrasi_bulan_ini' => HotspotUser::query()->accessibleBy($currentUser)->whereMonth('created_at', $now->month)->whereYear('created_at', $now->year)->count(),
            'pelanggan_isolir'      => HotspotUser::query()->accessibleBy($currentUser)->where('status_akun', 'isolir')->count(),
            'akun_disable'          => HotspotUser::query()->accessibleBy($currentUser)->where('status_akun', 'disable')->count(),
            'total'                 => HotspotUser::query()->accessibleBy($currentUser)->count(),
        ];

        return view('hotspot_users.index', compact('users', 'stats', 'perPage', 'search'));
    }

    public function create(): View
    {
        return view('hotspot_users.create', [
            'owners'   => User::query()->orderBy('name')->get(),
            'groups'   => ProfileGroup::query()->orderBy('name')->get(),
            'profiles' => HotspotProfile::query()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreHotspotUserRequest $request): RedirectResponse
    {
        $data = $this->prepareData($request->validated());
        HotspotUser::create($data);

        return redirect()->route('hotspot-users.index')->with('status', 'User Hotspot ditambahkan.');
    }

    public function show(HotspotUser $hotspotUser): RedirectResponse
    {
        return redirect()->route('hotspot-users.edit', $hotspotUser);
    }

    public function edit(HotspotUser $hotspotUser): View
    {
        return view('hotspot_users.edit', [
            'hotspotUser' => $hotspotUser,
            'owners'      => User::query()->orderBy('name')->get(),
            'groups'      => ProfileGroup::query()->orderBy('name')->get(),
            'profiles'    => HotspotProfile::query()->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateHotspotUserRequest $request, HotspotUser $hotspotUser): RedirectResponse
    {
        $data = $this->prepareData($request->validated());
        $hotspotUser->update($data);

        return redirect()->route('hotspot-users.index')->with('status', 'User Hotspot diperbarui.');
    }

    public function destroy(HotspotUser $hotspotUser): JsonResponse|RedirectResponse
    {
        $hotspotUser->delete();

        if (request()->wantsJson()) {
            return response()->json(['status' => 'User Hotspot dihapus.']);
        }

        return redirect()->route('hotspot-users.index')->with('status', 'User Hotspot dihapus.');
    }

    public function bulkDestroy(Request $request): JsonResponse|RedirectResponse
    {
        $ids = $request->input('ids', []);
        if (! empty($ids)) {
            HotspotUser::query()->whereIn('id', $ids)->delete();
        }

        if ($request->wantsJson()) {
            return response()->json(['status' => 'User Hotspot terpilih dihapus.']);
        }

        return redirect()->route('hotspot-users.index')->with('status', 'User Hotspot terpilih dihapus.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepareData(array $data): array
    {
        if (! empty($data['nomor_hp'])) {
            $data['nomor_hp'] = $this->normalizePhone($data['nomor_hp']);
        }

        if (! isset($data['biaya_instalasi'])) {
            $data['biaya_instalasi'] = 0;
        }

        if (isset($data['jatuh_tempo']) && $data['jatuh_tempo']) {
            $data['jatuh_tempo'] = Carbon::parse($data['jatuh_tempo'])->endOfDay();
        }

        return $data;
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

    private function enforceOverdueAction(HotspotUser $user): void
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
