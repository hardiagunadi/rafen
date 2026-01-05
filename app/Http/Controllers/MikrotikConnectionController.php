<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMikrotikConnectionRequest;
use App\Http\Requests\TestMikrotikConnectionRequest;
use App\Http\Requests\UpdateMikrotikConnectionRequest;
use App\Models\MikrotikConnection;
use App\Services\MikrotikPingService;
use App\Services\RadiusClientsSynchronizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class MikrotikConnectionController extends Controller
{
    public function __construct(
        private RadiusClientsSynchronizer $synchronizer,
        private MikrotikPingService $pingService,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $connections = MikrotikConnection::query()
            ->withCount('radiusAccounts')
            ->latest()
            ->paginate(10);

        return view('mikrotik_connections.index', compact('connections'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('mikrotik_connections.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMikrotikConnectionRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['use_ssl'] = $request->boolean('use_ssl');
        $data['is_active'] = $request->boolean('is_active', true);
        $data['username'] = $data['username'] ?: $this->generateApiUsername();
        $data['password'] = $data['password'] ?: $this->generateApiSecret();
        $data['radius_secret'] = $data['radius_secret'] ?: $data['password'];
        $data['monitor_interface'] = $data['monitor_interface'] ?? null;
        $data['timezone'] = $data['timezone'] ?? '+07:00 Asia/Jakarta';
        $data['ros_version'] = $data['ros_version'] ?? 'auto';

        MikrotikConnection::create($data);

        return $this->syncAndRedirect('Koneksi Mikrotik berhasil ditambahkan.');
    }

    /**
     * Display the specified resource.
     */
    public function show(MikrotikConnection $mikrotikConnection): RedirectResponse
    {
        return redirect()->route('mikrotik-connections.edit', $mikrotikConnection);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(MikrotikConnection $mikrotikConnection): View
    {
        return view('mikrotik_connections.edit', compact('mikrotikConnection'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMikrotikConnectionRequest $request, MikrotikConnection $mikrotikConnection): RedirectResponse
    {
        $data = $request->validated();
        $data['use_ssl'] = $request->boolean('use_ssl', $mikrotikConnection->use_ssl);
        $data['is_active'] = $request->boolean('is_active', $mikrotikConnection->is_active);
        $data['radius_secret'] = $data['radius_secret'] ?? $mikrotikConnection->radius_secret;
        $data['monitor_interface'] = $data['monitor_interface'] ?? $mikrotikConnection->monitor_interface;
        $data['timezone'] = $data['timezone'] ?? $mikrotikConnection->timezone ?? '+07:00 Asia/Jakarta';
        $data['ros_version'] = $data['ros_version'] ?? $mikrotikConnection->ros_version ?? 'auto';

        $mikrotikConnection->update($data);

        return $this->syncAndRedirect('Koneksi Mikrotik berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MikrotikConnection $mikrotikConnection): RedirectResponse
    {
        $mikrotikConnection->delete();

        return $this->syncAndRedirect('Koneksi Mikrotik dihapus.');
    }

    public function test(TestMikrotikConnectionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $timeout = (int) ($data['api_timeout'] ?? 10);
        $useSsl = (bool) ($data['use_ssl'] ?? false);
        $port = $useSsl
            ? (int) ($data['api_ssl_port'] ?? 8729)
            : (int) ($data['api_port'] ?? 8728);

        $result = $this->pingService->probe($data['host'], $timeout, $port, $useSsl);
        $message = $result['online']
            ? 'Koneksi OK'.($result['latency'] ? " ({$result['latency']} ms)" : '')
            : ($result['ping_success']
                ? 'Ping OK, port API '.$data['host'].':'.$port.' tertutup'
                : 'Ping ke '.$data['host'].' gagal');

        return response()->json([
            'success' => $result['online'],
            'latency' => $result['latency'],
            'port_open' => $result['port_open'],
            'message' => $message,
        ], $result['online'] ? 200 : 422);
    }

    private function syncAndRedirect(string $message): RedirectResponse
    {
        try {
            $this->synchronizer->sync();

            return redirect()
                ->route('mikrotik-connections.index')
                ->with('status', $message.' Radius clients.conf disinkron.');
        } catch (Throwable $exception) {
            return redirect()
                ->route('mikrotik-connections.index')
                ->with('status', $message)
                ->with('error', 'Sinkronisasi RADIUS gagal: '.$exception->getMessage());
        }
    }

    private function generateApiUsername(): string
    {
        return 'TMDRadius'.Str::upper(Str::random(6));
    }

    private function generateApiSecret(): string
    {
        return Str::password(10);
    }
}
