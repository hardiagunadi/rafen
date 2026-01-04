<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHotspotProfileRequest;
use App\Http\Requests\UpdateHotspotProfileRequest;
use App\Models\BandwidthProfile;
use App\Models\HotspotProfile;
use App\Models\ProfileGroup;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HotspotProfileController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $profiles = HotspotProfile::query()->with(['owner', 'profileGroup', 'bandwidthProfile'])->latest()->paginate(10);

        return view('hotspot_profiles.index', compact('profiles'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('hotspot_profiles.create', [
            'owners' => User::query()->orderBy('name')->get(),
            'groups' => ProfileGroup::query()->orderBy('name')->get(),
            'bandwidths' => BandwidthProfile::query()->orderBy('name')->get(),
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
        return view('hotspot_profiles.edit', [
            'hotspotProfile' => $hotspotProfile,
            'owners' => User::query()->orderBy('name')->get(),
            'groups' => ProfileGroup::query()->orderBy('name')->get(),
            'bandwidths' => BandwidthProfile::query()->orderBy('name')->get(),
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
    public function destroy(HotspotProfile $hotspotProfile): RedirectResponse
    {
        $hotspotProfile->delete();

        return redirect()->route('hotspot-profiles.index')->with('status', 'Profil Hotspot dihapus.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $ids = $request->input('ids', []);
        if (! empty($ids)) {
            HotspotProfile::query()->whereIn('id', $ids)->delete();
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
