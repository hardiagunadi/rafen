<?php

namespace App\Http\Middleware;

use App\Models\PortalSession;
use App\Models\TenantSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PortalAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->cookie('portal_session');

        if (! $token) {
            return $this->redirectToLogin($request);
        }

        $session = PortalSession::with('pppUser')
            ->where('token', $token)
            ->where('expires_at', '>', now())
            ->first();

        if (! $session || ! $session->pppUser) {
            return $this->redirectToLogin($request);
        }

        // Pastikan session ini milik tenant yang sesuai dengan slug di URL
        $portalSlug = $request->route('portalSlug');
        if ($portalSlug) {
            $settings = TenantSettings::where('portal_slug', $portalSlug)->first();
            if (! $settings || $settings->user_id !== $session->pppUser->owner_id) {
                return $this->redirectToLogin($request);
            }
        }

        $session->update(['last_activity_at' => now()]);

        $request->attributes->set('portal_ppp_user', $session->pppUser);
        $request->attributes->set('portal_session', $session);

        return $next($request);
    }

    private function redirectToLogin(Request $request): Response
    {
        $slug = $request->route('portalSlug');

        if ($slug) {
            return redirect()->route('portal.login', $slug);
        }

        return redirect()->route('portal.legacy.login');
    }
}
