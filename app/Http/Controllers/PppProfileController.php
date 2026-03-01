<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePppProfileRequest;
use App\Http\Requests\UpdatePppProfileRequest;
use App\Models\BandwidthProfile;
use App\Models\PppProfile;
use App\Models\ProfileGroup;
use App\Models\User;
use App\Traits\LogsActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PppProfileController extends Controller
{
    use LogsActivity;
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        return view('ppp_profiles.index');
    }

    public function datatable(Request $request): JsonResponse
    {
        $search = $request->input('search.value', '');

        $query = PppProfile::query()
            ->with(['owner', 'profileGroup', 'bandwidthProfile'])
            ->when($search !== '', fn($q) => $q->where('name', 'like', "%{$search}%"))
            ->latest();

        $total    = PppProfile::count();
        $filtered = $query->count();
        $rows     = $query->offset($request->integer('start'))
            ->limit(max(1, $request->integer('length', 20)))
            ->get();

        return response()->json([
            'draw'            => $request->integer('draw'),
            'recordsTotal'    => $total,
            'recordsFiltered' => $filtered,
            'data'            => $rows->map(fn($r) => [
                'id'          => $r->id,
                'name'        => $r->name,
                'owner_name'  => $r->owner?->name ?? '-',
                'harga_modal' => number_format($r->harga_modal, 0, ',', '.'),
                'harga_promo' => number_format($r->harga_promo, 0, ',', '.'),
                'ppn'         => number_format($r->ppn, 2).'%',
                'group_name'  => $r->profileGroup?->name ?? '-',
                'bandwidth'   => $r->bandwidthProfile?->name ?? '-',
                'masa_aktif'  => $r->masa_aktif.' '.$r->satuan,
                'edit_url'    => route('ppp-profiles.edit', $r->id),
                'destroy_url' => route('ppp-profiles.destroy', $r->id),
            ]),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('ppp_profiles.create', [
            'owners' => User::query()->orderBy('name')->get(),
            'groups' => ProfileGroup::query()->orderBy('name')->get(),
            'bandwidths' => BandwidthProfile::query()->orderBy('name')->get(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePppProfileRequest $request): RedirectResponse
    {
        $profile = PppProfile::create($request->validated());

        $this->logActivity('created', 'PppProfile', $profile->id, $profile->name, (int) $profile->owner_id);

        return redirect()->route('ppp-profiles.index')->with('status', 'Profil PPP ditambahkan.');
    }

    /**
     * Display the specified resource.
     */
    public function show(PppProfile $pppProfile): RedirectResponse
    {
        return redirect()->route('ppp-profiles.edit', $pppProfile);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PppProfile $pppProfile): View
    {
        return view('ppp_profiles.edit', [
            'pppProfile' => $pppProfile,
            'owners' => User::query()->orderBy('name')->get(),
            'groups' => ProfileGroup::query()->orderBy('name')->get(),
            'bandwidths' => BandwidthProfile::query()->orderBy('name')->get(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePppProfileRequest $request, PppProfile $pppProfile): RedirectResponse
    {
        $pppProfile->update($request->validated());

        $this->logActivity('updated', 'PppProfile', $pppProfile->id, $pppProfile->name, (int) $pppProfile->owner_id);

        return redirect()->route('ppp-profiles.index')->with('status', 'Profil PPP diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PppProfile $pppProfile): JsonResponse|RedirectResponse
    {
        $this->logActivity('deleted', 'PppProfile', $pppProfile->id, $pppProfile->name, (int) $pppProfile->owner_id);
        $pppProfile->delete();

        if (request()->wantsJson()) {
            return response()->json(['status' => 'Profil PPP dihapus.']);
        }

        return redirect()->route('ppp-profiles.index')->with('status', 'Profil PPP dihapus.');
    }

    public function bulkDestroy(Request $request): JsonResponse|RedirectResponse
    {
        $ids = $request->input('ids', []);
        if (! empty($ids)) {
            PppProfile::query()->whereIn('id', $ids)->each(function (PppProfile $p): void {
                $this->logActivity('deleted', 'PppProfile', $p->id, $p->name, (int) $p->owner_id);
            });
            PppProfile::query()->whereIn('id', $ids)->delete();
        }

        if ($request->wantsJson()) {
            return response()->json(['status' => 'Profil PPP terpilih dihapus.']);
        }

        return redirect()->route('ppp-profiles.index')->with('status', 'Profil PPP terpilih dihapus.');
    }
}
