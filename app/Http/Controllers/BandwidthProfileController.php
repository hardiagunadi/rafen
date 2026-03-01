<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBandwidthProfileRequest;
use App\Http\Requests\UpdateBandwidthProfileRequest;
use App\Models\BandwidthProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
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
        return view('bandwidth_profiles.index');
    }

    public function datatable(Request $request): JsonResponse
    {
        $search = $request->input('search.value', '');

        $query = BandwidthProfile::query()
            ->when($search !== '', fn($q) => $q->where('name', 'like', "%{$search}%"))
            ->latest();

        $total    = BandwidthProfile::count();
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
                'upload'      => $r->upload_min_mbps.' | '.$r->upload_max_mbps,
                'download'    => $r->download_min_mbps.' | '.$r->download_max_mbps,
                'owner'       => $r->owner ?? '-',
                'edit_url'    => route('bandwidth-profiles.edit', $r->id),
                'destroy_url' => route('bandwidth-profiles.destroy', $r->id),
            ]),
        ]);
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
    public function destroy(BandwidthProfile $bandwidthProfile): JsonResponse|RedirectResponse
    {
        $bandwidthProfile->delete();

        if (request()->wantsJson()) {
            return response()->json(['status' => 'Bandwidth profile dihapus.']);
        }

        return redirect()->route('bandwidth-profiles.index')->with('status', 'Bandwidth profile dihapus.');
    }

    public function bulkDestroy(Request $request): JsonResponse|RedirectResponse
    {
        $ids = $request->input('ids', []);
        if (! empty($ids)) {
            BandwidthProfile::query()->whereIn('id', $ids)->delete();
        }

        if ($request->wantsJson()) {
            return response()->json(['status' => 'Bandwidth profile terpilih dihapus.']);
        }

        return redirect()->route('bandwidth-profiles.index')->with('status', 'Bandwidth profile terpilih dihapus.');
    }
}
