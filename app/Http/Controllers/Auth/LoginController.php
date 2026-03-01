<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\LoginLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(): View
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->validated();

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            $user = Auth::user();
            optional($user)->forceFill(['last_login_at' => now()])->save();

            LoginLog::create([
                'user_id'    => $user?->id,
                'email'      => $credentials['email'],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'event'      => 'login',
            ]);

            return redirect()->intended(route('dashboard'))->with('status', 'Berhasil login.');
        }

        LoginLog::create([
            'user_id'    => null,
            'email'      => $credentials['email'],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'event'      => 'failed',
        ]);

        return back()->withErrors([
            'email' => 'Kredensial tidak valid.',
        ])->onlyInput('email');
    }

    public function logout(Request $request): RedirectResponse
    {
        $user = Auth::user();

        LoginLog::create([
            'user_id'    => $user?->id,
            'email'      => $user?->email,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'event'      => 'logout',
        ]);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Berhasil logout.');
    }
}
