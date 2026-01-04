<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePppUserRequest;
use App\Http\Requests\UpdatePppUserRequest;
use App\Models\PppProfile;
use App\Models\PppUser;
use App\Models\ProfileGroup;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PppUserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $users = PppUser::query()->with(['owner', 'profileGroup'])->latest()->paginate(10);

        return view('ppp_users.index', compact('users'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('ppp_users.create', [
            'owners' => User::query()->orderBy('name')->get(),
            'groups' => ProfileGroup::query()->orderBy('name')->get(),
            'profiles' => PppProfile::query()->orderBy('name')->get(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePppUserRequest $request): RedirectResponse
    {
        $data = $this->prepareData($request->validated());

        PppUser::create($data);

        return redirect()->route('ppp-users.index')->with('status', 'User PPP ditambahkan.');
    }

    /**
     * Display the specified resource.
     */
    public function show(PppUser $pppUser): RedirectResponse
    {
        return redirect()->route('ppp-users.edit', $pppUser);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PppUser $pppUser): View
    {
        return view('ppp_users.edit', [
            'pppUser' => $pppUser,
            'owners' => User::query()->orderBy('name')->get(),
            'groups' => ProfileGroup::query()->orderBy('name')->get(),
            'profiles' => PppProfile::query()->orderBy('name')->get(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePppUserRequest $request, PppUser $pppUser): RedirectResponse
    {
        $data = $this->prepareData($request->validated());

        $pppUser->update($data);

        return redirect()->route('ppp-users.index')->with('status', 'User PPP diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PppUser $pppUser): RedirectResponse
    {
        $pppUser->delete();

        return redirect()->route('ppp-users.index')->with('status', 'User PPP dihapus.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $ids = $request->input('ids', []);
        if (! empty($ids)) {
            PppUser::query()->whereIn('id', $ids)->delete();
        }

        return redirect()->route('ppp-users.index')->with('status', 'User PPP terpilih dihapus.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepareData(array $data): array
    {
        if (($data['tipe_ip'] ?? '') !== 'static') {
            $data['profile_group_id'] = null;
            $data['ip_static'] = null;
        }

        if (! empty($data['nomor_hp'])) {
            $data['nomor_hp'] = $this->normalizePhone($data['nomor_hp']);
        }

        if (($data['metode_login'] ?? '') === 'username_equals_password') {
            $data['ppp_password'] = $data['username'] ?? $data['ppp_password'] ?? null;
            $data['password_clientarea'] = $data['password_clientarea'] ?? $data['username'] ?? null;
        }

        return $data;
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($phone, '0')) {
            $phone = '62'.substr($phone, 1);
        } elseif (! str_starts_with($phone, '62')) {
            $phone = '62'.$phone;
        }

        return $phone;
    }
}
