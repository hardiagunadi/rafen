<?php

namespace App\Http\Controllers;

use App\Http\Requests\DetectOltModelRequest;
use App\Http\Requests\DetectOltOidRequest;
use App\Http\Requests\StoreOltConnectionRequest;
use App\Http\Requests\UpdateOltConnectionRequest;
use App\Models\OltConnection;
use App\Models\OltOnuOptic;
use App\Services\HsgqSnmpCollector;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class OltConnectionController extends Controller
{
    public function __construct(private HsgqSnmpCollector $collector) {}

    public function index(): View
    {
        $user = auth()->user();

        $connections = OltConnection::query()
            ->accessibleBy($user)
            ->withCount('onuOptics')
            ->latest()
            ->get();

        return view('olt_connections.index', compact('connections'));
    }

    public function create(): View
    {
        if (auth()->user()->role === 'teknisi') {
            abort(403);
        }

        return view('olt_connections.create', [
            'hsgqModels' => HsgqSnmpCollector::availableModels(),
        ]);
    }

    public function store(StoreOltConnectionRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['owner_id'] = auth()->user()->effectiveOwnerId();
        $data['is_active'] = $request->boolean('is_active', true);

        $oltConnection = OltConnection::query()->create($data);

        return redirect()
            ->route('olt-connections.show', $oltConnection)
            ->with('status', 'Koneksi OLT HSGQ berhasil ditambahkan.');
    }

    public function show(OltConnection $oltConnection, Request $request): View
    {
        $this->authorizeAccess($oltConnection);

        $search = trim((string) $request->input('search'));
        $selectedPortId = trim((string) $request->input('port_id'));
        $selectedStatus = $this->normalizeSelectedStatus((string) $request->input('status'));

        $summaryRows = $oltConnection->onuOptics()
            ->whereNotNull('pon_interface')
            ->get(['pon_interface', 'status'])
            ->groupBy('pon_interface')
            ->map(function ($items, string $portId): array {
                $total = $items->count();
                $online = $items->filter(fn (OltOnuOptic $item): bool => $this->isOnlineStatus($item->status))->count();

                return [
                    'port_id' => $portId,
                    'total' => $total,
                    'online' => $online,
                    'offline' => max(0, $total - $online),
                ];
            })
            ->sortBy(function (array $row): int {
                return (int) preg_replace('/\D+/', '', $row['port_id']);
            })
            ->values();

        $availablePortIds = $summaryRows
            ->pluck('port_id')
            ->values();

        $activeSummary = $summaryRows->sum('total');
        $onlineSummary = $summaryRows->sum('online');
        $offlineSummary = $summaryRows->sum('offline');
        $totalOnuStored = $oltConnection->onuOptics()->count();

        return view('olt_connections.show', compact(
            'oltConnection',
            'totalOnuStored',
            'search',
            'availablePortIds',
            'selectedPortId',
            'selectedStatus',
            'summaryRows',
            'activeSummary',
            'onlineSummary',
            'offlineSummary',
        ));
    }

    public function datatable(OltConnection $oltConnection, Request $request): JsonResponse
    {
        $this->authorizeAccess($oltConnection);

        $search = trim((string) $request->input('search.value', $request->input('search', '')));
        $selectedPortId = trim((string) $request->input('port_id'));
        $selectedStatus = $this->normalizeSelectedStatus((string) $request->input('status'));

        $query = $this->buildOnuOpticsQuery($oltConnection, $search, $selectedPortId, $selectedStatus);
        $total = $oltConnection->onuOptics()->count();
        $filtered = (clone $query)->count();

        $this->applyDatatableOrdering($query, $request);

        $rows = $query
            ->offset($request->integer('start'))
            ->limit(max(1, $request->integer('length', 50)))
            ->get();

        return response()->json([
            'draw' => $request->integer('draw'),
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $rows->map(fn (OltOnuOptic $onuOptic): array => [
                'pon_interface' => $onuOptic->pon_interface ?? '-',
                'onu_number' => $onuOptic->onu_number ?? '-',
                'onu_id' => $this->formatOnuId($onuOptic->pon_interface, $onuOptic->onu_number),
                'serial_number' => $this->formatMacIdentifier($onuOptic->serial_number),
                'onu_name' => $onuOptic->onu_name ?? '-',
                'distance_m' => $onuOptic->distance_m !== null ? number_format((int) $onuOptic->distance_m).' m' : '-',
                'rx_onu_dbm' => $onuOptic->rx_onu_dbm !== null ? number_format((float) $onuOptic->rx_onu_dbm, 2).' dBm' : '-',
                'tx_onu_dbm' => $onuOptic->tx_onu_dbm !== null ? number_format((float) $onuOptic->tx_onu_dbm, 2).' dBm' : '-',
                'tx_olt_dbm' => $onuOptic->tx_olt_dbm !== null ? number_format((float) $onuOptic->tx_olt_dbm, 2).' dBm' : '-',
                'status' => $this->formatStatusLabel($onuOptic->status),
                'status_badge' => $this->formatStatusBadge($onuOptic->status),
                'last_seen_at' => $onuOptic->last_seen_at?->format('Y-m-d H:i:s') ?? '-',
            ]),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function statusFilterValues(string $status): array
    {
        return match ($status) {
            'online' => ['1', 'online', 'ONLINE', 'up', 'UP', 'true', 'TRUE'],
            'offline' => ['2', 'offline', 'OFFLINE', 'down', 'DOWN', 'false', 'FALSE'],
            default => [],
        };
    }

    private function isOnlineStatus(?string $status): bool
    {
        if ($status === null) {
            return false;
        }

        return in_array($status, $this->statusFilterValues('online'), true);
    }

    private function normalizeSelectedStatus(string $status): string
    {
        return in_array($status, ['', 'online', 'offline'], true) ? $status : '';
    }

    private function buildOnuOpticsQuery(
        OltConnection $oltConnection,
        string $search,
        string $selectedPortId,
        string $selectedStatus
    ): Builder {
        $query = $oltConnection->onuOptics()->getQuery();

        return $this->applyOnuOpticsFilters($query, $search, $selectedPortId, $selectedStatus);
    }

    private function applyOnuOpticsFilters(
        Builder $query,
        string $search,
        string $selectedPortId,
        string $selectedStatus
    ): Builder {
        return $query
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $builder->where(function (Builder $childQuery) use ($search): void {
                    $childQuery->where('onu_index', 'like', '%'.$search.'%')
                        ->orWhere('serial_number', 'like', '%'.$search.'%')
                        ->orWhere('onu_name', 'like', '%'.$search.'%')
                        ->orWhere('pon_interface', 'like', '%'.$search.'%')
                        ->orWhere('onu_number', 'like', '%'.$search.'%');

                    if (preg_match('/^(?:PON)?(\d+)\/(\d+)$/i', $search, $matches) === 1) {
                        $childQuery->orWhere(function (Builder $onuIdQuery) use ($matches): void {
                            $onuIdQuery->where('pon_interface', 'PON'.(int) $matches[1])
                                ->where('onu_number', (string) (int) $matches[2]);
                        });
                    }
                });
            })
            ->when($selectedPortId !== '', function (Builder $builder) use ($selectedPortId): void {
                $builder->where('pon_interface', $selectedPortId);
            })
            ->when($selectedStatus !== '', function (Builder $builder) use ($selectedStatus): void {
                $builder->whereIn('status', $this->statusFilterValues($selectedStatus));
            });
    }

    private function applyDatatableOrdering(Builder $query, Request $request): void
    {
        $columnMap = [
            0 => ['pon_interface'],
            1 => ['onu_number'],
            2 => ['pon_interface', 'onu_number'],
            3 => ['serial_number'],
            4 => ['onu_name'],
            5 => ['distance_m'],
            6 => ['rx_onu_dbm'],
            7 => ['tx_onu_dbm'],
            8 => ['tx_olt_dbm'],
            9 => ['status'],
            10 => ['last_seen_at'],
        ];

        $orders = $request->input('order', []);

        if (! is_array($orders) || $orders === []) {
            $query->orderBy('pon_interface')->orderBy('onu_number');

            return;
        }

        foreach (array_slice($orders, 0, 2) as $order) {
            $columnIndex = isset($order['column']) ? (int) $order['column'] : null;
            $direction = strtolower((string) ($order['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

            if ($columnIndex === null || ! array_key_exists($columnIndex, $columnMap)) {
                continue;
            }

            foreach ($columnMap[$columnIndex] as $column) {
                $query->orderBy($column, $direction);
            }
        }
    }

    private function formatOnuId(?string $ponInterface, ?string $onuNumber): string
    {
        if ($ponInterface === null || $onuNumber === null) {
            return '-';
        }

        return preg_replace('/^PON/i', '', $ponInterface).'/'.$onuNumber;
    }

    private function formatMacIdentifier(?string $identifier): string
    {
        $identifier = trim((string) $identifier);

        if ($identifier === '') {
            return '-';
        }

        $normalized = preg_replace('/[^A-Fa-f0-9]/', '', $identifier);

        if ($normalized !== null && strlen($normalized) === 12) {
            return strtolower(implode(':', str_split($normalized, 2)));
        }

        return $identifier;
    }

    private function formatStatusLabel(?string $status): string
    {
        $statusValue = strtolower((string) $status);

        return match ($statusValue) {
            '1' => 'ONLINE',
            '2' => 'OFFLINE',
            default => $status ? strtoupper((string) $status) : '-',
        };
    }

    private function formatStatusBadge(?string $status): string
    {
        return '<span class="badge badge-'.$this->statusCssClass($status).'">'.$this->formatStatusLabel($status).'</span>';
    }

    private function statusCssClass(?string $status): string
    {
        $statusValue = strtolower((string) $status);

        if (in_array($statusValue, ['1', 'up', 'online'], true) || str_contains($statusValue, 'up') || str_contains($statusValue, 'online')) {
            return 'success';
        }

        if (in_array($statusValue, ['2', 'down', 'offline'], true) || str_contains($statusValue, 'down') || str_contains($statusValue, 'offline')) {
            return 'danger';
        }

        return 'secondary';
    }

    public function edit(OltConnection $oltConnection): View
    {
        if (auth()->user()->role === 'teknisi') {
            abort(403);
        }

        $this->authorizeAccess($oltConnection);

        return view('olt_connections.edit', [
            'oltConnection' => $oltConnection,
            'hsgqModels' => HsgqSnmpCollector::availableModels(),
        ]);
    }

    public function update(UpdateOltConnectionRequest $request, OltConnection $oltConnection): RedirectResponse
    {
        if (auth()->user()->role === 'teknisi') {
            abort(403);
        }

        $this->authorizeAccess($oltConnection);

        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', $oltConnection->is_active);

        $oltConnection->update($data);

        return redirect()
            ->route('olt-connections.show', $oltConnection)
            ->with('status', 'Konfigurasi OLT HSGQ berhasil diperbarui.');
    }

    public function destroy(OltConnection $oltConnection): RedirectResponse
    {
        if (auth()->user()->role === 'teknisi') {
            abort(403);
        }

        $this->authorizeAccess($oltConnection);
        $oltConnection->delete();

        return redirect()
            ->route('olt-connections.index')
            ->with('status', 'Koneksi OLT HSGQ dihapus.');
    }

    public function poll(OltConnection $oltConnection): RedirectResponse
    {
        if (auth()->user()->role === 'teknisi') {
            abort(403);
        }

        $this->authorizeAccess($oltConnection);

        try {
            $records = $this->collector->collect($oltConnection);
            $now = now();

            if (! empty($records)) {
                $payload = array_map(function (array $record) use ($oltConnection, $now): array {
                    return [
                        'olt_connection_id' => $oltConnection->id,
                        'owner_id' => $oltConnection->owner_id,
                        'onu_index' => (string) $record['onu_index'],
                        'pon_interface' => $record['pon_interface'] ?? null,
                        'onu_number' => $record['onu_number'] ?? null,
                        'serial_number' => $record['serial_number'] ?? null,
                        'onu_name' => $record['onu_name'] ?? null,
                        'distance_m' => $record['distance_m'] ?? null,
                        'rx_onu_dbm' => $record['rx_onu_dbm'] ?? null,
                        'tx_onu_dbm' => $record['tx_onu_dbm'] ?? null,
                        'rx_olt_dbm' => $record['rx_olt_dbm'] ?? null,
                        'tx_olt_dbm' => $record['tx_olt_dbm'] ?? null,
                        'status' => $record['status'] ?? null,
                        'raw_payload' => is_array($record['raw_payload'] ?? null)
                            ? json_encode($record['raw_payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                            : ($record['raw_payload'] ?? null),
                        'last_seen_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }, $records);

                OltOnuOptic::query()->upsert(
                    $payload,
                    ['olt_connection_id', 'onu_index'],
                    [
                        'pon_interface',
                        'onu_number',
                        'serial_number',
                        'onu_name',
                        'distance_m',
                        'rx_onu_dbm',
                        'tx_onu_dbm',
                        'rx_olt_dbm',
                        'tx_olt_dbm',
                        'status',
                        'raw_payload',
                        'last_seen_at',
                        'updated_at',
                    ]
                );
            }

            $oltConnection->update([
                'last_polled_at' => $now,
                'last_poll_success' => true,
                'last_poll_message' => 'Polling SNMP berhasil. ONU terdeteksi: '.count($records),
            ]);

            return redirect()
                ->route('olt-connections.show', $oltConnection)
                ->with('status', 'Polling OLT HSGQ berhasil. ONU terdeteksi: '.count($records).'.');
        } catch (Throwable $exception) {
            $oltConnection->update([
                'last_polled_at' => now(),
                'last_poll_success' => false,
                'last_poll_message' => Str::limit($exception->getMessage(), 230),
            ]);

            return redirect()
                ->route('olt-connections.show', $oltConnection)
                ->with('error', 'Polling OLT gagal: '.$exception->getMessage());
        }
    }

    public function autoDetectOid(DetectOltOidRequest $request): JsonResponse
    {
        if (auth()->user()->role === 'teknisi') {
            abort(403);
        }

        try {
            $detected = $this->collector->detectMappingFromModel($request->validated());

            return response()->json([
                'status' => 'ok',
                'message' => 'Mapping OID berhasil dideteksi dari model OLT.',
                'data' => $detected,
            ]);
        } catch (Throwable $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function autoDetectModel(DetectOltModelRequest $request): JsonResponse
    {
        if (auth()->user()->role === 'teknisi') {
            abort(403);
        }

        try {
            $detected = $this->collector->detectModelFromSnmp($request->validated());

            return response()->json([
                'status' => 'ok',
                'message' => $detected['matched_model'] !== null
                    ? 'Model OLT berhasil dideteksi dari SNMP.'
                    : 'SNMP terhubung, namun model belum ada pada profil. Isi model dan OID manual.',
                'data' => $detected,
            ]);
        } catch (Throwable $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    private function authorizeAccess(OltConnection $oltConnection): void
    {
        $user = auth()->user();
        if ($user->isSuperAdmin()) {
            return;
        }

        if ((int) $oltConnection->owner_id !== (int) $user->effectiveOwnerId()) {
            abort(403);
        }
    }
}
