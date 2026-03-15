@extends('portal.layout')

@section('title', 'Login Portal Pelanggan')

@push('css')
<style>
    body {
        background: linear-gradient(135deg, #0a3e68 0%, #0f6b95 55%, #0c8a8f 100%);
        min-height: 100vh;
    }
    .portal-navbar { display: none; }
    footer { display: none; }
    .portal-main {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-height: 100vh;
        padding: 1.5rem 1rem;
    }
    .login-box {
        width: 100%;
        max-width: 380px;
    }
    .login-logo {
        text-align: center;
        margin-bottom: 1.5rem;
    }
    .login-logo .brand-icon {
        width: 64px; height: 64px;
        background: rgba(255,255,255,.15);
        border-radius: 16px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        color: #fff;
        margin-bottom: .75rem;
    }
    .login-logo img {
        max-height: 64px;
        border-radius: 12px;
        margin-bottom: .75rem;
    }
    .login-logo .brand-name {
        color: #fff;
        font-weight: 700;
        font-size: 1.25rem;
        display: block;
        letter-spacing: .01em;
    }
    .login-logo .brand-sub {
        color: rgba(255,255,255,.7);
        font-size: .85rem;
        display: block;
    }
    .login-card {
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 8px 40px rgba(10,62,104,.3);
        overflow: hidden;
    }
    .login-card .card-header-custom {
        background: linear-gradient(90deg, #0a3e68, #0f6b95);
        padding: 1rem 1.5rem;
        color: #fff;
        font-weight: 600;
        font-size: .95rem;
        letter-spacing: .01em;
    }
    .login-card .card-body { padding: 1.5rem; }
    .login-card .form-control {
        border-radius: 8px;
        border-color: #d0dbe8;
        font-size: .92rem;
    }
    .login-card .form-control:focus {
        border-color: #0f6b95;
        box-shadow: 0 0 0 .2rem rgba(15,107,149,.2);
    }
    .login-card .btn-login {
        background: linear-gradient(90deg, #0f6b95, #0c8a8f);
        border: none;
        border-radius: 8px;
        font-weight: 600;
        letter-spacing: .02em;
        padding: .55rem;
        transition: opacity .15s;
    }
    .login-card .btn-login:hover { opacity: .88; }
    .login-footer-note {
        color: rgba(255,255,255,.65);
        font-size: .8rem;
        text-align: center;
        margin-top: 1.25rem;
    }
    .login-footer-note a { color: rgba(255,255,255,.85); }
</style>
@endpush

@section('content')
<div class="login-box">
    <div class="login-logo">
        @if(isset($tenantSettings) && $tenantSettings?->business_logo)
        <img src="{{ asset('storage/' . $tenantSettings->business_logo) }}" alt="Logo">
        @else
        <div class="brand-icon"><i class="fas fa-wifi"></i></div>
        @endif
        <span class="brand-name">{{ ($tenantSettings ?? null)?->business_name ?? 'Portal Pelanggan' }}</span>
        <span class="brand-sub">Akses layanan internet Anda</span>
    </div>

    <div class="login-card">
        <div class="card-header-custom">
            <i class="fas fa-user-lock mr-2"></i>
            @if(isset($showTenantPicker) && $showTenantPicker)
            Pilih ISP Anda
            @else
            Login Portal Pelanggan
            @endif
        </div>
        <div class="card-body">
            @if($errors->any())
            <div class="alert alert-danger py-2 small">
                @foreach($errors->all() as $err)<div>{{ $err }}</div>@endforeach
            </div>
            @endif

            <form method="POST" action="{{ route('portal.login.post', $portalSlug) }}">
                @csrf
                <div class="form-group">
                    <label class="font-weight-600 small text-muted">Nomor HP</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text" style="border-radius:8px 0 0 8px;border-color:#d0dbe8;background:#f8fbff;">
                                <i class="fas fa-mobile-alt text-muted"></i>
                            </span>
                        </div>
                        <input type="tel" name="nomor_hp"
                            class="form-control @error('nomor_hp') is-invalid @enderror"
                            style="border-radius:0 8px 8px 0;"
                            value="{{ old('nomor_hp') }}"
                            placeholder="Contoh: 0812xxxx"
                            required autofocus>
                        @error('nomor_hp')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="form-group">
                    <label class="font-weight-600 small text-muted">Password</label>
                    <input type="password" name="password"
                        class="form-control @error('password') is-invalid @enderror"
                        required>
                    @error('password')<span class="invalid-feedback">{{ $message }}</span>@enderror
                </div>
                <button type="submit" class="btn btn-primary btn-login btn-block text-white">
                    <i class="fas fa-sign-in-alt mr-1"></i> Masuk
                </button>
            </form>

            <p class="text-muted text-center small mb-0 mt-3">
                <i class="fas fa-info-circle mr-1"></i>
                Belum punya password? Hubungi CS kami.
            </p>
        </div>
    </div>

    @if(($tenantSettings ?? null)?->business_phone)
    <div class="login-footer-note">
        <i class="fas fa-phone-alt mr-1"></i> {{ $tenantSettings->business_phone }}
    </div>
    @endif
</div>
@endsection
