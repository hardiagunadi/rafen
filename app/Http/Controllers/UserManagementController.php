<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function index(): View
    {
        $users = User::query()->latest()->paginate(10);

        return view('users.index', compact('users'));
    }

    public function create(): View
    {
        $roles = $this->roles();

        return view('users.create', compact('roles'));
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['password'] = bcrypt($data['password']);

        User::create($data);

        return redirect()->route('users.index')->with('status', 'Pengguna dibuat.');
    }

    public function edit(User $user): View
    {
        $roles = $this->roles();

        return view('users.edit', compact('user', 'roles'));
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        if (! empty($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return redirect()->route('users.index')->with('status', 'Pengguna diperbarui.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $user->delete();

        return redirect()->route('users.index')->with('status', 'Pengguna dihapus.');
    }

    private function roles(): array
    {
        return [
            'administrator' => 'Administrator',
            'it_support' => 'IT Support',
            'noc' => 'NOC',
            'keuangan' => 'Keuangan',
            'mitra' => 'Mitra',
        ];
    }
}
