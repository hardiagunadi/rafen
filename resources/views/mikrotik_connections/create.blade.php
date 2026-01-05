@extends('layouts.admin')

@section('title', 'Tambah Koneksi Mikrotik')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Tambah Router [ NAS ]</h4>
            <span class="badge badge-success">Panduan Dasar</span>
        </div>
        <form action="{{ route('mikrotik-connections.store') }}" method="POST" id="mikrotik-form">
            @csrf
            <div class="card-body">
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <strong>Data belum valid:</strong>
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Authentication Port</label>
                            <input type="number" name="auth_port" value="{{ old('auth_port', 1812) }}" class="form-control @error('auth_port') is-invalid @enderror">
                            @error('auth_port')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Accounting Port</label>
                            <input type="number" name="acct_port" value="{{ old('acct_port', 1813) }}" class="form-control @error('acct_port') is-invalid @enderror">
                            @error('acct_port')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Nama Router</label>
                        <input type="text" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" placeholder="NAS SITE-1" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group col-md-6">
                        <label>Zona Waktu</label>
                        <select name="timezone" class="form-control @error('timezone') is-invalid @enderror">
                            <option value="+07:00 Asia/Jakarta" @selected(old('timezone', '+07:00 Asia/Jakarta') === '+07:00 Asia/Jakarta')>+07:00 Asia/Jakarta</option>
                            <option value="+08:00 Asia/Makassar" @selected(old('timezone') === '+08:00 Asia/Makassar')>+08:00 Asia/Makassar</option>
                        </select>
                        @error('timezone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="form-group">
                    <label>IP Router</label>
                    <input type="text" name="host" value="{{ old('host') }}" class="form-control @error('host') is-invalid @enderror" placeholder="192.168.1.1 atau mydomain.com" required>
                    @error('host')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>Port API</label>
                        <input type="number" name="api_port" value="{{ old('api_port', 8728) }}" class="form-control @error('api_port') is-invalid @enderror">
                        @error('api_port')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group col-md-4">
                        <label>Port API SSL</label>
                        <input type="number" name="api_ssl_port" value="{{ old('api_ssl_port', 8729) }}" class="form-control @error('api_ssl_port') is-invalid @enderror">
                        @error('api_ssl_port')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group col-md-4">
                        <label>Timeout (detik)</label>
                        <input type="number" name="api_timeout" value="{{ old('api_timeout', 10) }}" class="form-control @error('api_timeout') is-invalid @enderror">
                        @error('api_timeout')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>Versi RouterOS</label>
                        <select name="ros_version" class="form-control @error('ros_version') is-invalid @enderror" required>
                            <option value="auto" @selected(old('ros_version', 'auto') === 'auto')>Auto</option>
                            <option value="7" @selected(old('ros_version') === '7')>ROS 7</option>
                            <option value="6" @selected(old('ros_version') === '6')>ROS 6</option>
                        </select>
                        @error('ros_version')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="alert alert-info">
                    Kredensial API & Secret RADIUS akan dibuat otomatis setelah router disimpan dan bisa dilihat di halaman Edit Router.
                </div>
                <input type="hidden" name="username" value="{{ old('username') }}" id="auto-username">
                <input type="hidden" name="password" value="{{ old('password') }}" id="auto-password">
                <input type="hidden" name="radius_secret" value="{{ old('radius_secret') }}" id="auto-secret">

                <div class="form-group">
                    <label>URL Info Isolir (Optional)</label>
                    <input type="text" name="isolir_url" value="{{ old('isolir_url') }}" class="form-control @error('isolir_url') is-invalid @enderror" placeholder="mydomain.com/expired.html">
                    @error('isolir_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <small class="text-muted">Path URL lengkap tanpa http:// atau https://</small>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Deskripsi</label>
                        <textarea name="notes" class="form-control @error('notes') is-invalid @enderror" rows="2" placeholder="Deskripsi">{{ old('notes') }}</textarea>
                        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="use_ssl" value="1" id="use_ssl" @checked(old('use_ssl'))>
                            <label class="form-check-label" for="use_ssl">Gunakan SSL API</label>
                        </div>
                    </div>
                    <div class="form-group col-md-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" @checked(old('is_active', true))>
                            <label class="form-check-label" for="is_active">Aktif</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-between">
                <a href="{{ route('mikrotik-connections.index') }}" class="btn btn-link">Batal</a>
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">Tambahkan Router</button>
                    <button type="button" class="btn btn-secondary" id="test-connection-btn">Tes Koneksi</button>
                </div>
            </div>
        </form>
    </div>

    <script>
        document.getElementById('test-connection-btn').addEventListener('click', function () {
            const host = document.querySelector('input[name="host"]').value;
            const apiTimeout = document.querySelector('input[name="api_timeout"]').value || 10;
            const apiPort = document.querySelector('input[name="api_port"]').value || 8728;
            const apiSslPort = document.querySelector('input[name="api_ssl_port"]').value || 8729;
            const useSsl = document.querySelector('input[name="use_ssl"]').checked;
            const resultBox = document.getElementById('test-connection-result') || (function () {
                const alert = document.createElement('div');
                alert.id = 'test-connection-result';
                alert.className = 'alert mt-3';
                document.querySelector('.card').appendChild(alert);
                return alert;
            })();

            resultBox.className = 'alert mt-3 alert-info';
            resultBox.textContent = 'Menguji koneksi...';

            fetch('{{ route('mikrotik-connections.test') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ host, api_timeout: apiTimeout, api_port: apiPort, api_ssl_port: apiSslPort, use_ssl: useSsl })
            }).then(async response => {
                const data = await response.json();
                if (response.ok && data.success) {
                    resultBox.className = 'alert mt-3 alert-success';
                    resultBox.textContent = data.message;
                } else {
                    resultBox.className = 'alert mt-3 alert-danger';
                    const extra = data.port_open === false ? ' Port API tertutup atau salah.' : '';
                    resultBox.textContent = (data.message || 'Koneksi gagal.') + extra;
                }
            }).catch(() => {
                resultBox.className = 'alert mt-3 alert-danger';
                resultBox.textContent = 'Gagal menguji koneksi.';
            });
        });

        function randomString(length, charset) {
            let result = '';
            const chars = charset.split('');
            for (let i = 0; i < length; i++) {
                result += chars[Math.floor(Math.random() * chars.length)];
            }
            return result;
        }

        function generateCredentials() {
            const userField = document.getElementById('auto-username');
            const passField = document.getElementById('auto-password');
            const secretField = document.getElementById('auto-secret');

            if (! userField.value) {
                userField.value = `TMDRadius${randomString(6, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789')}`;
            }
            if (! passField.value) {
                passField.value = randomString(10, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*');
            }
            if (! secretField.value) {
                secretField.value = passField.value;
            }
        }

        document.addEventListener('DOMContentLoaded', generateCredentials);
    </script>
@endsection
