<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        // Super admins can always access
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Check if user can access the app
        if (!$user->canAccessApp()) {
            return redirect()->route('subscription.expired')
                ->with('error', 'Langganan Anda telah berakhir. Silakan perpanjang untuk melanjutkan.');
        }

        return $next($request);
    }
}
