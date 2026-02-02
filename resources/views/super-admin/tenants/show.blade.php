@extends('layouts.admin')

@section('title', 'Detail Tenant: ' . $tenant->name)

@section('content')
<div class="row">
    <div class="col-md-4">
        <!-- Profile Card -->
        <div class="card card-primary card-outline">
            <div class="card-body box-profile">
                <h3 class="profile-username text-center">{{ $tenant->name }}</h3>
                <p class="text-muted text-center">{{ $tenant->company_name ?? 'Individual' }}</p>

                <ul class="list-group list-group-unbordered mb-3">
                    <li class="list-group-item">
                        <b>Email</b> <a class="float-right">{{ $tenant->email }}</a>
                    </li>
                    <li class="list-group-item">
                        <b>Telepon</b> <a class="float-right">{{ $tenant->phone ?? '-' }}</a>
                    </li>
                    <li class="list-group-item">
                        <b>Status</b>
                        <span class="float-right">
                            @switch($tenant->subscription_status)
                                @case('trial')
                                    <span class="badge badge-warning">Trial</span>
                                    @break
                                @case('active')
                                    <span class="badge badge-success">Aktif</span>
                                    @break
                                @case('expired')
                                    <span class="badge badge-secondary">Berakhir</span>
                                    @break
                                @case('suspended')
                                    <span class="badge badge-danger">Suspend</span>
                                    @break
                            @endswitch
                        </span>
                    </li>
                    <li class="list-group-item">
                        <b>Paket</b> <a class="float-right">{{ $tenant->subscriptionPlan->name ?? '-' }}</a>
                    </li>
                    <li class="list-group-item">
                        <b>Berakhir</b>
                        <a class="float-right">
                            {{ $tenant->subscription_expires_at ? $tenant->subscription_expires_at->format('d M Y') : '-' }}
                        </a>
                    </li>
                    <li class="list-group-item">
                        <b>Terdaftar</b>
                        <a class="float-right">{{ $tenant->created_at->format('d M Y') }}</a>
                    </li>
                </ul>

                <div class="btn-group btn-group-sm d-flex">
                    <a href="{{ route('super-admin.tenants.edit', $tenant) }}" class="btn btn-warning">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                    <a href="{{ route('super-admin.tenants.vpn', $tenant) }}" class="btn btn-info">
                        <i class="fas fa-network-wired"></i> VPN
                    </a>
                </div>
            </div>
        </div>

        <!-- Stats Card -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Statistik</h3>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Mikrotik</span>
                        <strong>{{ $stats['mikrotik_count'] }}</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>PPP Users</span>
                        <strong>{{ $stats['ppp_users_count'] }}</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>PPP Aktif</span>
                        <strong>{{ $stats['active_ppp_users'] }}</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Invoice</span>
                        <strong>{{ $stats['invoices_count'] }}</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Belum Bayar</span>
                        <strong class="text-danger">{{ $stats['unpaid_invoices'] }}</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Total Pendapatan</span>
                        <strong class="text-success">Rp {{ number_format($stats['total_revenue'], 0, ',', '.') }}</strong>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Aksi Cepat</h3>
            </div>
            <div class="card-body">
                @if($tenant->subscription_status !== 'active')
                <button type="button" class="btn btn-success btn-block mb-2" data-toggle="modal" data-target="#activateModal">
                    <i class="fas fa-check"></i> Aktifkan Langganan
                </button>
                @endif

                @if($tenant->subscription_status === 'active')
                <button type="button" class="btn btn-info btn-block mb-2" data-toggle="modal" data-target="#extendModal">
                    <i class="fas fa-plus"></i> Perpanjang
                </button>
                @endif

                @if($tenant->subscription_status !== 'suspended')
                <form action="{{ route('super-admin.tenants.suspend', $tenant) }}" method="POST" onsubmit="return confirm('Suspend tenant ini?')">
                    @csrf
                    <button type="submit" class="btn btn-warning btn-block mb-2">
                        <i class="fas fa-pause"></i> Suspend
                    </button>
                </form>
                @endif

                <form action="{{ route('super-admin.tenants.delete', $tenant) }}" method="POST" onsubmit="return confirm('HAPUS tenant ini? Semua data akan hilang!')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-block">
                        <i class="fas fa-trash"></i> Hapus Tenant
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <!-- Subscription History -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Riwayat Langganan</h3>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Paket</th>
                            <th>Mulai</th>
                            <th>Berakhir</th>
                            <th>Status</th>
                            <th>Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($tenant->subscriptions as $sub)
                        <tr>
                            <td>{{ $sub->plan->name ?? '-' }}</td>
                            <td>{{ $sub->start_date->format('d M Y') }}</td>
                            <td>{{ $sub->end_date->format('d M Y') }}</td>
                            <td>
                                <span class="badge badge-{{ $sub->status === 'active' ? 'success' : 'secondary' }}">
                                    {{ $sub->status }}
                                </span>
                            </td>
                            <td>Rp {{ number_format($sub->amount_paid, 0, ',', '.') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted">Tidak ada data</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Mikrotik Connections -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Mikrotik Connections</h3>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Host</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($tenant->mikrotikConnections as $mt)
                        <tr>
                            <td>{{ $mt->name }}</td>
                            <td>{{ $mt->host }}</td>
                            <td>
                                @if($mt->is_online)
                                    <span class="badge badge-success">Online</span>
                                @else
                                    <span class="badge badge-danger">Offline</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" class="text-center text-muted">Tidak ada Mikrotik</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- VPN Info -->
        @if($tenant->vpn_enabled)
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Informasi VPN</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <strong>Username:</strong><br>
                        <code>{{ $tenant->vpn_username }}</code>
                    </div>
                    <div class="col-md-4">
                        <strong>Password:</strong><br>
                        <code>{{ $tenant->vpn_password }}</code>
                    </div>
                    <div class="col-md-4">
                        <strong>IP Address:</strong><br>
                        <code>{{ $tenant->vpn_ip }}</code>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>

<!-- Activate Modal -->
<div class="modal fade" id="activateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('super-admin.tenants.activate', $tenant) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Aktifkan Langganan</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Pilih Paket</label>
                        <select name="plan_id" class="form-control" required>
                            @foreach(\App\Models\SubscriptionPlan::active()->get() as $plan)
                            <option value="{{ $plan->id }}">{{ $plan->name }} - Rp {{ number_format($plan->price, 0, ',', '.') }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Durasi (hari)</label>
                        <input type="number" name="duration_days" class="form-control" placeholder="Kosongkan untuk default paket">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success">Aktifkan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Extend Modal -->
<div class="modal fade" id="extendModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('super-admin.tenants.extend', $tenant) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Perpanjang Langganan</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Tambah Hari</label>
                        <input type="number" name="days" class="form-control" value="30" min="1" max="365" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-info">Perpanjang</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
