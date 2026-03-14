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
    public function showLogin()
    {
        return view('portal.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'nomor_hp' => ['required', 'string'],
            'password' => ['required', 'string'],
            'owner_id' => ['nullable', 'integer'],
        ]);

        $phone = $this->normalizePhone($request->nomor_hp);
        $password = $request->password;

        // Find matching PppUsers
        $query = PppUser::where('nomor_hp', $phone)
            ->whereNotNull('password_clientarea');

        if ($request->filled('owner_id')) {
            $query->where('owner_id', $request->input('owner_id'));
        }

        $pppUsers = $query->with('owner.tenantSettings')->get();

        if ($pppUsers->isEmpty()) {
            return back()->withErrors(['nomor_hp' => 'Nomor HP tidak ditemukan atau password belum diatur oleh admin.'])->withInput();
        }

        // If multiple tenants have this phone and no tenant selected yet, show picker
        $uniqueOwnerIds = $pppUsers->pluck('owner_id')->unique();
        if ($uniqueOwnerIds->count() > 1 && ! $request->filled('owner_id')) {
            $tenants = $pppUsers->map(function ($u) {
                $businessName = $u->owner?->tenantSettings?->business_name ?? $u->owner?->name ?? 'ISP #'.$u->owner_id;

                return ['owner_id' => $u->owner_id, 'business_name' => $businessName];
            })->unique('owner_id')->values();

            return view('portal.login', [
                'showTenantPicker' => true,
                'tenants' => $tenants,
                'nomor_hp' => $request->nomor_hp,
            ]);
        }

        // Find the user whose password matches
        $matchedUser = null;
        foreach ($pppUsers as $pppUser) {
            $storedPassword = $pppUser->password_clientarea;

            // Try hashed first (throws RuntimeException for plain text), then plain text
            $matched = false;
            try {
                $matched = Hash::check($password, $storedPassword);
            } catch (\Throwable) {
            }
            if (! $matched) {
                $matched = $storedPassword === $password;
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
            'ppp_user_id' => $matchedUser->id,
            'token' => $token,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr($request->userAgent() ?? '', 0, 255),
            'last_activity_at' => now(),
            'expires_at' => now()->addDays(7),
        ]);

        $cookie = Cookie::make('portal_session', $token, 60 * 24 * 7, '/', null, false, true);

        return redirect()->route('portal.dashboard')->withCookie($cookie);
    }

    public function logout(Request $request)
    {
        $token = $request->cookies->get('portal_session');
        if ($token) {
            PortalSession::where('token', $token)->delete();
        }

        $cookie = Cookie::forget('portal_session');

        return redirect()->route('portal.login')->withCookie($cookie);
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? $phone;

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        } elseif (! str_starts_with($digits, '62')) {
            $digits = '62'.$digits;
        }

        return $digits;
    }
}
