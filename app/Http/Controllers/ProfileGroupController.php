<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkExportProfileGroupRequest;
use App\Http\Requests\StoreProfileGroupRequest;
use App\Http\Requests\UpdateProfileGroupRequest;
use App\Models\MikrotikConnection;
use App\Models\ProfileGroup;
use App\Services\ProfileGroupExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Throwable;

class ProfileGroupController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $groups = ProfileGroup::query()->with('mikrotikConnection')->latest()->paginate(10);
        $mikrotikConnections = MikrotikConnection::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('profile_groups.index', compact('groups', 'mikrotikConnections'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $mikrotikConnections = \App\Models\MikrotikConnection::query()->orderBy('name')->get();
        $users = \App\Models\User::query()->orderBy('name')->get();

        return view('profile_groups.create', compact('mikrotikConnections', 'users'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProfileGroupRequest $request): RedirectResponse
    {
        $data = $this->hydrateHostRange($request->validated());

        ProfileGroup::create($data);

        return redirect()->route('profile-groups.index')->with('status', 'Profil group ditambahkan.');
    }

    /**
     * Display the specified resource.
     */
    public function show(ProfileGroup $profileGroup): RedirectResponse
    {
        return redirect()->route('profile-groups.edit', $profileGroup);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ProfileGroup $profileGroup): View
    {
        $mikrotikConnections = \App\Models\MikrotikConnection::query()->orderBy('name')->get();
        $users = \App\Models\User::query()->orderBy('name')->get();

        return view('profile_groups.edit', compact('profileGroup', 'mikrotikConnections', 'users'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProfileGroupRequest $request, ProfileGroup $profileGroup): RedirectResponse
    {
        $data = $this->hydrateHostRange($request->validated());

        $profileGroup->update($data);

        return redirect()->route('profile-groups.index')->with('status', 'Profil group diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ProfileGroup $profileGroup): RedirectResponse
    {
        $profileGroup->delete();

        return redirect()->route('profile-groups.index')->with('status', 'Profil group dihapus.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $ids = $request->input('ids', []);
        if (! empty($ids)) {
            ProfileGroup::query()->whereIn('id', $ids)->delete();
        }

        return redirect()->route('profile-groups.index')->with('status', 'Profil group terpilih dihapus.');
    }

    public function export(ProfileGroup $profileGroup, ProfileGroupExporter $exporter): RedirectResponse
    {
        $connections = $this->resolveExportConnections($profileGroup);
        if ($connections->isEmpty()) {
            return redirect()
                ->route('profile-groups.index')
                ->with('error', 'Tidak ada koneksi Mikrotik aktif untuk export profil group.');
        }

        $success = [];
        $errors = [];

        foreach ($connections as $connection) {
            try {
                $exporter->export($profileGroup, $connection);
                $success[] = $connection->name;
            } catch (Throwable $exception) {
                $errors[] = $connection->name.': '.$exception->getMessage();
            }
        }

        $redirect = redirect()->route('profile-groups.index');

        if (! empty($success)) {
            $redirect = $redirect->with('status', 'Export profil group sukses: '.implode(', ', $success).'.');
        }

        if (! empty($errors)) {
            $redirect = $redirect->with('error', 'Sebagian export gagal: '.implode(' | ', $errors));
        }

        return $redirect;
    }

    public function bulkExport(BulkExportProfileGroupRequest $request, ProfileGroupExporter $exporter): RedirectResponse
    {
        $groupIds = $request->input('profile_group_ids', []);
        $connectionIds = $request->input('mikrotik_connection_ids', []);

        $groups = ProfileGroup::query()
            ->whereIn('id', $groupIds)
            ->get();

        $connections = MikrotikConnection::query()
            ->whereIn('id', $connectionIds)
            ->get();

        if ($groups->isEmpty() || $connections->isEmpty()) {
            return redirect()
                ->route('profile-groups.index')
                ->with('error', 'Data export tidak lengkap.');
        }

        $success = [];
        $errors = [];

        foreach ($groups as $group) {
            foreach ($connections as $connection) {
                try {
                    $exporter->export($group, $connection);
                    $success[] = $group->name.' -> '.$connection->name;
                } catch (Throwable $exception) {
                    $errors[] = $group->name.' -> '.$connection->name.': '.$exception->getMessage();
                }
            }
        }

        $redirect = redirect()->route('profile-groups.index');

        if (! empty($success)) {
            $redirect = $redirect->with('status', 'Export profil group sukses: '.implode(', ', $success).'.');
        }

        if (! empty($errors)) {
            $redirect = $redirect->with('error', 'Sebagian export gagal: '.implode(' | ', $errors));
        }

        return $redirect;
    }

    private function hydrateHostRange(array $data): array
    {
        if (($data['ip_pool_mode'] ?? null) !== 'sql') {
            $data['host_min'] = null;
            $data['host_max'] = null;

            return $data;
        }

        if (empty($data['ip_address']) || empty($data['netmask'])) {
            return $data;
        }

        [$hostMin, $hostMax] = $this->calculateHostRange($data['ip_address'], $data['netmask']);
        $data['host_min'] = $hostMin;
        $data['host_max'] = $hostMax;

        return $data;
    }

    private function calculateHostRange(string $ip, string $netmask): array
    {
        $ipLong = ip2long($ip);
        if ($ipLong === false) {
            return [null, null];
        }

        $maskLong = str_contains($netmask, '.')
            ? ip2long($netmask)
            : (~0 << (32 - (int) $netmask));

        if ($maskLong === false) {
            return [null, null];
        }

        $network = $ipLong & $maskLong;
        $broadcast = $network | (~$maskLong);

        $hostMin = $network + 1;
        $hostMax = $broadcast - 1;

        return [long2ip($hostMin), long2ip($hostMax)];
    }

    /**
     * @return Collection<int, MikrotikConnection>
     */
    private function resolveExportConnections(ProfileGroup $profileGroup): Collection
    {
        if ($profileGroup->mikrotik_connection_id) {
            return MikrotikConnection::query()
                ->whereKey($profileGroup->mikrotik_connection_id)
                ->get();
        }

        return MikrotikConnection::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }
}
