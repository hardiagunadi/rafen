<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHotspotProfileRequest;
use App\Http\Requests\UpdateHotspotProfileRequest;
use App\Models\BandwidthProfile;
use App\Models\HotspotProfile;
use App\Models\ProfileGroup;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class HotspotProfileController extends Controller
{
    public function datatable(Request $request): JsonResponse
    {
        $user  = $request->user();
        $query = HotspotProfile::query()
            ->accessibleBy($user)
            ->with(['owner', 'profileGroup', 'bandwidthProfile'])
            ->select('hotspot_profiles.*');

        return DataTables::of($query)
            ->addColumn('owner_name', fn ($p) => $p->owner?->name ?? '-')
            ->addColumn('bandwidth_name', fn ($p) => $p->bandwidthProfile?->name ?? '-')
            ->addColumn('profile_group_name', fn ($p) => $p->profileGroup?->name ?? '-')
            ->addColumn('tipe_profil', function ($p) {
                if ($p->profile_type === 'unlimited') {
                    return '<span class="badge badge-success">Unlimited</span><div class="small text-muted">'.$p->masa_aktif_value.' '.$p->masa_aktif_unit.'</div>';
                }
                if ($p->limit_type === 'time') {
                    return '<span class="badge badge-info">Limited - Time</span><div class="small text-muted">'.$p->time_limit_value.' '.$p->time_limit_unit.'</div>';
                }
                if ($p->limit_type === 'quota') {
                    return '<span class="badge badge-info">Limited - Quota</span><div class="small text-muted">'.$p->quota_limit_value.' '.strtoupper($p->quota_limit_unit ?? '').'</div>';
                }
                return '-';
            })
            ->addColumn('prioritas_label', function ($p) {
                return $p->prioritas === 'default' ? 'Default' : 'Prioritas '.((int) str_replace('prioritas', '', $p->prioritas));
            })
            ->addColumn('aksi', function ($p) {
                $edit = route('hotspot-profiles.edit', $p);
                $del  = route('hotspot-profiles.destroy', $p);
                return '<a href="'.$edit.'" class="btn btn-sm btn-outline-primary">Edit</a> '
                    .'<button type="button" class="btn btn-sm btn-outline-danger" data-ajax-delete="'.$del.'" data-confirm="Hapus profil ini?">Delete</button>';
            })
            ->rawColumns(['tipe_profil', 'aksi'])
            ->make(true);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        return view('hotspot_profiles.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $user = auth()->user();
        return view('hotspot_profiles.create', [
            'owners'     => $user->isSuperAdmin() ? User::query()->orderBy('name')->get() : collect([$user]),
            'groups'     => ProfileGroup::query()->accessibleBy($user)->orderBy('name')->get(),
            'bandwidths' => BandwidthProfile::query()->accessibleBy($user)->orderBy('name')->get(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreHotspotProfileRequest $request): RedirectResponse
    {
        HotspotProfile::create($this->sanitizeData($request->validated()));

        return redirect()->route('hotspot-profiles.index')->with('status', 'Profil Hotspot ditambahkan.');
    }

    /**
     * Display the specified resource.
     */
    public function show(HotspotProfile $hotspotProfile): RedirectResponse
    {
        return redirect()->route('hotspot-profiles.edit', $hotspotProfile);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(HotspotProfile $hotspotProfile): View
    {
        $user = auth()->user();
        if (! $user->isSuperAdmin() && $hotspotProfile->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }
        return view('hotspot_profiles.edit', [
            'hotspotProfile' => $hotspotProfile,
            'owners'         => $user->isSuperAdmin() ? User::query()->orderBy('name')->get() : collect([$user]),
            'groups'         => ProfileGroup::query()->accessibleBy($user)->orderBy('name')->get(),
            'bandwidths'     => BandwidthProfile::query()->accessibleBy($user)->orderBy('name')->get(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateHotspotProfileRequest $request, HotspotProfile $hotspotProfile): RedirectResponse
    {
        $hotspotProfile->update($this->sanitizeData($request->validated()));

        return redirect()->route('hotspot-profiles.index')->with('status', 'Profil Hotspot diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(HotspotProfile $hotspotProfile): JsonResponse|RedirectResponse
    {
        $user = auth()->user();
        if (! $user->isSuperAdmin() && $hotspotProfile->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }
        $hotspotProfile->delete();

        if (request()->wantsJson()) {
            return response()->json(['status' => 'Profil Hotspot dihapus.']);
        }

        return redirect()->route('hotspot-profiles.index')->with('status', 'Profil Hotspot dihapus.');
    }

    public function bulkDestroy(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $ids  = $request->input('ids', []);
        if (! empty($ids)) {
            HotspotProfile::query()->accessibleBy($user)->whereIn('id', $ids)->delete();
        }

        if ($request->wantsJson()) {
            return response()->json(['status' => 'Profil Hotspot terpilih dihapus.']);
        }

        return redirect()->route('hotspot-profiles.index')->with('status', 'Profil Hotspot terpilih dihapus.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sanitizeData(array $data): array
    {
        $profileType = $data['profile_type'] ?? null;
        $limitType = $data['limit_type'] ?? null;

        if ($profileType === 'unlimited') {
            $data['limit_type'] = null;
            $data['time_limit_value'] = null;
            $data['time_limit_unit'] = null;
            $data['quota_limit_value'] = null;
            $data['quota_limit_unit'] = null;
        }

        if ($profileType === 'limited') {
            $data['masa_aktif_value'] = null;
            $data['masa_aktif_unit'] = null;

            if ($limitType === 'time') {
                $data['quota_limit_value'] = null;
                $data['quota_limit_unit'] = null;
            }

            if ($limitType === 'quota') {
                $data['time_limit_value'] = null;
                $data['time_limit_unit'] = null;
            }
        }

        return $data;
    }
}
