<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - RAFEN Manager</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/svg+xml" href="{{ asset('branding/rafen-favicon.svg') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('branding/favicon-32.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('branding/favicon-180.png') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <style>
        .auth-wordmark {
            width: min(280px, 88%);
            margin: 0 auto;
            filter: drop-shadow(0 8px 18px rgba(11, 42, 74, .2));
        }
    </style>
</head>
<body class="hold-transition login-page">
@php
    $serverLoadTimeMs = defined('LARAVEL_START')
        ? (microtime(true) - LARAVEL_START) * 1000
        : null;
@endphp
<div class="login-box">
    <div class="login-logo">
        <img src="{{ asset('branding/rafen-wordmark.svg') }}" alt="Rafen Wordmark" class="auth-wordmark">
    </div>
    <div class="card">
        <div class="card-body login-card-body">
            <p class="login-box-msg">Masuk ke akun Anda</p>
            @if ($errors->any())
                <div class="alert alert-danger">{{ $errors->first() }}</div>
            @endif
            @if (session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif
            <form action="{{ route('login') }}" method="POST">
                @csrf
                <div class="input-group mb-3">
                    <input type="email" name="email" class="form-control" placeholder="Email" required value="{{ old('email') }}">
                    <div class="input-group-append">
                        <div class="input-group-text"><span class="fas fa-envelope"></span></div>
                    </div>
                </div>
                <div class="input-group mb-3">
                    <input type="password" name="password" class="form-control" placeholder="Password" required>
                    <div class="input-group-append">
                        <div class="input-group-text"><span class="fas fa-lock"></span></div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-8">
                        <div class="icheck-primary">
                            <input type="checkbox" id="remember" name="remember">
                            <label for="remember">Remember Me</label>
                        </div>
                    </div>
                    <div class="col-4">
                        <button type="submit" class="btn btn-primary btn-block">Sign In</button>
                    </div>
                </div>
            </form>
            <p class="mb-0 mt-3">
                <a href="{{ route('register') }}" class="text-center">Daftar akun</a>
            </p>
        </div>
    </div>
</div>
@if($serverLoadTimeMs !== null)
    <div class="text-center text-muted small mt-2">Load Time: {{ number_format($serverLoadTimeMs, 1, '.', '') }} ms</div>
@endif
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
