@extends('layouts.admin')

@section('title', 'Manajemen Pengguna')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0">Manajemen Pengguna</h4>
                <small class="text-muted">Level: Administrator, IT Support, NOC, Keuangan, Mitra</small>
            </div>
            <a href="{{ route('users.create') }}" class="btn btn-primary btn-sm">Tambah Pengguna</a>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead>
                <tr>
                    <th>Nama</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Terakhir Login</th>
                    <th class="text-right">Aksi</th>
                </tr>
                </thead>
                <tbody>
                @forelse($users as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ strtoupper(str_replace('_', ' ', $user->role)) }}</td>
                        <td>{{ $user->last_login_at?->format('Y-m-d H:i:s') ?? '-' }}</td>
                        <td class="text-right">
                            <a href="{{ route('users.edit', $user) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                            <form action="{{ route('users.destroy', $user) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus pengguna ini?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center p-4">Belum ada pengguna.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($users->hasPages())
            <div class="card-footer">
                {{ $users->links() }}
            </div>
        @endif
    </div>
@endsection
