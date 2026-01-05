@extends('layouts.admin')

@section('title', 'Edit Koneksi Mikrotik')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Edit Router [ NAS ]</h4>
            <span class="badge badge-success">Panduan Dasar</span>
        </div>
        <form action="{{ route('mikrotik-connections.update', $mikrotikConnection) }}" method="POST" id="mikrotik-form">
            @csrf
            @method('PUT')
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
                <div class="alert alert-info">
                    <div><strong>Username API:</strong> <span id="generated-username"></span></div>
                    <div><strong>Password API & Secret Radius:</strong> <span id="generated-secret"></span></div>
                </div>
                <div class="mb-3">
                    <button type="button" class="btn btn-primary" id="script-generator-btn" data-toggle="modal" data-target="#scriptGeneratorModal"><i class="fas fa-code"></i> SCRIPT GENERATOR</button>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Authentication Port</label>
                            <input type="number" name="auth_port" value="{{ old('auth_port', $mikrotikConnection->auth_port) }}" class="form-control @error('auth_port') is-invalid @enderror">
                            @error('auth_port')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Accounting Port</label>
                            <input type="number" name="acct_port" value="{{ old('acct_port', $mikrotikConnection->acct_port) }}" class="form-control @error('acct_port') is-invalid @enderror">
                            @error('acct_port')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Nama Router</label>
                        <input type="text" name="name" value="{{ old('name', $mikrotikConnection->name) }}" class="form-control @error('name') is-invalid @enderror" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group col-md-6">
                        <label>Zona Waktu</label>
                        <select name="timezone" class="form-control @error('timezone') is-invalid @enderror">
                            <option value="+07:00 Asia/Jakarta" @selected(old('timezone', $mikrotikConnection->timezone) === '+07:00 Asia/Jakarta')>+07:00 Asia/Jakarta</option>
                            <option value="+08:00 Asia/Makassar" @selected(old('timezone', $mikrotikConnection->timezone) === '+08:00 Asia/Makassar')>+08:00 Asia/Makassar</option>
                        </select>
                        @error('timezone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="form-group">
                    <label>IP Router</label>
                    <input type="text" name="host" value="{{ old('host', $mikrotikConnection->host) }}" class="form-control @error('host') is-invalid @enderror" required>
                    @error('host')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>Port API</label>
                        <input type="number" name="api_port" value="{{ old('api_port', $mikrotikConnection->api_port) }}" class="form-control @error('api_port') is-invalid @enderror">
                        @error('api_port')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group col-md-4">
                        <label>Port API SSL</label>
                        <input type="number" name="api_ssl_port" value="{{ old('api_ssl_port', $mikrotikConnection->api_ssl_port) }}" class="form-control @error('api_ssl_port') is-invalid @enderror">
                        @error('api_ssl_port')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group col-md-4">
                        <label>Timeout (detik)</label>
                        <input type="number" name="api_timeout" value="{{ old('api_timeout', $mikrotikConnection->api_timeout) }}" class="form-control @error('api_timeout') is-invalid @enderror">
                        @error('api_timeout')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>Versi RouterOS</label>
                        <select name="ros_version" class="form-control @error('ros_version') is-invalid @enderror" required>
                            <option value="auto" @selected(old('ros_version', $mikrotikConnection->ros_version) === 'auto')>Auto</option>
                            <option value="7" @selected(old('ros_version', $mikrotikConnection->ros_version) === '7')>ROS 7</option>
                            <option value="6" @selected(old('ros_version', $mikrotikConnection->ros_version) === '6')>ROS 6</option>
                        </select>
                        @error('ros_version')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>Username API</label>
                        <input type="text" name="username" value="{{ old('username', $mikrotikConnection->username) }}" class="form-control @error('username') is-invalid @enderror" required>
                        @error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group col-md-4">
                        <label>Password API</label>
                        <input type="text" name="password" value="{{ old('password', $mikrotikConnection->password) }}" class="form-control @error('password') is-invalid @enderror" required>
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group col-md-4">
                        <label>Secret Radius</label>
                        <input type="text" name="radius_secret" value="{{ old('radius_secret', $mikrotikConnection->radius_secret) }}" class="form-control @error('radius_secret') is-invalid @enderror" placeholder="Kosongkan jika tidak ingin mengganti">
                        @error('radius_secret')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="form-group">
                    <label>URL Info Isolir (Optional)</label>
                    <input type="text" name="isolir_url" value="{{ old('isolir_url', $mikrotikConnection->isolir_url) }}" class="form-control @error('isolir_url') is-invalid @enderror" placeholder="mydomain.com/expired.html">
                    @error('isolir_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <small class="text-muted">Path URL lengkap tanpa http:// atau https://</small>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Deskripsi</label>
                        <textarea name="notes" class="form-control @error('notes') is-invalid @enderror" rows="2" placeholder="Deskripsi">{{ old('notes', $mikrotikConnection->notes) }}</textarea>
                        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="use_ssl" value="1" id="use_ssl" @checked(old('use_ssl', $mikrotikConnection->use_ssl))>
                            <label class="form-check-label" for="use_ssl">Gunakan SSL API</label>
                        </div>
                    </div>
                    <div class="form-group col-md-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" @checked(old('is_active', $mikrotikConnection->is_active))>
                            <label class="form-check-label" for="is_active">Aktif</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-between">
                <a href="{{ route('mikrotik-connections.index') }}" class="btn btn-link">Batal</a>
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">Update</button>
                    <button type="button" class="btn btn-secondary" id="test-connection-btn">Tes Koneksi</button>
                </div>
            </div>
        </form>
    </div>
    <div class="modal fade" id="scriptGeneratorModal" tabindex="-1" aria-labelledby="scriptGeneratorModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="scriptGeneratorModalLabel">Radius Script Generator</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">Salin script berikut ke terminal Mikrotik untuk menyiapkan RADIUS (PPPoE/Hotspot/Login). Secret Radius disamakan dengan Password API.</p>
                    <textarea id="generated-script" class="form-control" rows="10" readonly></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                    <button type="button" class="btn btn-outline-primary" id="copy-script-btn">Copy</button>
                </div>
            </div>
        </div>
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
            const existingUser = document.querySelector('input[name="username"]').value;
            const existingPass = document.querySelector('input[name="password"]').value;
            const existingSecret = document.querySelector('input[name="radius_secret"]').value;

            const user = existingUser || `TMDRadius${randomString(6, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789')}`;
            const pass = existingPass || randomString(10, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*');
            const secret = existingSecret || pass;

            document.querySelector('input[name="username"]').value = user;
            document.querySelector('input[name="password"]').value = pass;
            document.querySelector('input[name="radius_secret"]').value = secret;

            document.getElementById('generated-username').textContent = user;
            document.getElementById('generated-secret').textContent = secret;
        }

        function escapeForRouter(value) {
            return String(value).replace(/(["\\])/g, '\\$1');
        }

        function buildScript() {
            const host = @json(config('radius.server_ip'));
            const apiUser = document.querySelector('input[name="username"]').value;
            const apiPass = document.querySelector('input[name="password"]').value;
            const secret = document.querySelector('input[name="radius_secret"]').value || apiPass;
            const authPort = document.querySelector('input[name="auth_port"]').value || 1812;
            const acctPort = document.querySelector('input[name="acct_port"]').value || 1813;
            const safeUser = escapeForRouter(apiUser);
            const safePass = escapeForRouter(apiPass);
            const safeSecret = escapeForRouter(secret);
            const script = [
                '/radius remove [find comment="added by TMDRadius"]',
                '/user remove [find comment="user for TMDRadius authentication"]',
                '/user group remove [find comment="group for TMDRadius authentication"]',
                `/user group add name="TMDRadius.group" policy=read,write,api,test,policy,sensitive comment="group for TMDRadius authentication"`,
                `/user add name="${safeUser}" group="TMDRadius.group" password="${safePass}" comment="user for TMDRadius authentication"`,
                `/radius add authentication-port=${authPort} accounting-port=${acctPort} timeout=2s comment="added by TMDRadius" service=ppp,hotspot,login address="${host}" secret="${safeSecret}"`,
                `/ip hotspot profile set use-radius=yes radius-accounting=yes radius-interim-update="00:10:00" nas-port-type="wireless-802.11" [find name!=""]`,
                `/ppp aaa set use-radius=yes accounting=yes interim-update="00:10:00"`,
                `/radius incoming set accept=yes port=3799`
            ].join(';');

            document.getElementById('generated-script').value = script;
        }

        document.getElementById('script-generator-btn').addEventListener('click', function () {
            generateCredentials();
            buildScript();
        });

        document.addEventListener('DOMContentLoaded', generateCredentials);

        document.getElementById('copy-script-btn')?.addEventListener('click', function () {
            const textarea = document.getElementById('generated-script');
            textarea.select();
            textarea.setSelectionRange(0, 99999);
            document.execCommand('copy');
        });
    </script>
@endsection
