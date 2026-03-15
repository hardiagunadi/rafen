<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $tenantTitle = 'RAFEN Manager';
        $subscriptionExpired = false;
        $subscriptionDaysLeft = null;
        $hotspotModuleEnabled = true;
        $isolatedUsersCount = 0;
        $monthlyRegistrationsCount = 0;
        $notificationTotal = 0;
        if (auth()->check()) {
            $tenantSettings = \App\Models\TenantSettings::getOrCreate(auth()->user()->effectiveOwnerId());
            if ($tenantSettings?->business_name) {
                $tenantTitle = $tenantSettings->business_name;
            }
            $hotspotModuleEnabled = $tenantSettings?->isHotspotModuleEnabled() ?? true;
            $authUser = auth()->user();
            if (!$authUser->isSuperAdmin() && !$authUser->canAccessApp()) {
                $subscriptionExpired = true;
            }
            if (!$authUser->isSuperAdmin() && !$subscriptionExpired && $authUser->subscription_expires_at) {
                $subscriptionDaysLeft = now()->diffInDays($authUser->subscription_expires_at, false);
            }

            $now = now();
            $pppUserQuery = \App\Models\PppUser::query()->accessibleBy($authUser);
            $isolatedUsersCount = (clone $pppUserQuery)->where('status_akun', 'isolir')->count();
            $monthlyRegistrationsCount = (clone $pppUserQuery)
                ->whereMonth('created_at', $now->month)
                ->whereYear('created_at', $now->year)
                ->count();

            if ($hotspotModuleEnabled) {
                $hotspotUserQuery = \App\Models\HotspotUser::query()->accessibleBy($authUser);
                $isolatedUsersCount += (clone $hotspotUserQuery)->where('status_akun', 'isolir')->count();
                $monthlyRegistrationsCount += (clone $hotspotUserQuery)
                    ->whereMonth('created_at', $now->month)
                    ->whereYear('created_at', $now->year)
                    ->count();
            }

            $dueSoonCount = \App\Models\Invoice::query()
                ->accessibleBy($authUser)
                ->where('status', 'unpaid')
                ->whereBetween('due_date', [$now->toDateString(), $now->copy()->addDays(7)->toDateString()])
                ->count();

            $notificationTotal = $isolatedUsersCount + $monthlyRegistrationsCount + $dueSoonCount;

            // Untuk teknisi: ganti notifikasi dengan jumlah tiket aktif yang di-assign
            if ($authUser->role === 'teknisi') {
                $teknisiActiveTickets = \App\Models\WaTicket::where('assigned_to_id', $authUser->id)
                    ->whereIn('status', ['open', 'in_progress'])
                    ->count();
                $notificationTotal = $teknisiActiveTickets;
            }

            // Admin, CS, dan NOC mendapat notifikasi update tiket dari teknisi
            $csTicketUpdateCount = 0;
            $canSeeTicketUpdate = ($authUser->isAdmin() && ! $authUser->isSubUser())
                || in_array($authUser->role, ['cs', 'noc'], true);
            if ($canSeeTicketUpdate) {
                $ownerId = $authUser->effectiveOwnerId();
                $csTicketUpdateCount = \App\Models\WaTicketNote::query()
                    ->where('read_by_cs', false)
                    ->whereHas('ticket', fn ($q) => $q->where('owner_id', $ownerId))
                    ->count();
                $notificationTotal += $csTicketUpdateCount;
            }
        }
    @endphp
    @php
        $serverLoadTimeMs = defined('LARAVEL_START')
            ? (microtime(true) - LARAVEL_START) * 1000
            : null;
    @endphp
    <title>{{ $tenantTitle }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/svg+xml" href="{{ asset('branding/rafen-favicon.svg') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('branding/favicon-32.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('branding/favicon-180.png') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-bs4@1.13.8/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-responsive-bs4@2.5.0/css/responsive.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme@1.5.2/dist/select2-bootstrap4.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <style>
        :root {
            --app-bg: #f4f7fb;
            --app-border: #d7e1ee;
            --app-surface: #ffffff;
            --app-shadow: 0 10px 22px rgba(15, 23, 42, 0.07);
            --app-shadow-soft: 0 6px 14px rgba(15, 23, 42, 0.05);
            --app-text: #0f172a;
            --app-text-soft: #5b6b83;
        }

        body.sidebar-mini .content-wrapper {
            background:
                radial-gradient(circle at 8% -8%, rgba(14, 116, 144, 0.1), transparent 30%),
                radial-gradient(circle at 100% 0%, rgba(37, 99, 235, 0.07), transparent 24%),
                var(--app-bg);
        }

        .content-wrapper > .content {
            padding-top: 0.8rem;
            padding-bottom: 1rem;
        }

        .content-wrapper > .content > .container-fluid {
            padding-left: 0.95rem;
            padding-right: 0.95rem;
        }

        .content-wrapper .card {
            border: 1px solid var(--app-border);
            border-radius: 16px;
            box-shadow: var(--app-shadow-soft);
            background: var(--app-surface);
            overflow: hidden;
        }

        .content-wrapper .card-header {
            border-bottom: 1px solid #e4ebf5;
            background: linear-gradient(180deg, #fbfdff 0%, #f5f9ff 100%);
            padding: 0.82rem 1rem;
        }

        .content-wrapper .card-title {
            color: var(--app-text);
            font-weight: 700;
        }

        .content-wrapper .card-body {
            padding: 1rem;
        }

        .content-wrapper .card-footer {
            border-top: 1px solid #e4ebf5;
            background: #f8fbff;
            padding: 0.82rem 1rem;
        }

        .content-wrapper .form-control,
        .content-wrapper .custom-select,
        .content-wrapper .custom-file-label,
        .content-wrapper .input-group-text {
            border-radius: 8px;
            border-color: #d4deea;
        }

        .content-wrapper .form-control:focus,
        .content-wrapper .custom-select:focus {
            border-color: #8fb5df;
            box-shadow: 0 0 0 0.2rem rgba(19, 103, 164, 0.15);
        }

        .content-wrapper .select2-container--bootstrap4 .select2-selection {
            border-color: #d4deea;
            border-radius: 8px;
            min-height: calc(2.25rem + 2px);
        }

        .content-wrapper .select2-container--bootstrap4.select2-container--focus .select2-selection {
            border-color: #8fb5df;
            box-shadow: 0 0 0 0.2rem rgba(19, 103, 164, 0.15);
        }

        .content-wrapper .select2-container--bootstrap4 .select2-selection--single .select2-selection__rendered {
            line-height: calc(2.25rem - 2px);
        }

        .content-wrapper .select2-container--bootstrap4 .select2-selection--single .select2-selection__arrow {
            height: calc(2.25rem - 2px);
        }

        .content-wrapper .table thead th {
            border-top: 0;
            border-bottom: 1px solid #dfe8f4;
            background: #f8fbff;
            color: #64748b;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .content-wrapper .table td {
            vertical-align: middle;
        }

        .content-wrapper .btn-primary {
            background-color: #1367a4;
            border-color: #1367a4;
        }

        .content-wrapper .btn-primary:hover,
        .content-wrapper .btn-primary:focus {
            background-color: #0f5689;
            border-color: #0f5689;
        }

        .content-wrapper .small-box,
        .content-wrapper .info-box {
            border-radius: 14px;
            box-shadow: var(--app-shadow);
            overflow: hidden;
        }

        .main-header.navbar {
            border-bottom: 1px solid rgba(9, 39, 68, 0.22);
            background:
                linear-gradient(105deg, rgba(10, 62, 104, 0.98), rgba(15, 107, 149, 0.95) 45%, rgba(12, 138, 143, 0.94)) !important;
            box-shadow: 0 8px 20px rgba(9, 39, 68, 0.22);
        }

        .main-header.navbar .navbar-nav > .nav-item > .nav-link:not(.text-danger):not(.text-warning) {
            color: #e7f5ff;
            border-radius: 9px;
            transition: background-color 0.16s ease, color 0.16s ease, transform 0.16s ease;
        }

        .main-header.navbar .navbar-nav > .nav-item > .nav-link:not(.text-danger):not(.text-warning):hover,
        .main-header.navbar .navbar-nav > .nav-item > .nav-link:not(.text-danger):not(.text-warning):focus {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.16);
            transform: translateY(-1px);
        }

        .main-header.navbar .navbar-nav > .nav-item > .nav-link.text-danger,
        .main-header.navbar .navbar-nav > .nav-item > .nav-link.text-warning {
            font-weight: 700;
            text-shadow: 0 1px 8px rgba(7, 20, 35, 0.35);
        }

        .main-header.navbar .user-dropdown-toggle {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            font-weight: 600;
        }

        .main-header.navbar .user-dropdown-toggle::after {
            border-top-color: currentColor;
        }

        .main-header.navbar .user-dropdown-menu {
            border: 1px solid rgba(148, 163, 184, 0.3);
            border-radius: 10px;
            box-shadow: 0 12px 24px rgba(2, 8, 23, 0.18);
            min-width: 220px;
        }

        .main-header.navbar .user-dropdown-menu .dropdown-item {
            font-weight: 500;
        }

        .main-header.navbar .user-dropdown-menu .user-dropdown-profile {
            padding: 0.6rem 1rem 0.5rem;
            color: #1f2937;
            line-height: 1.35;
        }

        .main-header.navbar .user-dropdown-menu .user-dropdown-name {
            display: block;
            font-weight: 700;
            color: #0f172a;
        }

        .main-header.navbar .user-dropdown-menu .user-dropdown-meta {
            display: block;
            color: #64748b;
            font-size: 0.85rem;
        }

        .main-header.navbar .user-dropdown-menu .dropdown-item:active {
            background-color: #0f6b95;
        }

        .main-header.navbar .nav-link-icon {
            width: 2.3rem;
            height: 2.3rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .main-header.navbar .nav-icon-badge {
            position: absolute;
            top: 0.12rem;
            right: 0.12rem;
            min-width: 1.1rem;
            height: 1.1rem;
            border-radius: 999px;
            font-size: 0.65rem;
            line-height: 1.1rem;
            text-align: center;
            font-weight: 700;
            color: #fff;
        }

        .main-header.navbar .nav-icon-badge.badge-danger {
            background-color: #dc3545;
        }

        .main-header.navbar .nav-icon-badge.badge-warning {
            background-color: #f4b000;
        }

        .main-header.navbar .navbar-icon-dropdown {
            min-width: 280px;
            border: 1px solid rgba(148, 163, 184, 0.3);
            border-radius: 10px;
            box-shadow: 0 12px 24px rgba(2, 8, 23, 0.18);
        }

        .main-header.navbar .navbar-icon-dropdown .dropdown-item-text {
            white-space: normal;
        }

        .main-header.navbar .navbar-search-block {
            padding-left: 0.75rem;
            padding-right: 0.75rem;
        }

        .main-header.navbar .navbar-search-block .form-control-navbar,
        .main-header.navbar .navbar-search-block .btn-navbar,
        .main-header.navbar .navbar-search-block .custom-select {
            height: calc(2rem + 2px);
        }

        .main-header.navbar .navbar-search-block .search-target-select {
            min-width: 145px;
            border: 1px solid #d0d8e4;
            border-right: 0;
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
            font-size: 0.9rem;
        }

        .main-header.navbar .navbar-search-block .form-control-navbar {
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
            border-left: 1px solid #d0d8e4;
        }

        .main-sidebar.sidebar-modern {
            position: relative;
            border-right: 1px solid rgba(148, 163, 184, 0.24);
            background: linear-gradient(180deg, #081527 0%, #0d2035 48%, #102a44 100%);
        }

        .main-sidebar.sidebar-modern::before {
            content: '';
            position: absolute;
            inset: 0;
            pointer-events: none;
            background:
                radial-gradient(circle at 18% 6%, rgba(56, 189, 248, 0.24), transparent 28%),
                radial-gradient(circle at 85% 0%, rgba(14, 165, 233, 0.18), transparent 26%),
                linear-gradient(180deg, rgba(255, 255, 255, 0.03) 0%, rgba(255, 255, 255, 0) 35%);
        }

        .sidebar-modern .brand-link,
        .sidebar-modern .sidebar {
            position: relative;
            z-index: 1;
        }

        .sidebar-modern .brand-link {
            border-bottom: 1px solid rgba(148, 163, 184, 0.2);
            background: linear-gradient(110deg, rgba(15, 118, 168, 0.35), rgba(14, 165, 233, 0.14));
            padding-top: 0.95rem;
            padding-bottom: 0.95rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.55rem;
        }

        .sidebar-modern .brand-text {
            color: #f8fbff;
            font-weight: 700;
            letter-spacing: 0.015em;
            text-shadow: 0 2px 10px rgba(15, 23, 42, 0.35);
        }

        .sidebar-modern .brand-logo-mark {
            width: 1.95rem;
            height: 1.95rem;
            border-radius: 0.5rem;
            box-shadow: 0 8px 16px rgba(8, 23, 39, 0.35);
            border: 1px solid rgba(255, 255, 255, 0.24);
        }

        .sidebar-modern .sidebar {
            scrollbar-width: thin;
            scrollbar-color: rgba(125, 166, 210, 0.5) transparent;
        }

        .sidebar-modern .sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar-modern .sidebar::-webkit-scrollbar-thumb {
            border-radius: 999px;
            background: rgba(125, 166, 210, 0.42);
        }

        .sidebar-modern .nav-header {
            color: #9eb3cc;
            font-size: 0.69rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            padding-top: 0.9rem;
            padding-bottom: 0.45rem;
        }

        .sidebar-modern .nav-sidebar .nav-link {
            margin: 0.17rem 0.4rem;
            border-radius: 11px;
            border: 1px solid transparent;
            color: #d7e5f7;
            padding: 0.58rem 0.72rem;
            transition: all 170ms ease;
        }

        .sidebar-modern .nav-sidebar > .nav-item > .nav-link {
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.015);
        }

        .sidebar-modern .nav-sidebar .nav-link p {
            font-weight: 500;
            letter-spacing: 0.01em;
        }

        .sidebar-modern .nav-sidebar .nav-link .nav-icon {
            width: 1.84rem;
            height: 1.84rem;
            margin-right: 0.5rem;
            border-radius: 9px;
            line-height: 1.84rem;
            text-align: center;
            background: rgba(148, 163, 184, 0.16);
            color: #97b4d2;
            font-size: 0.87rem;
            transition: all 170ms ease;
        }

        .sidebar-modern .nav-sidebar .nav-link .right {
            color: #9ab4d1;
        }

        .sidebar-modern .nav-sidebar .nav-link:hover,
        .sidebar-modern .nav-sidebar .nav-link:focus {
            background: rgba(37, 99, 235, 0.18);
            border-color: rgba(125, 166, 210, 0.3);
            color: #f5fbff;
            transform: translateX(2px);
        }

        .sidebar-modern .nav-sidebar .nav-link:hover .nav-icon,
        .sidebar-modern .nav-sidebar .nav-link:focus .nav-icon {
            background: rgba(191, 219, 254, 0.23);
            color: #eaf5ff;
        }

        .sidebar-modern .nav-sidebar .menu-open > .nav-link,
        .sidebar-modern .nav-sidebar .nav-link.active {
            background: linear-gradient(135deg, #0f6aa7 0%, #17a2b8 100%);
            border-color: rgba(255, 255, 255, 0.26);
            box-shadow: 0 10px 22px rgba(9, 91, 138, 0.36);
            color: #f8fdff;
        }

        .sidebar-modern .nav-sidebar .menu-open > .nav-link .nav-icon,
        .sidebar-modern .nav-sidebar .nav-link.active .nav-icon {
            background: rgba(255, 255, 255, 0.22);
            color: #fff;
        }

        .sidebar-modern .nav-treeview {
            margin: 0.22rem 0.38rem 0.42rem 1.35rem;
            padding-left: 0.46rem;
            border-left: 1px dashed rgba(148, 163, 184, 0.38);
        }

        .sidebar-modern .nav-treeview > .nav-item > .nav-link {
            margin: 0.14rem 0;
            padding: 0.48rem 0.6rem;
            border-radius: 10px;
            background: rgba(15, 23, 42, 0.24);
        }

        .sidebar-modern .nav-treeview > .nav-item > .nav-link:hover,
        .sidebar-modern .nav-treeview > .nav-item > .nav-link:focus {
            background: rgba(37, 99, 235, 0.26);
            transform: none;
        }

        .sidebar-modern .nav-treeview > .nav-item > .nav-link.active {
            background: linear-gradient(135deg, #2c7fbf 0%, #0ea5e9 100%);
        }

        .sidebar-modern .nav-treeview > .nav-item > .nav-link .nav-icon {
            width: 1.6rem;
            height: 1.6rem;
            line-height: 1.6rem;
            border-radius: 7px;
            font-size: 0.72rem;
        }

        @media (max-width: 991.98px) {
            .content-wrapper > .content > .container-fluid {
                padding-left: 0.65rem;
                padding-right: 0.65rem;
            }

            .sidebar-modern .nav-sidebar .nav-link {
                margin-left: 0.35rem;
                margin-right: 0.35rem;
            }
        }
    </style>
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
                @if($subscriptionExpired)
                <li class="nav-item">
                    <a href="{{ route('subscription.expired') }}" class="nav-link text-danger font-weight-bold">
                        <i class="fas fa-exclamation-triangle"></i> Langganan Berakhir
                    </a>
                </li>
                @elseif($subscriptionDaysLeft !== null && $subscriptionDaysLeft <= 7)
                <li class="nav-item">
                    <a href="{{ route('subscription.index') }}" class="nav-link {{ $subscriptionDaysLeft <= 3 ? 'text-danger' : 'text-warning' }} font-weight-bold">
                        <i class="fas fa-bell"></i>
                        @if($subscriptionDaysLeft <= 0)
                            Langganan habis hari ini!
                        @else
                            Langganan berakhir {{ $subscriptionDaysLeft }} hari lagi
                        @endif
                    </a>
                </li>
                @endif
                <li class="nav-item">
                    <a class="nav-link nav-link-icon" href="#" role="button" data-widget="navbar-search" title="Pencarian">
                        <i class="fas fa-search"></i>
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link nav-link-icon" href="#" id="navbar-notification-dropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Notifikasi">
                        <i class="far fa-bell"></i>
                        @if($notificationTotal > 0)
                            <span class="nav-icon-badge badge-warning">{{ $notificationTotal }}</span>
                        @endif
                    </a>
                    <div class="dropdown-menu dropdown-menu-right navbar-icon-dropdown" aria-labelledby="navbar-notification-dropdown">
                        <span class="dropdown-item-text font-weight-bold">Ringkasan Notifikasi</span>
                        <div class="dropdown-divider"></div>
                        @if(auth()->user()->role === 'teknisi')
                        <a href="{{ route('wa-tickets.index') }}" class="dropdown-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-ticket-alt text-warning mr-2"></i>Tiket Aktif Saya</span>
                            <span class="badge badge-{{ $teknisiActiveTickets > 0 ? 'danger' : 'secondary' }}">{{ $teknisiActiveTickets ?? 0 }}</span>
                        </a>
                        @else
                        @if($csTicketUpdateCount > 0)
                        <a href="{{ route('wa-tickets.index') }}" class="dropdown-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-ticket-alt text-warning mr-2"></i>Update Tiket dari Teknisi</span>
                            <span class="badge badge-warning">{{ $csTicketUpdateCount }}</span>
                        </a>
                        @endif
                        <a href="{{ route('ppp-users.index', ['filter_isolir' => 1]) }}" class="dropdown-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-user-slash text-danger mr-2"></i>User Terisolir</span>
                            <span class="badge badge-danger">{{ $isolatedUsersCount }}</span>
                        </a>
                        <a href="{{ route('ppp-users.index') }}" class="dropdown-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-user-plus text-info mr-2"></i>Registrasi Bulan Ini</span>
                            <span class="badge badge-info">{{ $monthlyRegistrationsCount }}</span>
                        </a>
                        <a href="{{ route('invoices.index', ['filter_due' => '7days']) }}" class="dropdown-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-calendar-times text-warning mr-2"></i>Jatuh Tempo 7 Hari</span>
                            <span class="badge badge-warning">{{ $dueSoonCount }}</span>
                        </a>
                        @endif
                    </div>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle user-dropdown-toggle" href="#" id="navbar-user-dropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        {{ auth()->user()->name }}
                    </a>
                    <div class="dropdown-menu dropdown-menu-right user-dropdown-menu" aria-labelledby="navbar-user-dropdown">
                        <div class="dropdown-item-text user-dropdown-profile">
                            <span class="user-dropdown-name">{{ auth()->user()->name }}</span>
                            <span class="user-dropdown-meta">(Role: {{ ucwords(str_replace('_', ' ', auth()->user()->role)) }})</span>
                            <span class="user-dropdown-meta">({{ $tenantTitle }})</span>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a href="#" class="dropdown-item" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                            <i class="fas fa-sign-out-alt mr-2"></i>Logout
                        </a>
                    </div>
                </li>
            @else
                <li class="nav-item"><a href="{{ route('login') }}" class="nav-link">Login</a></li>
                <li class="nav-item"><a href="{{ route('register') }}" class="nav-link">Register</a></li>
            @endauth
        </ul>
        @auth
            <div class="navbar-search-block">
                <form
                    class="form-inline"
                    id="navbar-global-search-form"
                    action="{{ route('ppp-users.index') }}"
                    method="GET"
                    data-pppoe-action="{{ route('ppp-users.index') }}"
                    data-pppoe-autocomplete="{{ route('ppp-users.autocomplete') }}"
                    @if($hotspotModuleEnabled)
                        data-hotspot-action="{{ route('hotspot-users.index') }}"
                        data-hotspot-autocomplete="{{ route('hotspot-users.autocomplete') }}"
                    @endif
                >
                    <div class="input-group input-group-sm w-100">
                        @if($hotspotModuleEnabled)
                            <div class="input-group-prepend">
                                <select class="custom-select search-target-select" id="navbar-search-target" name="target" data-native-select="true">
                                    <option value="pppoe" @selected(request('target', 'pppoe') === 'pppoe')>PPPoE</option>
                                    <option value="hotspot" @selected(request('target') === 'hotspot')>Hotspot</option>
                                </select>
                            </div>
                        @else
                            <input type="hidden" name="target" value="pppoe">
                        @endif
                        <input class="form-control form-control-navbar" name="search" id="navbar-search-keyword" type="search" value="{{ request('search') }}" placeholder="Cari pelanggan, ID, username..." aria-label="Search" list="navbar-search-suggestions" autocomplete="off">
                        <datalist id="navbar-search-suggestions"></datalist>
                        <div class="input-group-append">
                            <button class="btn btn-navbar" type="submit" title="Cari">
                                <i class="fas fa-search"></i>
                            </button>
                            <button class="btn btn-navbar" type="button" data-widget="navbar-search" title="Tutup">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        @endauth
    </nav>
    @auth
        @include('auth.logout')
    @endauth

    <aside class="main-sidebar sidebar-dark-primary elevation-4 sidebar-modern">
        <a href="{{ route('dashboard') }}" class="brand-link text-center">
            <img src="{{ asset('branding/rafen-mark.svg') }}" alt="Rafen Logo" class="brand-logo-mark">
            <span class="brand-text font-weight-light">{{ $tenantTitle }}</span>
        </a>
        <div class="sidebar">
            @hasSection('sidebar')
                @yield('sidebar')
            @else
            <nav class="mt-2">
                @php
                    $listPelangganRoutes = $hotspotModuleEnabled
                        ? ['hotspot-users.*', 'ppp-users.*', 'vouchers.*', 'customer-map.*', 'odps.*']
                        : ['ppp-users.*', 'vouchers.*', 'customer-map.*', 'odps.*'];
                    $profilePaketRoutes = $hotspotModuleEnabled
                        ? ['bandwidth-profiles.*', 'profile-groups.*', 'hotspot-profiles.*', 'ppp-profiles.*']
                        : ['bandwidth-profiles.*', 'profile-groups.*', 'ppp-profiles.*'];
                @endphp
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">
                    @if($subscriptionExpired)
                    {{-- Subscription expired: hanya tampilkan menu Langganan --}}
                    <li class="nav-item">
                        <a href="{{ route('subscription.expired') }}" class="nav-link text-warning">
                            <i class="nav-icon fas fa-exclamation-triangle"></i>
                            <p>Perpanjang Langganan</p>
                        </a>
                    </li>
                    @else
                    <li class="nav-item">
                        <a href="{{ route('dashboard') }}" class="nav-link">
                            <i class="nav-icon fas fa-tachometer-alt"></i>
                            <p>Dashboard</p>
                        </a>
                    </li>
                    <li class="nav-item has-treeview {{ request()->is('sessions*') ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link {{ request()->is('sessions*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-signal"></i>
                            <p>
                                Session User
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="{{ route('sessions.pppoe') }}" class="nav-link {{ request()->routeIs('sessions.pppoe*') || request()->routeIs('sessions.pppoe-inactive*') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>PPPoE</p>
                                </a>
                            </li>
                            @if($hotspotModuleEnabled)
                                <li class="nav-item">
                                    <a href="{{ route('sessions.hotspot') }}" class="nav-link {{ request()->routeIs('sessions.hotspot*') || request()->routeIs('sessions.hotspot-inactive*') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Hotspot</p>
                                    </a>
                                </li>
                            @endif
                        </ul>
                    </li>

                     <li class="nav-item has-treeview {{ request()->routeIs(...$listPelangganRoutes) ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link {{ request()->routeIs(...$listPelangganRoutes) ? 'active' : '' }}">
                            <i class="nav-icon fas fa-users"></i>
                            <p>
                                List Pelanggan
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            @if($hotspotModuleEnabled)
                                <li class="nav-item">
                                    <a href="{{ route('hotspot-users.index') }}" class="nav-link {{ request()->routeIs('hotspot-users.*') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>User Hotspot</p>
                                    </a>
                                </li>
                            @endif
                            <li class="nav-item">
                                <a href="{{ route('ppp-users.index') }}" class="nav-link {{ request()->routeIs('ppp-users.*') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>User PPP</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('vouchers.index') }}" class="nav-link {{ request()->routeIs('vouchers.*') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Voucher</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('customer-map.index') }}" class="nav-link {{ request()->routeIs('customer-map.*') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Peta Pelanggan</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('odps.index') }}" class="nav-link {{ request()->routeIs('odps.*') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Data ODP</p>
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
                    @if(in_array(auth()->user()->role, ['administrator', 'noc', 'teknisi'], true))
                    <li class="nav-item">
                        <a href="{{ route('olt-connections.index') }}" class="nav-link {{ request()->routeIs('olt-connections.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-broadcast-tower"></i>
                            <p>Monitoring OLT</p>
                        </a>
                    </li>
                    @endif
                    @if(in_array(auth()->user()->role, ['administrator', 'noc', 'it_support'], true))
                    <li class="nav-item">
                        <a href="{{ route('cpe.index') }}" class="nav-link {{ request()->routeIs('cpe.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-network-wired"></i>
                            <p>CPE Management</p>
                        </a>
                    </li>

                    @endif
                    @if(auth()->user()->role !== 'teknisi')
                        <li class="nav-item has-treeview {{ request()->routeIs(...$profilePaketRoutes) ? 'menu-open' : '' }}">
                            <a href="#" class="nav-link {{ request()->routeIs(...$profilePaketRoutes) ? 'active' : '' }}">
                                <i class="nav-icon fas fa-box"></i>
                                <p>
                                    Profil Paket
                                    <i class="right fas fa-angle-left"></i>
                                </p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item">
                                    <a href="{{ route('bandwidth-profiles.index') }}" class="nav-link {{ request()->routeIs('bandwidth-profiles.*') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Bandwidth</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="{{ route('profile-groups.index') }}" class="nav-link {{ request()->routeIs('profile-groups.*') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Profil Group</p>
                                    </a>
                                </li>
                                @if($hotspotModuleEnabled)
                                    <li class="nav-item">
                                        <a href="{{ route('hotspot-profiles.index') }}" class="nav-link {{ request()->routeIs('hotspot-profiles.*') ? 'active' : '' }}">
                                            <i class="far fa-circle nav-icon"></i>
                                            <p>Profil Hotspot</p>
                                        </a>
                                    </li>
                                @endif
                                <li class="nav-item">
                                    <a href="{{ route('ppp-profiles.index') }}" class="nav-link {{ request()->routeIs('ppp-profiles.*') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Profil PPP</p>
                                    </a>
                                </li>
                            </ul>
                        </li>
                    @endif
                    
                    <li class="nav-item has-treeview {{ request()->routeIs('payments.pending*') ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link {{ request()->routeIs('payments.pending*') ? 'active' : '' }}">
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
                            @if(auth()->user()->isSuperAdmin() || auth()->user()->isAdmin() || in_array(auth()->user()->role, ['keuangan', 'cs']))
                            <li class="nav-item">
                                <a href="{{ route('payments.pending') }}" class="nav-link {{ request()->routeIs('payments.pending*') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Konfirmasi Transfer</p>
                                </a>
                            </li>
                            @endif
                        </ul>
                    </li>
                    @if(auth()->user()->isSuperAdmin() || in_array(auth()->user()->role, ['administrator', 'keuangan', 'teknisi']))
                    <li class="nav-item">
                        <a href="{{ route('teknisi-setoran.index') }}" class="nav-link {{ request()->routeIs('teknisi-setoran.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-hand-holding-usd"></i>
                            <p>Rekonsiliasi Nota</p>
                        </a>
                    </li>
                    @endif
                    @if(auth()->user()->isSuperAdmin() || in_array(auth()->user()->role, ['administrator', 'keuangan', 'teknisi'], true))
                    <li class="nav-item has-treeview {{ request()->routeIs('reports.income') ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link {{ request()->routeIs('reports.income') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-dollar-sign"></i>
                            <p>
                                Data Keuangan
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="{{ route('reports.income', ['report' => 'daily']) }}" class="nav-link {{ request()->routeIs('reports.income') && request()->query('report', 'daily') === 'daily' ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Income Harian</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('reports.income', ['report' => 'period']) }}" class="nav-link {{ request()->routeIs('reports.income') && request()->query('report') === 'period' ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Income Periode</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('reports.income', ['report' => 'expense']) }}" class="nav-link {{ request()->routeIs('reports.income') && request()->query('report') === 'expense' ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Pengeluaran</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('reports.income', ['report' => 'profit_loss']) }}" class="nav-link {{ request()->routeIs('reports.income') && request()->query('report') === 'profit_loss' ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Laba Rugi</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('reports.income', ['report' => 'bhp_uso']) }}" class="nav-link {{ request()->routeIs('reports.income') && request()->query('report') === 'bhp_uso' ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Hitung BHP | USO</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    @endif
                    @php
                        $isSuperAdmin = auth()->user()->isSuperAdmin();
                        $isAdminOrAbove = $isSuperAdmin || (auth()->user()->isAdmin() && !auth()->user()->isSubUser());
                        $isTeknisi = auth()->user()->role === 'teknisi';
                        $isKeuangan = auth()->user()->role === 'keuangan';
                        $tenantSettings = $tenantSettings ?? \App\Models\TenantSettings::where('user_id', auth()->user()->effectiveOwnerId())->first();
                        $shiftModuleEnabled = $tenantSettings?->isShiftModuleEnabled() ?? false;
                        $waChatRoles = ['administrator', 'noc', 'it_support', 'cs'];
                        $canSeeWaChat = $isSuperAdmin || in_array(auth()->user()->role, $waChatRoles, true) || $isTeknisi;
                        $canSeeShift = $isSuperAdmin || in_array(auth()->user()->role, ['administrator', 'noc', 'it_support', 'cs', 'teknisi'], true);
                        $waChatUnreadCount = ($canSeeWaChat && !$isTeknisi) ? \App\Models\WaConversation::query()->accessibleBy(auth()->user())->where('unread_count', '>', 0)->sum('unread_count') : 0;
                        // Badge tiket untuk teknisi: tiket open/in_progress yang di-assign ke mereka
                        $teknisiTicketCount = $isTeknisi ? \App\Models\WaTicket::where('assigned_to_id', auth()->id())->whereIn('status', ['open', 'in_progress'])->count() : 0;
                        // Badge update tiket dari teknisi (untuk CS/NOC/admin)
                        $csTicketUpdateCount = $csTicketUpdateCount ?? 0;
                    @endphp
                    @if($isAdminOrAbove)
                    <li class="nav-item has-treeview {{ request()->is('tools*') ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link {{ request()->is('tools*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-wrench"></i>
                            <p>
                                Tool Sistem
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="{{ route('tools.usage') }}" class="nav-link {{ request()->is('tools/usage*') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Cek Pemakaian</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('tools.import') }}" class="nav-link {{ request()->is('tools/import*') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Impor User</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('tools.export-users') }}" class="nav-link {{ request()->is('tools/export-users*') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Ekspor User</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('tools.export-transactions') }}" class="nav-link {{ request()->is('tools/export-transactions*') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Ekspor Transaksi</p>
                                </a>
                            </li>
                            @if($isSuperAdmin)
                            <li class="nav-item">
                                <a href="{{ route('tools.backup') }}" class="nav-link {{ request()->is('tools/backup*') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Backup Restore DB</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('tools.reset-report') }}" class="nav-link text-danger {{ request()->is('tools/reset-report*') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Reset Laporan</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('tools.reset-database') }}" class="nav-link text-danger {{ request()->is('tools/reset-database*') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Reset Database</p>
                                </a>
                            </li>
                            @endif
                        </ul>
                    </li>
                    @endif
                    @if(!$isTeknisi)
                    <li class="nav-item has-treeview {{ request()->routeIs('logs.*') ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link {{ request()->routeIs('logs.*') ? 'active' : '' }}">
                            <i class="nav-icon far fa-file-alt"></i>
                            <p>
                                Log Aplikasi
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="{{ route('logs.login') }}" class="nav-link {{ request()->routeIs('logs.login') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Log Login</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('logs.activity') }}" class="nav-link {{ request()->routeIs('logs.activity') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Log Aktivitas</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('logs.bg-process') }}" class="nav-link {{ request()->routeIs('logs.bg-process') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Log BG Process</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('logs.radius-auth') }}" class="nav-link {{ request()->routeIs('logs.radius-auth') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Log Auth Radius</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <hr class="mt-1 mb-1">
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('logs.wa-pengiriman') }}" class="nav-link {{ request()->routeIs('logs.wa-pengiriman') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Log Pengiriman WA</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    @endif
                    {{-- Chat WA --}}
                    @if($canSeeWaChat)
                    @php
                        $waMenuBadge = $isTeknisi ? $teknisiTicketCount : ($waChatUnreadCount + $csTicketUpdateCount);
                    @endphp
                    <li class="nav-item has-treeview {{ request()->routeIs('wa-chat.*', 'wa-tickets.*') ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link {{ request()->routeIs('wa-chat.*', 'wa-tickets.*') ? 'active' : '' }}">
                            <i class="nav-icon fab fa-whatsapp text-success"></i>
                            <p>
                                {{ $isTeknisi ? 'Tiket Saya' : 'Chat WA' }}
                                @if($waMenuBadge > 0)
                                <span class="badge badge-danger right">{{ $waMenuBadge > 99 ? '99+' : $waMenuBadge }}</span>
                                @endif
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            @if(!$isTeknisi)
                            <li class="nav-item">
                                <a href="{{ route('wa-chat.index') }}" class="nav-link {{ request()->routeIs('wa-chat.index') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>
                                        Inbox Chat
                                        @if($waChatUnreadCount > 0)
                                        <span class="badge badge-danger right">{{ $waChatUnreadCount > 99 ? '99+' : $waChatUnreadCount }}</span>
                                        @endif
                                    </p>
                                </a>
                            </li>
                            @endif
                            <li class="nav-item">
                                <a href="{{ route('wa-tickets.index') }}" class="nav-link {{ request()->routeIs('wa-tickets.*') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>
                                        {{ $isTeknisi ? 'Tiket Saya' : 'Tiket Pengaduan' }}
                                        @if($isTeknisi && $teknisiTicketCount > 0)
                                        <span class="badge badge-danger right">{{ $teknisiTicketCount > 99 ? '99+' : $teknisiTicketCount }}</span>
                                        @elseif(!$isTeknisi && $csTicketUpdateCount > 0)
                                        <span class="badge badge-warning right" title="Ada update dari teknisi">{{ $csTicketUpdateCount > 99 ? '99+' : $csTicketUpdateCount }}</span>
                                        @endif
                                    </p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    @endif

                    {{-- Jadwal Shift --}}
                    @if($canSeeShift && $shiftModuleEnabled)
                    <li class="nav-item has-treeview {{ request()->routeIs('shifts.*') ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link {{ request()->routeIs('shifts.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-calendar-alt"></i>
                            <p>
                                Jadwal Shift
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            @if(in_array(auth()->user()->role, ['administrator'], true) || $isSuperAdmin)
                            <li class="nav-item">
                                <a href="{{ route('shifts.index') }}" class="nav-link {{ request()->routeIs('shifts.index') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Kelola Jadwal</p>
                                </a>
                            </li>
                            @endif
                            <li class="nav-item">
                                <a href="{{ route('shifts.my') }}" class="nav-link {{ request()->routeIs('shifts.my') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Jadwal Saya</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    @endif

                    @if(!$isTeknisi)
                    <li class="nav-item has-treeview {{ request()->routeIs('users.*', 'tenant-settings.*', 'settings.*', 'wa-gateway.*', 'wa-blast.*') ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link {{ request()->routeIs('users.*', 'tenant-settings.*', 'settings.*', 'wa-gateway.*', 'wa-blast.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-cog"></i>
                            <p>
                                Pengaturan
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            @if(auth()->user()->isSuperAdmin() || (auth()->user()->isAdmin() && !auth()->user()->isSubUser()))
                            <li class="nav-item">
                                <a href="{{ route('users.index') }}" class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Manajemen Pengguna</p>
                                </a>
                            </li>
                            @endif
                            <li class="nav-item">
                                <a href="{{ route('tenant-settings.index') }}" class="nav-link {{ request()->routeIs('tenant-settings.*') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Pengaturan</p>
                                </a>
                            </li>
                            <li class="nav-item has-treeview {{ request()->routeIs('wa-gateway.*', 'wa-blast.*') ? 'menu-open' : '' }}">
                                <a href="#" class="nav-link {{ request()->routeIs('wa-gateway.*', 'wa-blast.*') ? 'active' : '' }}">
                                    <i class="fab fa-whatsapp nav-icon text-success"></i>
                                    <p>
                                        WhatsApp
                                        <i class="right fas fa-angle-left"></i>
                                    </p>
                                </a>
                                <ul class="nav nav-treeview">
                                    <li class="nav-item">
                                        <a href="{{ route('wa-gateway.index') }}" class="nav-link {{ request()->routeIs('wa-gateway.*') && request()->query('tab', 'overview') !== 'devices' ? 'active' : '' }}">
                                            <i class="far fa-circle nav-icon"></i>
                                            <p>Gateway & Template</p>
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a href="{{ route('wa-gateway.index', array_filter([
                                            'tab' => 'devices',
                                            'tenant_id' => request()->query('tenant_id'),
                                        ])) }}" class="nav-link {{ request()->routeIs('wa-gateway.*') && request()->query('tab') === 'devices' ? 'active' : '' }}">
                                            <i class="far fa-circle nav-icon"></i>
                                            <p>Manajemen Device</p>
                                        </a>
                                    </li>
                                    @if(auth()->user()->isSuperAdmin() || in_array(auth()->user()->role, ['administrator', 'noc', 'it_support', 'cs'], true))
                                    <li class="nav-item">
                                        <a href="{{ route('wa-blast.index') }}" class="nav-link {{ request()->routeIs('wa-blast.*') ? 'active' : '' }}">
                                            <i class="far fa-circle nav-icon"></i>
                                            <p>WA Blast</p>
                                        </a>
                                    </li>
                                    @endif
                                </ul>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('settings.freeradius') }}" class="nav-link {{ request()->routeIs('settings.freeradius') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>FreeRADIUS</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('settings.wg') }}" class="nav-link {{ request()->routeIs('settings.wg') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>WireGuard</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    @endif {{-- end !$isTeknisi (Pengaturan) --}}
                    @endif {{-- end @else (subscription not expired) --}}
                    @if(!auth()->user()->isSubUser())
                    <li class="nav-item has-treeview {{ request()->routeIs('subscription.*') ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link {{ request()->routeIs('subscription.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-credit-card {{ $subscriptionExpired ? 'text-warning' : '' }}"></i>
                            <p>
                                Langganan
                                @if($subscriptionExpired)
                                    <span class="badge badge-danger badge-pill ml-1">!</span>
                                @elseif($subscriptionDaysLeft !== null && $subscriptionDaysLeft <= 7)
                                    <span class="badge {{ $subscriptionDaysLeft <= 3 ? 'badge-danger' : 'badge-warning' }} badge-pill ml-1">{{ $subscriptionDaysLeft }}h</span>
                                @endif
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="{{ route('subscription.index') }}" class="nav-link {{ request()->routeIs('subscription.index') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Status Langganan</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('subscription.plans') }}" class="nav-link {{ request()->routeIs('subscription.plans') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Paket Tersedia</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('subscription.history') }}" class="nav-link {{ request()->routeIs('subscription.history') ? 'active' : '' }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Riwayat Pembayaran</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    @endif {{-- end !isSubUser (Langganan) --}}
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
                    <li class="nav-item">
                        <a href="{{ route('help.index') }}" class="nav-link">
                            <i class="nav-icon fas fa-question-circle"></i>
                            <p>Bantuan</p>
                        </a>
                    </li>
                </ul>
            </nav>
            @endif
        </div>
    </aside>

    <div class="content-wrapper">
        @if($subscriptionExpired)
        <section class="content-header pb-0">
            <div class="container-fluid">
                <div class="alert alert-danger alert-dismissible mb-0">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    <strong>Langganan Anda telah berakhir.</strong>
                    Akses fitur dibatasi. Silakan perpanjang untuk menggunakan semua fitur.
                    <a href="{{ route('subscription.plans') }}" class="btn btn-sm btn-danger ml-2">
                        <i class="fas fa-shopping-cart"></i> Perpanjang Sekarang
                    </a>
                </div>
            </div>
        </section>
        @elseif($subscriptionDaysLeft !== null && $subscriptionDaysLeft <= 7)
        <section class="content-header pb-0">
            <div class="container-fluid">
                <div class="alert {{ $subscriptionDaysLeft <= 3 ? 'alert-danger' : 'alert-warning' }} alert-dismissible mb-0">
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                    <i class="fas fa-bell mr-1"></i>
                    <strong>Peringatan:</strong>
                    @if($subscriptionDaysLeft <= 0)
                        Langganan Anda habis hari ini!
                    @else
                        Langganan Anda akan berakhir dalam <strong>{{ $subscriptionDaysLeft }} hari</strong>.
                    @endif
                    <a href="{{ route('subscription.renew') }}" class="btn btn-sm {{ $subscriptionDaysLeft <= 3 ? 'btn-danger' : 'btn-warning' }} ml-2">
                        <i class="fas fa-redo"></i> Perpanjang
                    </a>
                </div>
            </div>
        </section>
        @endif

        @if (session('status'))
            <script>window.__flashStatus = {{ Js::from(session('status')) }};</script>
        @endif
        @if (session('success'))
            <script>window.__flashStatus = {{ Js::from(session('success')) }};</script>
        @endif
        @if (session('error'))
            <script>window.__flashError = {{ Js::from(session('error')) }};</script>
        @endif
        <section class="content">
            <div class="container-fluid">
                @yield('content')
            </div>
        </section>
    </div>

    <footer class="main-footer">
        <strong>FreeRADIUS Mikrotik Manager.</strong>
        <div class="float-right d-none d-sm-inline-block">
            Support ROS 7.x / 6.x
            @if($serverLoadTimeMs !== null)
                <span class="text-muted ml-2">| Load Time: {{ number_format($serverLoadTimeMs, 1, '.', '') }} ms</span>
            @endif
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
                            @if($hotspotModuleEnabled)
                                <option value="hotspot">Hotspot</option>
                            @endif
                        </select>
                    </div>
                    @if(auth()->user()->isSuperAdmin())
                    <div class="form-group">
                        <label for="invoice-owner">Owner Data</label>
                        <select class="form-control" id="invoice-owner" name="owner_id">
                            <option value="">- Semua Owner -</option>
                            @foreach($sidebarOwners as $owner)
                                <option value="{{ $owner->id }}">{{ $owner->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
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
                            <option value="pppoe">PPPoE</option>
                            @if($hotspotModuleEnabled)
                                <option value="hotspot">HOTSPOT</option>
                            @endif
                        </select>
                    </div>
                    @if(auth()->user()->isSuperAdmin())
                    <div class="form-group">
                        <label for="modal-owner">Owner Data</label>
                        <select class="form-control" id="modal-owner" name="owner_id">
                            <option value="">- Semua Owner -</option>
                            @foreach($sidebarOwners as $owner)
                                <option value="{{ $owner->id }}">{{ $owner->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
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
                            @if($hotspotModuleEnabled)
                                <option value="hotspot">HOTSPOT</option>
                            @endif
                        </select>
                    </div>
                    @if(auth()->user()->isSuperAdmin())
                    <div class="form-group">
                        <label for="period-owner">Owner Data</label>
                        <select class="form-control" id="period-owner" name="owner_id">
                            <option value="">- Semua Owner -</option>
                            @foreach($sidebarOwners as $owner)
                                <option value="{{ $owner->id }}">{{ $owner->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
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
<script src="https://cdn.jsdelivr.net/npm/datatables.net@1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-bs4@1.13.8/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-responsive@2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-responsive-bs4@2.5.0/js/responsive.bootstrap4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.full.min.js"></script>
<script>
window.AppSelect = (function () {
    function canUseSelect2() {
        return typeof window.jQuery !== 'undefined'
            && typeof window.jQuery.fn !== 'undefined'
            && typeof window.jQuery.fn.select2 === 'function';
    }

    function shouldEnhance(selectElement) {
        if (!selectElement) {
            return false;
        }
        if (selectElement.dataset.nativeSelect === 'true') {
            return false;
        }
        if (selectElement.classList.contains('select2-hidden-accessible')) {
            return false;
        }
        if (selectElement.closest('.dataTables_wrapper')) {
            return false;
        }

        return true;
    }

    function buildConfig(selectElement) {
        var totalOptions = selectElement.options ? selectElement.options.length : 0;
        var parentModal = selectElement.closest('.modal');
        var config = {
            theme: 'bootstrap4',
            width: '100%',
            minimumResultsForSearch: totalOptions > 8 ? 0 : Infinity,
        };

        if (parentModal) {
            config.dropdownParent = window.jQuery(parentModal);
        }

        return config;
    }

    function initSelect(selectElement) {
        if (!canUseSelect2() || !shouldEnhance(selectElement)) {
            return;
        }

        window.jQuery(selectElement).select2(buildConfig(selectElement));
    }

    function initAll(context) {
        if (!canUseSelect2()) {
            return;
        }

        var root = context || document;
        root.querySelectorAll('select').forEach(function (selectElement) {
            initSelect(selectElement);
        });
    }

    function refresh(selectElement) {
        if (!canUseSelect2() || !selectElement) {
            return;
        }

        var $select = window.jQuery(selectElement);
        if ($select.hasClass('select2-hidden-accessible')) {
            $select.select2('destroy');
        }
        initSelect(selectElement);
    }

    document.addEventListener('DOMContentLoaded', function () {
        initAll(document);
    });

    document.addEventListener('shown.bs.modal', function (event) {
        initAll(event.target);
    });

    document.addEventListener('rafen:select-refresh', function (event) {
        if (event.detail && event.detail.element) {
            refresh(event.detail.element);
            return;
        }
        initAll(event.detail && event.detail.root ? event.detail.root : document);
    });

    return {
        initAll: initAll,
        refresh: refresh,
    };
})();
</script>
<script>
// ── Global AJAX helpers ────────────────────────────────────────────────────
window.AppAjax = (function () {
    function getCsrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.content : '';
    }

    function showToast(message, type) {
        var container = document.getElementById('app-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'app-toast-container';
            container.style.cssText = 'position:fixed;top:16px;right:16px;z-index:9999;min-width:260px;';
            document.body.appendChild(container);
        }
        var colors = { success: '#28a745', danger: '#dc3545', warning: '#ffc107', info: '#17a2b8' };
        var toast = document.createElement('div');
        toast.style.cssText = 'background:' + (colors[type] || '#333') + ';color:#fff;padding:12px 18px;border-radius:6px;margin-bottom:8px;box-shadow:0 2px 8px rgba(0,0,0,.25);font-size:14px;';
        toast.textContent = message;
        container.appendChild(toast);
        setTimeout(function () { toast.style.opacity = '0'; toast.style.transition = 'opacity .4s'; setTimeout(function () { toast.remove(); }, 400); }, 4000);
    }

    function request(method, url, body) {
        var opts = {
            method: method,
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
        };
        if (body instanceof URLSearchParams) {
            opts.body = body;
        } else if (body) {
            opts.headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(body);
        }
        return fetch(url, opts).then(function (res) {
            return res.json().then(function (data) {
                if (!res.ok) return Promise.reject(data);
                return data;
            });
        });
    }

    function formRequest(method, url, formData) {
        var params = new URLSearchParams();
        formData.forEach(function (val, key) { params.append(key, val); });
        if (method !== 'POST') params.append('_method', method);
        return request('POST', url, params);
    }

    // Delegated delete handler — call once after DOM ready
    function initDeleteButtons() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-ajax-delete]');
            if (!btn) return;
            var msg = btn.dataset.confirm || 'Hapus data ini?';
            if (!confirm(msg)) return;

            var url = btn.dataset.ajaxDelete;
            var row = btn.closest('tr');
            btn.disabled = true;

            request('DELETE', url).then(function (data) {
                showToast(data.message || data.status || 'Data berhasil dihapus.', 'success');
                document.dispatchEvent(new CustomEvent('rafen:ajax-success'));
                if (row) {
                    row.style.transition = 'opacity .3s';
                    row.style.opacity = '0';
                    setTimeout(function () { row.remove(); }, 300);
                }
            }).catch(function (err) {
                btn.disabled = false;
                showToast((err && (err.error || err.message)) || 'Gagal menghapus data.', 'danger');
            });
        });
    }

    function initPostButtons() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-ajax-post]');
            if (!btn) return;
            var msg = btn.dataset.confirm;
            if (msg && !confirm(msg)) return;

            var url = btn.dataset.ajaxPost;
            btn.disabled = true;
            var origText = btn.innerHTML;
            if (btn.dataset.loadingText) btn.innerHTML = btn.dataset.loadingText;

            request('POST', url).then(function (data) {
                btn.disabled = false;
                btn.innerHTML = origText;
                showToast(data.message || data.status || 'Berhasil.', 'success');
                document.dispatchEvent(new CustomEvent('rafen:ajax-success'));
                if (btn.dataset.reloadRow) {
                    var row = btn.closest('tr');
                    if (row && data.row_html) row.outerHTML = data.row_html;
                }
            }).catch(function (err) {
                btn.disabled = false;
                btn.innerHTML = origText;
                showToast((err && (err.error || err.message)) || 'Gagal.', 'danger');
            });
        });
    }

    function initNavbarGlobalSearch() {
        var searchForm = document.getElementById('navbar-global-search-form');
        if (!searchForm) {
            return;
        }

        var searchInput = document.getElementById('navbar-search-keyword');
        var targetSelect = document.getElementById('navbar-search-target');
        var suggestionsList = document.getElementById('navbar-search-suggestions');
        var autocompleteTimer = null;

        function selectedTarget() {
            return targetSelect ? targetSelect.value : 'pppoe';
        }

        function autocompleteUrlByTarget() {
            if (selectedTarget() === 'hotspot' && searchForm.dataset.hotspotAutocomplete) {
                return searchForm.dataset.hotspotAutocomplete;
            }

            return searchForm.dataset.pppoeAutocomplete || '';
        }

        function clearAutocompleteOptions() {
            if (!suggestionsList) {
                return;
            }

            suggestionsList.innerHTML = '';
        }

        function renderAutocompleteOptions(items) {
            if (!suggestionsList) {
                return;
            }

            clearAutocompleteOptions();

            items.forEach(function (item) {
                var option = document.createElement('option');
                option.value = item.value || '';

                if (item.label) {
                    option.label = item.label;
                }

                suggestionsList.appendChild(option);
            });
        }

        function fetchAutocomplete() {
            if (!searchInput) {
                return;
            }

            var keyword = searchInput.value.trim();
            if (keyword.length < 2) {
                clearAutocompleteOptions();

                return;
            }

            var endpoint = autocompleteUrlByTarget();
            if (!endpoint) {
                return;
            }

            window.fetch(endpoint + '?q=' + encodeURIComponent(keyword), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
                .then(function (response) { return response.ok ? response.json() : { data: [] }; })
                .then(function (payload) {
                    renderAutocompleteOptions(Array.isArray(payload.data) ? payload.data : []);
                })
                .catch(function () {
                    clearAutocompleteOptions();
                });
        }

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                if (autocompleteTimer) {
                    window.clearTimeout(autocompleteTimer);
                }

                autocompleteTimer = window.setTimeout(fetchAutocomplete, 180);
            });
        }

        if (targetSelect) {
            targetSelect.addEventListener('change', function () {
                clearAutocompleteOptions();
                fetchAutocomplete();
            });
        }

        searchForm.addEventListener('submit', function (event) {
            event.preventDefault();

            var pppoeAction = searchForm.dataset.pppoeAction || searchForm.getAttribute('action');
            var hotspotAction = searchForm.dataset.hotspotAction || pppoeAction;
            var target = selectedTarget();
            var action = target === 'hotspot' ? hotspotAction : pppoeAction;
            var keyword = searchInput ? searchInput.value.trim() : '';
            var targetUrl = new URL(action, window.location.origin);

            if (keyword !== '') {
                targetUrl.searchParams.set('search', keyword);
            }
            targetUrl.searchParams.set('target', target);

            window.location.href = targetUrl.toString();
        });
    }

document.addEventListener('DOMContentLoaded', function () {
        initDeleteButtons();
        initPostButtons();
        initNavbarGlobalSearch();
        if (window.__flashStatus) { showToast(window.__flashStatus, 'success'); window.__flashStatus = null; }
        if (window.__flashError)  { showToast(window.__flashError,  'danger');  window.__flashError  = null; }
    });

    return { request: request, formRequest: formRequest, showToast: showToast };
})();
</script>
@stack('scripts')
<script>$(function(){ $('[data-toggle="tooltip"]').tooltip(); });</script>
</body>
</html>
