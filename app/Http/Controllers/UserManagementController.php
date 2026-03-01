<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function index(): View
    {
        return view('users.index');
    }

    public function datatable(Request $request): JsonResponse
    {
        $search = $request->input('search.value', '');

        $query = User::query()
            ->when($search !== '', fn($q) => $q->where(function ($q2) use ($search) {
                $q2->where('name', 'like', "%{$search}%")
                   ->orWhere('email', 'like', "%{$search}%");
            }))
            ->latest();

        $total    = User::count();
        $filtered = $query->count();
        $rows     = $query->offset($request->integer('start'))
            ->limit(max(1, $request->integer('length', 20)))
            ->get();

        return response()->json([
            'draw'            => $request->integer('draw'),
            'recordsTotal'    => $total,
            'recordsFiltered' => $filtered,
            'data'            => $rows->map(fn($r) => [
                'id'            => $r->id,
                'name'          => $r->name,
                'email'         => $r->email,
                'role'          => strtoupper(str_replace('_', ' ', $r->role ?? '-')),
                'last_login_at' => $r->last_login_at?->format('Y-m-d H:i:s') ?? '-',
                'edit_url'      => route('users.edit', $r->id),
                'destroy_url'   => route('users.destroy', $r->id),
            ]),
        ]);
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

    public function destroy(User $user): JsonResponse|RedirectResponse
    {
        $user->delete();

        if (request()->wantsJson()) {
            return response()->json(['status' => 'Pengguna dihapus.']);
        }

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
