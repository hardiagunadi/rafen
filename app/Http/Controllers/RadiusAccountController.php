<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRadiusAccountRequest;
use App\Http\Requests\UpdateRadiusAccountRequest;
use App\Models\MikrotikConnection;
use App\Models\RadiusAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RadiusAccountController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $accounts = RadiusAccount::query()
            ->with('mikrotikConnection')
            ->latest()
            ->paginate(10);

        return view('radius_accounts.index', compact('accounts'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $mikrotikConnections = MikrotikConnection::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('radius_accounts.create', compact('mikrotikConnections'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRadiusAccountRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);

        if (($data['service'] ?? null) !== 'pppoe') {
            $data['ipv4_address'] = null;
        }

        RadiusAccount::create($data);

        return redirect()
            ->route('radius-accounts.index')
            ->with('status', 'Akun RADIUS berhasil dibuat.');
    }

    /**
     * Display the specified resource.
     */
    public function show(RadiusAccount $radiusAccount): RedirectResponse
    {
        return redirect()->route('radius-accounts.edit', $radiusAccount);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(RadiusAccount $radiusAccount): View
    {
        $mikrotikConnections = MikrotikConnection::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('radius_accounts.edit', compact('radiusAccount', 'mikrotikConnections'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRadiusAccountRequest $request, RadiusAccount $radiusAccount): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', $radiusAccount->is_active);

        if (($data['service'] ?? $radiusAccount->service) !== 'pppoe') {
            $data['ipv4_address'] = null;
        }

        $radiusAccount->update($data);

        return redirect()
            ->route('radius-accounts.index')
            ->with('status', 'Akun RADIUS diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(RadiusAccount $radiusAccount): RedirectResponse
    {
        $radiusAccount->delete();

        return redirect()
            ->route('radius-accounts.index')
            ->with('status', 'Akun RADIUS dihapus.');
    }
}
