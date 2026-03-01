<?php

namespace App\Http\Controllers;

use App\Models\MikrotikConnection;
use App\Models\RadiusAccount;
use App\Services\ActiveSessionFetcher;
use App\Services\MikrotikApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class ActiveSessionController extends Controller
{
    public function pppoe(Request $request): View
    {
        $user = auth()->user();

        $routers = MikrotikConnection::query()
            ->accessibleBy($user)
            ->orderBy('name')
            ->get();

        // Auto-sync from all accessible routers on page load
        foreach ($routers as $router) {
            try {
                (new ActiveSessionFetcher(new MikrotikApiClient($router)))->syncPpp($router);
            } catch (RuntimeException) {
                // silently skip unreachable routers
            }
        }

        $total = RadiusAccount::query()
            ->where('service', 'pppoe')
            ->where('is_active', true)
            ->accessibleBy($user)
            ->count();

        return view('sessions.pppoe', compact('routers', 'total'));
    }

    public function hotspot(Request $request): View
    {
        $user = auth()->user();

        $routers = MikrotikConnection::query()
            ->accessibleBy($user)
            ->orderBy('name')
            ->get();

        // Auto-sync from all accessible routers on page load
        foreach ($routers as $router) {
            try {
                (new ActiveSessionFetcher(new MikrotikApiClient($router)))->syncHotspot($router);
            } catch (RuntimeException) {
                // silently skip unreachable routers
            }
        }

        $total = RadiusAccount::query()
            ->where('service', 'hotspot')
            ->where('is_active', true)
            ->accessibleBy($user)
            ->count();

        return view('sessions.hotspot', compact('routers', 'total'));
    }

    public function pppoeDatatable(Request $request): JsonResponse
    {
        $user   = auth()->user();
        $search = $request->input('search.value', '');

        $query = RadiusAccount::query()
            ->where('service', 'pppoe')
            ->where('is_active', true)
            ->with('mikrotikConnection')
            ->accessibleBy($user)
            ->when($request->filled('router_id'), fn($q) => $q->where('mikrotik_connection_id', $request->router_id))
            ->addSelect([
                'radius_accounts.*',
                DB::raw('(SELECT acctinputoctets FROM radacct WHERE radacct.username = radius_accounts.username AND radacct.acctstoptime IS NULL ORDER BY acctstarttime DESC LIMIT 1) as bytes_in'),
                DB::raw('(SELECT acctoutputoctets FROM radacct WHERE radacct.username = radius_accounts.username AND radacct.acctstoptime IS NULL ORDER BY acctstarttime DESC LIMIT 1) as bytes_out'),
            ])
            ->when($search !== '', fn($q) => $q->where(function ($q2) use ($search) {
                $q2->where('radius_accounts.username', 'like', "%{$search}%")
                   ->orWhere('radius_accounts.ipv4_address', 'like', "%{$search}%")
                   ->orWhere('radius_accounts.caller_id', 'like', "%{$search}%");
            }))
            ->orderByDesc('radius_accounts.updated_at');

        $total    = RadiusAccount::where('service', 'pppoe')->where('is_active', true)->accessibleBy($user)->count();
        $filtered = $query->count();
        $rows     = $query->offset($request->integer('start'))
            ->limit(max(1, $request->integer('length', 20)))
            ->get();

        return response()->json([
            'draw'            => $request->integer('draw'),
            'recordsTotal'    => $total,
            'recordsFiltered' => $filtered,
            'data'            => $rows->map(fn($r) => [
                'id'         => $r->id,
                'username'   => $r->username,
                'ipv4'       => $r->ipv4_address ?? '-',
                'uptime'     => $r->uptime ?? '-',
                'caller_id'  => $r->caller_id ?? '-',
                'bytes_in'   => $r->bytes_in ? number_format($r->bytes_in / 1073741824, 2).' GB' : '-',
                'bytes_out'  => $r->bytes_out ? number_format($r->bytes_out / 1073741824, 2).' GB' : '-',
                'profile'    => $r->profile ?? '-',
                'router'     => $r->mikrotikConnection?->name ?? '-',
                'updated_at' => $r->updated_at?->diffForHumans() ?? '-',
            ]),
        ]);
    }

    public function hotspotDatatable(Request $request): JsonResponse
    {
        $user   = auth()->user();
        $search = $request->input('search.value', '');

        $query = RadiusAccount::query()
            ->where('service', 'hotspot')
            ->where('is_active', true)
            ->with('mikrotikConnection')
            ->accessibleBy($user)
            ->when($request->filled('router_id'), fn($q) => $q->where('mikrotik_connection_id', $request->router_id))
            ->when($search !== '', fn($q) => $q->where(function ($q2) use ($search) {
                $q2->where('username', 'like', "%{$search}%")
                   ->orWhere('ipv4_address', 'like', "%{$search}%")
                   ->orWhere('caller_id', 'like', "%{$search}%");
            }))
            ->orderByDesc('updated_at');

        $total    = RadiusAccount::where('service', 'hotspot')->where('is_active', true)->accessibleBy($user)->count();
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
                'username'    => $r->username,
                'ipv4'        => $r->ipv4_address ?? '-',
                'caller_id'   => $r->caller_id ?? '-',
                'uptime'      => $r->uptime ?? '-',
                'bytes_in'    => $r->bytes_in ? number_format($r->bytes_in / 1073741824, 2).' GB' : '-',
                'bytes_out'   => $r->bytes_out ? number_format($r->bytes_out / 1073741824, 2).' GB' : '-',
                'server_name' => $r->server_name ?? '-',
                'router'      => $r->mikrotikConnection?->name ?? '-',
                'updated_at'  => $r->updated_at?->diffForHumans() ?? '-',
            ]),
        ]);
    }

    public function refreshRouter(Request $request, MikrotikConnection $connection): JsonResponse
    {
        $user = auth()->user();

        if (! $user->isSuperAdmin() && $connection->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        $service = $request->input('service', 'pppoe');

        try {
            $fetcher = new ActiveSessionFetcher(new MikrotikApiClient($connection));
            $count = $service === 'hotspot'
                ? $fetcher->syncHotspot($connection)
                : $fetcher->syncPpp($connection);

            return response()->json([
                'success' => true,
                'synced'  => $count,
                'router'  => $connection->name,
                'message' => "{$count} sesi aktif ditemukan di {$connection->name}",
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function refreshAll(): JsonResponse
    {
        $user = auth()->user();

        $routers = MikrotikConnection::query()
            ->accessibleBy($user)
            ->get();

        $pppTotal = 0;
        $hotspotTotal = 0;
        $errors = [];

        foreach ($routers as $router) {
            $fetcher = new ActiveSessionFetcher(new MikrotikApiClient($router));
            try {
                $pppTotal += $fetcher->syncPpp($router);
            } catch (RuntimeException $e) {
                $errors[] = $router->name.': '.$e->getMessage();
            }
            try {
                $hotspotTotal += $fetcher->syncHotspot($router);
            } catch (RuntimeException $e) {
                $errors[] = $router->name.': '.$e->getMessage();
            }
        }

        return response()->json([
            'success'       => true,
            'ppp_online'    => $pppTotal,
            'hotspot_online' => $hotspotTotal,
            'errors'        => $errors,
            'message'       => "PPPoE: {$pppTotal}, Hotspot: {$hotspotTotal} sesi aktif",
        ]);
    }
}
