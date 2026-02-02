@extends('layouts.admin')

@section('title', 'Super Admin Dashboard')

@section('content')
<div class="row">
    <div class="col-lg-3 col-6">
        <div class="small-box bg-info">
            <div class="inner">
                <h3>{{ $stats['total_tenants'] }}</h3>
                <p>Total Tenant</p>
            </div>
            <div class="icon">
                <i class="fas fa-building"></i>
            </div>
            <a href="{{ route('super-admin.tenants') }}" class="small-box-footer">
                Lihat Detail <i class="fas fa-arrow-circle-right"></i>
            </a>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-success">
            <div class="inner">
                <h3>{{ $stats['active_subscribers'] }}</h3>
                <p>Subscriber Aktif</p>
            </div>
            <div class="icon">
                <i class="fas fa-user-check"></i>
            </div>
            <a href="{{ route('super-admin.tenants', ['status' => 'active']) }}" class="small-box-footer">
                Lihat Detail <i class="fas fa-arrow-circle-right"></i>
            </a>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-warning">
            <div class="inner">
                <h3>{{ $stats['trial_users'] }}</h3>
                <p>Masa Percobaan</p>
            </div>
            <div class="icon">
                <i class="fas fa-hourglass-half"></i>
            </div>
            <a href="{{ route('super-admin.tenants', ['status' => 'trial']) }}" class="small-box-footer">
                Lihat Detail <i class="fas fa-arrow-circle-right"></i>
            </a>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-danger">
            <div class="inner">
                <h3>{{ $stats['expired_subscribers'] }}</h3>
                <p>Berakhir</p>
            </div>
            <div class="icon">
                <i class="fas fa-user-times"></i>
            </div>
            <a href="{{ route('super-admin.tenants', ['status' => 'expired']) }}" class="small-box-footer">
                Lihat Detail <i class="fas fa-arrow-circle-right"></i>
            </a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-3 col-6">
        <div class="info-box">
            <span class="info-box-icon bg-primary"><i class="fas fa-server"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Total Mikrotik</span>
                <span class="info-box-number">{{ $stats['total_mikrotik'] }}</span>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="info-box">
            <span class="info-box-icon bg-secondary"><i class="fas fa-users"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Total PPP Users</span>
                <span class="info-box-number">{{ $stats['total_ppp_users'] }}</span>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="info-box">
            <span class="info-box-icon bg-success"><i class="fas fa-money-bill"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Total Revenue</span>
                <span class="info-box-number">Rp {{ number_format($stats['total_revenue'], 0, ',', '.') }}</span>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="info-box">
            <span class="info-box-icon bg-info"><i class="fas fa-calendar"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Revenue Bulan Ini</span>
                <span class="info-box-number">Rp {{ number_format($stats['monthly_revenue'], 0, ',', '.') }}</span>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Langganan Terbaru</h3>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Tenant</th>
                            <th>Paket</th>
                            <th>Status</th>
                            <th>Tanggal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentSubscriptions as $sub)
                        <tr>
                            <td>
                                <a href="{{ route('super-admin.tenants.show', $sub->user) }}">
                                    {{ $sub->user->name }}
                                </a>
                            </td>
                            <td>{{ $sub->plan->name ?? '-' }}</td>
                            <td>
                                @switch($sub->status)
                                    @case('active')
                                        <span class="badge badge-success">Aktif</span>
                                        @break
                                    @case('pending')
                                        <span class="badge badge-warning">Pending</span>
                                        @break
                                    @default
                                        <span class="badge badge-secondary">{{ $sub->status }}</span>
                                @endswitch
                            </td>
                            <td>{{ $sub->created_at->format('d M Y') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted">Tidak ada data</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Akan Berakhir (7 Hari)</h3>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Tenant</th>
                            <th>Berakhir</th>
                            <th>Sisa Hari</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($expiringSubscriptions as $tenant)
                        <tr>
                            <td>
                                <a href="{{ route('super-admin.tenants.show', $tenant) }}">
                                    {{ $tenant->name }}
                                </a>
                            </td>
                            <td>{{ $tenant->subscription_expires_at->format('d M Y') }}</td>
                            <td>
                                <span class="badge badge-{{ $tenant->getSubscriptionDaysRemaining() <= 3 ? 'danger' : 'warning' }}">
                                    {{ $tenant->getSubscriptionDaysRemaining() }} hari
                                </span>
                            </td>
                            <td>
                                <a href="{{ route('super-admin.tenants.show', $tenant) }}" class="btn btn-xs btn-info">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted">Tidak ada tenant yang akan berakhir</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Pembayaran Terbaru</h3>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>No. Pembayaran</th>
                            <th>Tenant</th>
                            <th>Paket</th>
                            <th>Metode</th>
                            <th>Jumlah</th>
                            <th>Status</th>
                            <th>Tanggal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentPayments as $payment)
                        <tr>
                            <td>{{ $payment->payment_number }}</td>
                            <td>{{ $payment->user->name ?? '-' }}</td>
                            <td>{{ $payment->subscription?->plan?->name ?? '-' }}</td>
                            <td>{{ $payment->payment_channel ?? '-' }}</td>
                            <td>Rp {{ number_format($payment->total_amount, 0, ',', '.') }}</td>
                            <td>
                                @switch($payment->status)
                                    @case('paid')
                                        <span class="badge badge-success">Dibayar</span>
                                        @break
                                    @case('pending')
                                        <span class="badge badge-warning">Pending</span>
                                        @break
                                    @default
                                        <span class="badge badge-secondary">{{ $payment->status }}</span>
                                @endswitch
                            </td>
                            <td>{{ $payment->created_at->format('d M Y H:i') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted">Tidak ada data</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
