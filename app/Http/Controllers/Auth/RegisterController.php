<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Models\User;
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
        // default role mitra on self-registration
        $data['role'] = 'mitra';
        $data['password'] = bcrypt($data['password']);

        $user = User::create($data);
        Auth::login($user);

        return redirect()->route('dashboard')->with('status', 'Akun berhasil dibuat.');
    }
}
