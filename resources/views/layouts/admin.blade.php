<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Radius Manager</title>
    <script src="https://cdn.jsdelivr.net/npm/@hotwired/turbo@8.0.4/dist/turbo.es2017-umd.js" data-turbo-track="reload"></script>
    <style>
        .turbo-loading .content-wrapper {
            opacity: 0.85;
            transition: opacity 150ms ease-in-out;
        }
    </style>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <a href="{{ route('dashboard') }}" class="nav-link">Dashboard</a>
            </li>
        </ul>
        <ul class="navbar-nav ml-auto">
            @auth
                <li class="nav-item">
                    <span class="nav-link">{{ auth()->user()->name }} ({{ strtoupper(str_replace('_', ' ', auth()->user()->role)) }})</span>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">Logout</a>
                </li>
            @else
                <li class="nav-item"><a href="{{ route('login') }}" class="nav-link">Login</a></li>
                <li class="nav-item"><a href="{{ route('register') }}" class="nav-link">Register</a></li>
            @endauth
        </ul>
    </nav>
    @auth
        @include('auth.logout')
    @endauth

    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <a href="{{ route('dashboard') }}" class="brand-link text-center">
            <span class="brand-text font-weight-light">Radius Admin</span>
        </a>
        <div class="sidebar">
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">
                    <li class="nav-item">
                        <a href="{{ route('dashboard') }}" class="nav-link">
                            <i class="nav-icon fas fa-tachometer-alt"></i>
                            <p>Dashboard</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('dashboard.api') }}" class="nav-link">
                            <i class="nav-icon fas fa-chart-pie"></i>
                            <p>API Dashboard</p>
                        </a>
                    </li>
                    <li class="nav-item has-treeview">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-file-invoice"></i>
                            <p>
                                Data Tagihan
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="#" class="nav-link" data-toggle="modal" data-target="#period-bills-modal">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Tagihan Periode</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" class="nav-link" data-toggle="modal" data-target="#invoice-filter-modal">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Semua Tagihan (Invoice)</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-item has-treeview">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-dollar-sign"></i>
                            <p>
                                Data Keuangan
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="#" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Income Harian</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Income Periode</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Pengeluaran</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Laba Rugi</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Hitung BHP | USO</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-item has-treeview">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-wrench"></i>
                            <p>
                                Tool Sistem
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="#" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Cek Pemakaian</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Impor User</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Ekspor User</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Ekspor Transaksi</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Backup Restore DB</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" class="nav-link text-danger">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Reset Laporan</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" class="nav-link text-danger">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Reset Database</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-item has-treeview">
                        <a href="#" class="nav-link">
                            <i class="nav-icon far fa-file-alt"></i>
                            <p>
                                Log Aplikasi
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="#" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Log Login</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Log Aktivitas</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Log BG Process</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Log Auth Radius</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <hr class="mt-1 mb-1">
                            </li>
                            <li class="nav-item">
                                <a href="#" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Log WA Blast</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('mikrotik-connections.index') }}" class="nav-link">
                            <i class="nav-icon fas fa-server"></i>
                            <p>Router (NAS)</p>
                        </a>
                    </li>
                    <li class="nav-item has-treeview">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-users"></i>
                            <p>
                                List Pelanggan
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="{{ route('radius-accounts.index') }}" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>User Hotspot</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('ppp-users.index') }}" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>User PPP</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Peta Pelanggan</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Data ODP</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-item has-treeview">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-cog"></i>
                            <p>
                                Pengaturan
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="{{ route('users.index') }}" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Manajemen Pengguna</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('tenant-settings.index') }}" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Pengaturan Bisnis</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('settings.freeradius') }}" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>FreeRADIUS</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('settings.ovpn') }}" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>OpenVPN</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-item has-treeview">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-credit-card"></i>
                            <p>
                                Langganan
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="{{ route('subscription.index') }}" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Status Langganan</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('subscription.plans') }}" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Paket Tersedia</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('subscription.history') }}" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Riwayat Pembayaran</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    @if(auth()->user()->isSuperAdmin())
                    <li class="nav-header">SUPER ADMIN</li>
                    <li class="nav-item">
                        <a href="{{ route('super-admin.dashboard') }}" class="nav-link">
                            <i class="nav-icon fas fa-crown"></i>
                            <p>Admin Dashboard</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('super-admin.tenants') }}" class="nav-link">
                            <i class="nav-icon fas fa-building"></i>
                            <p>Kelola Tenant</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('super-admin.subscription-plans.index') }}" class="nav-link">
                            <i class="nav-icon fas fa-tags"></i>
                            <p>Paket Langganan</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('super-admin.reports.revenue') }}" class="nav-link">
                            <i class="nav-icon fas fa-chart-line"></i>
                            <p>Laporan Pendapatan</p>
                        </a>
                    </li>
                    @endif
                    <li class="nav-item has-treeview">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-box"></i>
                            <p>
                                Profil Paket
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="{{ route('bandwidth-profiles.index') }}" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Bandwidth</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('profile-groups.index') }}" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Profil Group</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('hotspot-profiles.index') }}" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Profil Hotspot</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('ppp-profiles.index') }}" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Profil PPP</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </nav>
        </div>
    </aside>

    <div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1>@yield('title')</h1>
                    </div>
                </div>
                @if (session('status'))
                    <div class="alert alert-success">
                        {{ session('status') }}
                    </div>
                @endif
                @if (session('error'))
                    <div class="alert alert-danger">
                        {{ session('error') }}
                    </div>
                @endif
            </div>
        </section>

        <turbo-frame id="main-content">
            <section class="content">
                <div class="container-fluid">
                    @yield('content')
                </div>
            </section>
        </turbo-frame>
    </div>

    <footer class="main-footer">
        <strong>FreeRADIUS Mikrotik Manager.</strong>
        <div class="float-right d-none d-sm-inline-block">
            Support ROS 7.x / 6.x
        </div>
    </footer>
</div>

<div class="modal fade" id="invoice-filter-modal" tabindex="-1" aria-labelledby="invoice-filter-modal-label" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="invoice-filter-modal-label">Semua Tagihan (Invoice)</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form method="GET" action="{{ route('invoices.index') }}">
                    <div class="form-group">
                        <label for="invoice-service-type">Tipe Service</label>
                        <select class="form-control" id="invoice-service-type" name="service_type">
                            <option value="">- Semua -</option>
                            <option value="pppoe">PPPoE</option>
                            <option value="hotspot">Hotspot</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="invoice-owner">Owner Data</label>
                        <select class="form-control" id="invoice-owner" name="owner_id">
                            <option value="">- Semua Owner -</option>
                            @isset($sidebarOwners)
                                @foreach($sidebarOwners as $owner)
                                    <option value="{{ $owner->id }}">{{ $owner->name }}</option>
                                @endforeach
                            @endisset
                        </select>
                    </div>
                    <div class="text-right">
                        <button type="submit" class="btn btn-primary">Lihat Laporan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="all-bills-modal" tabindex="-1" aria-labelledby="all-bills-modal-label" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="all-bills-modal-label">Semua Tagihan</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form method="GET" action="{{ route('reports.income') }}">
                    <div class="form-group">
                        <label for="modal-service-type">Tipe Service</label>
                        <select class="form-control" id="modal-service-type" name="service_type">
                            <option value="">- Semua -</option>
                            <option value="hotspot">HOTSPOT</option>
                            <option value="pppoe">PPPoE</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="modal-owner">Owner Data</label>
                        <select class="form-control" id="modal-owner" name="owner_id">
                            <option value="">- Semua Owner -</option>
                            @isset($sidebarOwners)
                                @foreach($sidebarOwners as $owner)
                                    <option value="{{ $owner->id }}">{{ $owner->name }}</option>
                                @endforeach
                            @endisset
                        </select>
                    </div>
                    <div class="text-right">
                        <button type="submit" class="btn btn-primary">Lihat Laporan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="period-bills-modal" tabindex="-1" aria-labelledby="period-bills-modal-label" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="period-bills-modal-label">Tagihan Periode</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form method="GET" action="{{ route('reports.income') }}">
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="period-date-from">Dari Tanggal</label>
                            <input type="date" class="form-control" id="period-date-from" name="date_from" value="{{ now()->subMonthNoOverflow()->startOfMonth()->toDateString() }}">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="period-date-to">Sampai Tanggal</label>
                            <input type="date" class="form-control" id="period-date-to" name="date_to" value="{{ now()->subMonthNoOverflow()->endOfMonth()->toDateString() }}">
                        </div>
                    </div>
                    <div class="text-muted mb-3"><em>Tanggal jatuh tempo pelanggan</em></div>
                    <div class="form-group">
                        <label for="period-service-type">Tipe Service</label>
                        <select class="form-control" id="period-service-type" name="service_type">
                            <option value="">- Semua Transaksi -</option>
                            <option value="pppoe">PPPoE</option>
                            <option value="hotspot">HOTSPOT</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="period-owner">Owner Data</label>
                        <select class="form-control" id="period-owner" name="owner_id">
                            <option value="">- Semua Owner -</option>
                            @isset($sidebarOwners)
                                @foreach($sidebarOwners as $owner)
                                    <option value="{{ $owner->id }}">{{ $owner->name }}</option>
                                @endforeach
                            @endisset
                        </select>
                    </div>
                    <div class="text-right mt-4">
                        <button type="submit" class="btn btn-primary">Lihat Laporan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script>
    document.addEventListener('turbo:visit', () => document.body.classList.add('turbo-loading'));
    document.addEventListener('turbo:load', () => {
        document.body.classList.remove('turbo-loading');
        document.querySelectorAll('.nav-link[href]').forEach(link => {
            if (!link.dataset.turboFrame) {
                link.dataset.turboFrame = 'main-content';
            }
        });
    });
</script>
@stack('scripts')
</body>
</html>
