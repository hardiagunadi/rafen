<?php

namespace App\Http\Middleware;

use App\Models\MikrotikConnection;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class RedirectIsolatedCaptivePortal
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldSkip($request)) {
            return $next($request);
        }

        if (! $this->hasMikrotikConnectionsTable()) {
            return $next($request);
        }

        $clientIp = (string) $request->ip();
        if ($clientIp === '') {
            return $next($request);
        }

        $ownerId = (int) Cache::remember("isolir:nas-owner:{$clientIp}", now()->addMinute(), function () use ($clientIp): int {
            return (int) (MikrotikConnection::query()
                ->where('is_active', true)
                ->where('host', $clientIp)
                ->orderByDesc('is_online')
                ->value('owner_id') ?? 0);
        });

        if ($ownerId <= 0) {
            return $next($request);
        }

        if (! $this->isCaptiveRequest($request)) {
            return $next($request);
        }

        return redirect()->route('isolir.show', ['userId' => $ownerId]);
    }

    private function hasMikrotikConnectionsTable(): bool
    {
        try {
            return Schema::hasTable('mikrotik_connections');
        } catch (\Throwable) {
            return false;
        }
    }

    private function shouldSkip(Request $request): bool
    {
        if ($request->isMethod('POST') || $request->isMethod('PUT') || $request->isMethod('PATCH') || $request->isMethod('DELETE')) {
            return true;
        }

        if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
            return true;
        }

        if ($request->user()) {
            return true;
        }

        if ($request->is('isolir/*')) {
            return true;
        }

        if ($request->is('webhook') || $request->is('webhook/*') || $request->is('webhook/wa') || $request->is('webhook/wa/*')) {
            return true;
        }

        if ($request->is('payment/callback') || $request->is('payment/callback/*') || $request->is('subscription/payment/callback')) {
            return true;
        }

        if ($request->is('bayar/*')) {
            return true;
        }

        if ($request->is('up')) {
            return true;
        }

        return false;
    }

    private function isCaptiveRequest(Request $request): bool
    {
        $path = ltrim($request->path(), '/');
        $host = strtolower((string) $request->getHost());
        $appHost = strtolower((string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: ''));

        $captivePaths = [
            'generate_204',
            'gen_204',
            'connecttest.txt',
            'ncsi.txt',
            'hotspot-detect.html',
            'getDNList',
            'getHttpDnsServerList',
            'chat',
            'route/mac/v1',
        ];

        if (in_array($path, $captivePaths, true)) {
            return true;
        }

        if ($appHost === '') {
            return false;
        }

        return $host !== $appHost;
    }
}
