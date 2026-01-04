@extends('layouts.admin')

@section('title', 'Manajemen PPPoE & Hotspot')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0">Akun RADIUS</h3>
            <a href="{{ route('radius-accounts.create') }}" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Tambah Akun
            </a>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead>
                <tr>
                    <th>Username</th>
                    <th>Layanan</th>
                    <th>IP PPPoE</th>
                    <th>Rate Limit</th>
                    <th>Mikrotik</th>
                    <th>Status</th>
                    <th class="text-right">Aksi</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($accounts as $account)
                    <tr>
                        <td>{{ $account->username }}</td>
                        <td>{{ strtoupper($account->service) }}</td>
                        <td>{{ $account->service === 'pppoe' ? $account->ipv4_address ?? '-' : '-' }}</td>
                        <td>{{ $account->rate_limit ?? '-' }}</td>
                        <td>{{ $account->mikrotikConnection?->name ?? '-' }}</td>
                        <td>{!! $account->is_active ? '<span class="badge badge-success">Aktif</span>' : '<span class="badge badge-secondary">Non-aktif</span>' !!}</td>
                        <td class="text-right">
                            <a href="{{ route('radius-accounts.edit', $account) }}" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form action="{{ route('radius-accounts.destroy', $account) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus akun ini?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center p-4">Belum ada akun.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($accounts->hasPages())
            <div class="card-footer">
                {{ $accounts->links() }}
            </div>
        @endif
    </div>
@endsection
