@extends('layouts.admin')

@section('title', 'Kelola Paket Langganan')

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Daftar Paket Langganan</h3>
        <div class="card-tools">
            <a href="{{ route('super-admin.subscription-plans.create') }}" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Tambah Paket
            </a>
        </div>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Harga</th>
                    <th>Durasi</th>
                    <th>Max Mikrotik</th>
                    <th>Max PPP Users</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($plans as $plan)
                <tr>
                    <td>
                        {{ $plan->name }}
                        @if($plan->is_featured)
                            <span class="badge badge-warning">Featured</span>
                        @endif
                    </td>
                    <td>Rp {{ number_format($plan->price, 0, ',', '.') }}</td>
                    <td>{{ $plan->duration_days }} hari</td>
                    <td>{{ $plan->max_mikrotik == -1 ? 'Unlimited' : $plan->max_mikrotik }}</td>
                    <td>{{ $plan->max_ppp_users == -1 ? 'Unlimited' : $plan->max_ppp_users }}</td>
                    <td>
                        @if($plan->is_active)
                            <span class="badge badge-success">Aktif</span>
                        @else
                            <span class="badge badge-secondary">Nonaktif</span>
                        @endif
                    </td>
                    <td class="text-right">
                        <div class="btn-group btn-group-sm">
                            <form action="{{ route('super-admin.subscription-plans.toggle-active', $plan) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-{{ $plan->is_active ? 'warning' : 'success' }}">
                                    <i class="fas fa-{{ $plan->is_active ? 'pause' : 'play' }}"></i>
                                </button>
                            </form>
                            <a href="{{ route('super-admin.subscription-plans.edit', $plan) }}" class="btn btn-info">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form action="{{ route('super-admin.subscription-plans.destroy', $plan) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus paket ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center text-muted">Belum ada paket langganan</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
