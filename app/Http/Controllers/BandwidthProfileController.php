<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBandwidthProfileRequest;
use App\Http\Requests\UpdateBandwidthProfileRequest;
use App\Models\BandwidthProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BandwidthProfileController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $profiles = BandwidthProfile::query()->latest()->paginate(10);

        return view('bandwidth_profiles.index', compact('profiles'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('bandwidth_profiles.create', [
            'users' => User::query()->orderBy('name')->get(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreBandwidthProfileRequest $request): RedirectResponse
    {
        BandwidthProfile::create($request->validated());

        return redirect()->route('bandwidth-profiles.index')->with('status', 'Bandwidth profile ditambahkan.');
    }

    /**
     * Display the specified resource.
     */
    public function show(BandwidthProfile $bandwidthProfile): RedirectResponse
    {
        return redirect()->route('bandwidth-profiles.edit', $bandwidthProfile);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(BandwidthProfile $bandwidthProfile): View
    {
        return view('bandwidth_profiles.edit', [
            'bandwidthProfile' => $bandwidthProfile,
            'users' => User::query()->orderBy('name')->get(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateBandwidthProfileRequest $request, BandwidthProfile $bandwidthProfile): RedirectResponse
    {
        $bandwidthProfile->update($request->validated());

        return redirect()->route('bandwidth-profiles.index')->with('status', 'Bandwidth profile diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(BandwidthProfile $bandwidthProfile): RedirectResponse
    {
        $bandwidthProfile->delete();

        return redirect()->route('bandwidth-profiles.index')->with('status', 'Bandwidth profile dihapus.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $ids = $request->input('ids', []);
        if (! empty($ids)) {
            BandwidthProfile::query()->whereIn('id', $ids)->delete();
        }

        return redirect()->route('bandwidth-profiles.index')->with('status', 'Bandwidth profile terpilih dihapus.');
    }
}
