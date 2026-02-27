<?php

namespace App\Http\Controllers;

use App\Models\MikrotikConnection;
use App\Models\RadiusAccount;
use App\Services\ActiveSessionFetcher;
use App\Services\MikrotikApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        $sessions = RadiusAccount::query()
            ->where('service', 'pppoe')
            ->where('is_active', true)
            ->with('mikrotikConnection')
            ->accessibleBy($user)
            ->when($request->router_id, fn ($q) => $q->where('mikrotik_connection_id', $request->router_id))
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
}
