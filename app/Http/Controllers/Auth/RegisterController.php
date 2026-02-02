<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Models\User;
use App\Models\TenantSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function show(): View
    {
        $roles = [
            'mitra' => 'Mitra',
        ];

        return view('auth.register', compact('roles'));
    }

    public function register(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // Create user as tenant with trial period
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
            'role' => 'administrator',
            'is_super_admin' => false,
            'subscription_status' => 'trial',
            'trial_days_remaining' => 14,
            'registered_at' => now(),
            'phone' => $data['phone'] ?? null,
            'company_name' => $data['company_name'] ?? null,
        ]);

        // Create default tenant settings
        TenantSettings::create([
            'user_id' => $user->id,
            'invoice_prefix' => 'INV',
            'enable_manual_payment' => true,
            'payment_expiry_hours' => 24,
            'auto_isolate_unpaid' => true,
            'grace_period_days' => 3,
        ]);

        Auth::login($user);

        return redirect()->route('dashboard')->with('status', 'Selamat datang! Anda memiliki 14 hari masa percobaan gratis.');
    }
}
