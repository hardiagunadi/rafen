<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - RAFEN ISP Manager</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
</head>
<body class="hold-transition register-page">
<div class="register-box" style="width: 450px;">
    <div class="register-logo">
        <b>RAFEN</b> ISP Manager
    </div>
    <div class="card">
        <div class="card-body register-card-body">
            <p class="login-box-msg">Daftar Akun Baru</p>

            <div class="alert alert-info">
                <i class="fas fa-gift"></i> Dapatkan <strong>14 hari trial gratis</strong> untuk mencoba semua fitur!
            </div>

            @if ($errors->any())
                <div class="alert alert-danger">{{ $errors->first() }}</div>
            @endif

            <form action="{{ route('register') }}" method="POST">
                @csrf
                <div class="input-group mb-3">
                    <input type="text" name="name" class="form-control" placeholder="Nama Lengkap *" required value="{{ old('name') }}">
                    <div class="input-group-append">
                        <div class="input-group-text"><span class="fas fa-user"></span></div>
                    </div>
                </div>
                <div class="input-group mb-3">
                    <input type="email" name="email" class="form-control" placeholder="Email *" required value="{{ old('email') }}">
                    <div class="input-group-append">
                        <div class="input-group-text"><span class="fas fa-envelope"></span></div>
                    </div>
                </div>
                <div class="input-group mb-3">
                    <input type="text" name="phone" class="form-control" placeholder="Nomor HP (Opsional)" value="{{ old('phone') }}">
                    <div class="input-group-append">
                        <div class="input-group-text"><span class="fas fa-phone"></span></div>
                    </div>
                </div>
                <div class="input-group mb-3">
                    <input type="text" name="company_name" class="form-control" placeholder="Nama ISP / Perusahaan (Opsional)" value="{{ old('company_name') }}">
                    <div class="input-group-append">
                        <div class="input-group-text"><span class="fas fa-building"></span></div>
                    </div>
                </div>
                <div class="input-group mb-3">
                    <input type="password" name="password" class="form-control" placeholder="Password *" required>
                    <div class="input-group-append">
                        <div class="input-group-text"><span class="fas fa-lock"></span></div>
                    </div>
                </div>
                <div class="input-group mb-3">
                    <input type="password" name="password_confirmation" class="form-control" placeholder="Konfirmasi Password *" required>
                    <div class="input-group-append">
                        <div class="input-group-text"><span class="fas fa-lock"></span></div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-user-plus"></i> Daftar Sekarang
                        </button>
                    </div>
                </div>
            </form>

            <hr>

            <div class="text-center">
                <p class="mb-1">Fitur yang akan Anda dapatkan:</p>
                <small class="text-muted">
                    <i class="fas fa-check text-success"></i> Manajemen Mikrotik Unlimited |
                    <i class="fas fa-check text-success"></i> FreeRADIUS Integration |
                    <i class="fas fa-check text-success"></i> Invoicing Otomatis
                </small>
            </div>

            <hr>

            <p class="mb-0 text-center">
                <a href="{{ route('login') }}">Sudah punya akun? <strong>Login</strong></a>
            </p>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
