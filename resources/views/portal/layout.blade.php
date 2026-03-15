<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Portal Pelanggan') @if(isset($tenantSettings) && $tenantSettings?->business_name) — {{ $tenantSettings->business_name }}@endif</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        :root {
            --brand-start: #0a3e68;
            --brand-mid:   #0f6b95;
            --brand-end:   #0c8a8f;
        }
        body {
            background: #f4f7fb;
            min-height: 100vh;
            font-family: 'Source Sans Pro', sans-serif;
        }

        /* ── Navbar ── */
        .portal-navbar {
            background: linear-gradient(105deg, var(--brand-start) 0%, var(--brand-mid) 55%, var(--brand-end) 100%);
            padding: .7rem 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 8px rgba(10,62,104,.35);
        }
        .portal-navbar .brand {
            color: #fff;
            font-weight: 700;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }
        .portal-navbar .brand img { max-height: 36px; border-radius: 4px; }
        .portal-navbar .brand .brand-icon {
            width: 36px; height: 36px;
            background: rgba(255,255,255,.18);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem;
        }

        .portal-navbar .nav-links { display: flex; align-items: center; gap: .25rem; }
        .portal-navbar .nav-links a {
            color: rgba(255,255,255,.8);
            text-decoration: none;
            font-size: .875rem;
            padding: .35rem .65rem;
            border-radius: 6px;
            transition: background .15s, color .15s;
        }
        .portal-navbar .nav-links a:hover,
        .portal-navbar .nav-links a.active {
            color: #fff;
            background: rgba(255,255,255,.18);
        }
        .portal-navbar .nav-links a.active { font-weight: 600; }
        .portal-navbar .nav-links .btn-logout {
            color: rgba(255,255,255,.75);
            border: 1px solid rgba(255,255,255,.3);
            margin-left: .25rem;
        }
        .portal-navbar .nav-links .btn-logout:hover {
            background: rgba(255,255,255,.18);
            color: #fff;
        }
        @media(max-width:576px) {
            .portal-navbar .nav-links a { padding: .3rem .45rem; font-size: .8rem; }
            .portal-navbar .brand span { display: none; }
        }

        /* ── Content ── */
        .portal-main {
            padding: 1.75rem 1rem;
            max-width: 980px;
            margin: 0 auto;
        }

        /* ── Cards ── */
        .card { border: none; box-shadow: 0 1px 6px rgba(0,0,0,.07); border-radius: 10px; }
        .card-header {
            border-radius: 10px 10px 0 0 !important;
            font-weight: 600;
            font-size: .92rem;
            letter-spacing: .01em;
        }
        .card-header.bg-primary,
        .card-header.bg-dark {
            background: linear-gradient(90deg, var(--brand-start), var(--brand-mid)) !important;
            color: #fff !important;
            border-bottom: none;
        }

        /* ── Buttons ── */
        .btn-primary {
            background: linear-gradient(90deg, var(--brand-mid), var(--brand-end));
            border: none;
        }
        .btn-primary:hover, .btn-primary:focus {
            background: linear-gradient(90deg, var(--brand-start), var(--brand-mid));
            border: none;
        }

        /* ── Footer ── */
        footer {
            border-top: 1px solid #e2eaf4;
            margin-top: 2rem;
            color: #6b7a90;
            font-size: .82rem;
            padding: 1rem;
        }

        /* ── Page header ── */
        .portal-page-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--brand-start);
            margin-bottom: 1.25rem;
        }
        .portal-page-title i { margin-right: .4rem; color: var(--brand-mid); }
    </style>
    @stack('css')
</head>
<body>
    <nav class="portal-navbar">
        <div class="brand">
            @if(isset($tenantSettings) && $tenantSettings?->business_logo)
            <img src="{{ asset('storage/' . $tenantSettings->business_logo) }}" alt="Logo">
            @else
            <span class="brand-icon"><i class="fas fa-wifi"></i></span>
            @endif
            <span>{{ ($tenantSettings ?? null)?->business_name ?? 'Portal Pelanggan' }}</span>
        </div>

        @if(request()->cookie('portal_session') && isset($portalSlug))
        <div class="nav-links">
            <a href="{{ route('portal.dashboard', $portalSlug) }}" class="{{ request()->routeIs('portal.dashboard') ? 'active' : '' }}">
                <i class="fas fa-home"></i><span class="d-none d-sm-inline"> Dashboard</span>
            </a>
            <a href="{{ route('portal.invoices', $portalSlug) }}" class="{{ request()->routeIs('portal.invoices') ? 'active' : '' }}">
                <i class="fas fa-file-invoice"></i><span class="d-none d-sm-inline"> Tagihan</span>
            </a>
            <a href="{{ route('portal.account', $portalSlug) }}" class="{{ request()->routeIs('portal.account') ? 'active' : '' }}">
                <i class="fas fa-user"></i><span class="d-none d-sm-inline"> Akun</span>
            </a>
            <a href="#" class="btn-logout" onclick="document.getElementById('logout-form').submit();return false;">
                <i class="fas fa-sign-out-alt"></i><span class="d-none d-sm-inline"> Keluar</span>
            </a>
            <form id="logout-form" action="{{ route('portal.logout', $portalSlug) }}" method="POST" style="display:none;">@csrf</form>
        </div>
        @endif
    </nav>

    <div class="portal-main">
        @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }} <button type="button" class="close" data-dismiss="alert">&times;</button></div>
        @endif
        @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }} <button type="button" class="close" data-dismiss="alert">&times;</button></div>
        @endif

        @yield('content')
    </div>

    <footer class="text-center">
        &copy; {{ date('Y') }} {{ ($tenantSettings ?? null)?->business_name ?? 'Portal Pelanggan' }}
        @if(($tenantSettings ?? null)?->business_phone)
         &middot; <i class="fas fa-phone-alt fa-xs"></i> {{ $tenantSettings->business_phone }}
        @endif
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.4/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    @stack('js')
</body>
</html>
