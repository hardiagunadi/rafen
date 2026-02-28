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

        $sessions = RadiusAccount::query()
            ->where('service', 'pppoe')
            ->where('is_active', true)
            ->with('mikrotikConnection')
            ->accessibleBy($user)
            ->when($request->router_id, fn ($q) => $q->where('mikrotik_connection_id', $request->router_id))
            ->addSelect([
                'radius_accounts.*',
                DB::raw('(SELECT acctinputoctets FROM radacct WHERE radacct.username = radius_accounts.username AND radacct.acctstoptime IS NULL ORDER BY acctstarttime DESC LIMIT 1) as bytes_in'),
                DB::raw('(SELECT acctoutputoctets FROM radacct WHERE radacct.username = radius_accounts.username AND radacct.acctstoptime IS NULL ORDER BY acctstarttime DESC LIMIT 1) as bytes_out'),
            ])
            ->orderByDesc('updated_at')
            ->paginate(50)
            ->withQueryString();

        $total = RadiusAccount::query()
            ->where('service', 'pppoe')
            ->where('is_active', true)
            ->accessibleBy($user)
            ->count();

        return view('sessions.pppoe', compact('sessions', 'routers', 'total'));
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

        $sessions = RadiusAccount::query()
            ->where('service', 'hotspot')
            ->where('is_active', true)
            ->with('mikrotikConnection')
            ->accessibleBy($user)
            ->when($request->router_id, fn ($q) => $q->where('mikrotik_connection_id', $request->router_id))
            ->orderByDesc('updated_at')
            ->paginate(50)
            ->withQueryString();

        $total = RadiusAccount::query()
            ->where('service', 'hotspot')
            ->where('is_active', true)
            ->accessibleBy($user)
            ->count();

        return view('sessions.hotspot', compact('sessions', 'routers', 'total'));
    }

    public function refreshRouter(Request $request, MikrotikConnection $connection): JsonResponse
    {
        $user = auth()->user();

        if (! $user->is_super_admin && $connection->owner_id !== $user->id) {
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
