<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePppProfileRequest;
use App\Http\Requests\UpdatePppProfileRequest;
use App\Models\BandwidthProfile;
use App\Models\PppProfile;
use App\Models\ProfileGroup;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PppProfileController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $profiles = PppProfile::query()->with(['owner', 'profileGroup', 'bandwidthProfile'])->latest()->paginate(10);

        return view('ppp_profiles.index', compact('profiles'));
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
        PppProfile::create($request->validated());

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

        return redirect()->route('ppp-profiles.index')->with('status', 'Profil PPP diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PppProfile $pppProfile): RedirectResponse
    {
        $pppProfile->delete();

        return redirect()->route('ppp-profiles.index')->with('status', 'Profil PPP dihapus.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $ids = $request->input('ids', []);
        if (! empty($ids)) {
            PppProfile::query()->whereIn('id', $ids)->delete();
        }

        return redirect()->route('ppp-profiles.index')->with('status', 'Profil PPP terpilih dihapus.');
    }
}
