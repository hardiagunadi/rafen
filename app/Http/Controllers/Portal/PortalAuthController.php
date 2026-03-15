<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\PortalSession;
use App\Models\PppUser;
use App\Models\TenantSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PortalAuthController extends Controller
{
    /**
     * Resolve TenantSettings by portal slug. Aborts 404 if not found.
     */
    private function resolveTenant(string $slug): TenantSettings
    {
        $settings = TenantSettings::where('portal_slug', $slug)->first();

        abort_unless($settings, 404, 'Portal tidak ditemukan.');

        return $settings;
    }

    public function showLogin(string $portalSlug)
    {
        $tenantSettings = $this->resolveTenant($portalSlug);

        return view('portal.login', compact('tenantSettings', 'portalSlug'));
    }

    public function login(Request $request, string $portalSlug)
    {
        $tenantSettings = $this->resolveTenant($portalSlug);

        $request->validate([
            'nomor_hp' => ['required', 'string'],
            'password'  => ['required', 'string'],
        ]);

        $phone   = $this->normalizePhone($request->nomor_hp);
        $ownerId = $tenantSettings->user_id;

        $pppUsers = PppUser::where('nomor_hp', $phone)
            ->where('owner_id', $ownerId)
            ->whereNotNull('password_clientarea')
            ->get();

        if ($pppUsers->isEmpty()) {
            return back()->withErrors(['nomor_hp' => 'Nomor HP tidak ditemukan atau password belum diatur oleh admin.'])->withInput();
        }

        // Find the user whose password matches
        $matchedUser = null;
        foreach ($pppUsers as $pppUser) {
            $storedPassword = $pppUser->password_clientarea;

            $matched = false;
            try {
                $matched = Hash::check($request->password, $storedPassword);
            } catch (\Throwable) {
            }
            if (! $matched) {
                $matched = $storedPassword === $request->password;
            }
            if ($matched) {
                $matchedUser = $pppUser;
                break;
            }
        }

        if (! $matchedUser) {
            return back()->withErrors(['password' => 'Password salah.'])->withInput();
        }

        // Create portal session
        $token = Str::random(64);
        PortalSession::create([
            'ppp_user_id'      => $matchedUser->id,
            'token'            => $token,
            'ip_address'       => $request->ip(),
            'user_agent'       => mb_substr($request->userAgent() ?? '', 0, 255),
            'last_activity_at' => now(),
            'expires_at'       => now()->addDays(7),
        ]);

        $cookie = Cookie::make('portal_session', $token, 60 * 24 * 7, '/', null, false, true);

        return redirect()->route('portal.dashboard', $portalSlug)->withCookie($cookie);
    }

    public function logout(Request $request, string $portalSlug)
    {
        $token = $request->cookies->get('portal_session');
        if ($token) {
            PortalSession::where('token', $token)->delete();
        }

        $cookie = Cookie::forget('portal_session');

        return redirect()->route('portal.login', $portalSlug)->withCookie($cookie);
    }

    /**
     * Legacy /portal/login — tanpa slug.
     * Tampilkan daftar semua tenant yang punya slug, atau redirect jika hanya satu.
     */
    public function showLoginLegacy()
    {
        $tenants = TenantSettings::whereNotNull('portal_slug')
            ->where('portal_slug', '!=', '')
            ->get(['portal_slug', 'business_name', 'business_logo']);

        if ($tenants->count() === 1) {
            return redirect()->route('portal.login', $tenants->first()->portal_slug);
        }

        return view('portal.login-legacy', compact('tenants'));
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? $phone;

        if (str_starts_with($digits, '0')) {
            $digits = '62' . substr($digits, 1);
        } elseif (! str_starts_with($digits, '62')) {
            $digits = '62' . $digits;
        }

        return $digits;
    }
}
