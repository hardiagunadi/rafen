<?php

namespace App\Http\Middleware;

use App\Models\PortalSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PortalAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->cookie('portal_session');

        if (! $token) {
            return redirect()->route('portal.login');
        }

        $session = PortalSession::with('pppUser')
            ->where('token', $token)
            ->where('expires_at', '>', now())
            ->first();

        if (! $session || ! $session->pppUser) {
            return redirect()->route('portal.login');
        }

        $session->update(['last_activity_at' => now()]);

        $request->attributes->set('portal_ppp_user', $session->pppUser);
        $request->attributes->set('portal_session', $session);

        return $next($request);
    }
}
