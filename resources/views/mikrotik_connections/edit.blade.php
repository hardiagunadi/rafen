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
                @if($radiusSecretMismatch ?? false)
                    <div class="alert alert-warning d-flex align-items-center justify-content-between flex-wrap gap-2" id="radius-mismatch-alert">
                        <span>
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            <strong>Secret RADIUS tidak sinkron!</strong>
                            Secret di <code>clients.conf</code> belum cocok dengan DB — MikroTik tidak bisa autentikasi ke RADIUS.
                        </span>
                        <button type="button" class="btn btn-sm btn-warning" id="sync-radius-clients-btn">
                            <i class="fas fa-sync mr-1"></i> Sync RADIUS Clients
                        </button>
                    </div>
                @endif
                <div class="alert alert-info">
                    <div><strong>Username API:</strong> <span id="generated-username">{{ $mikrotikConnection->username }}</span></div>
                    <div><strong>Password API & Secret Radius:</strong> <span id="generated-secret">{{ $mikrotikConnection->password }}</span></div>
                </div>
                <div class="mb-3">
                    <button type="button" class="btn btn-primary" id="script-generator-btn" data-toggle="modal" data-target="#scriptGeneratorModal"><i class="fas fa-code"></i> SCRIPT GENERATOR</button>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Authentication Port</label>
                            <input type="number" name="auth_port" value="{{ old('auth_port', $mikrotikConnection->auth_port ?? 1812) }}" class="form-control @error('auth_port') is-invalid @enderror" placeholder="1812">
                            @error('auth_port')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Accounting Port</label>
                            <input type="number" name="acct_port" value="{{ old('acct_port', $mikrotikConnection->acct_port ?? 1813) }}" class="form-control @error('acct_port') is-invalid @enderror" placeholder="1813">
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

                <div class="form-row align-items-end">
                    <div class="form-group col-md-4">
                        <label>Port API
                            <span class="badge badge-warning ml-1" style="font-size:10px;" title="Port default 8728 mudah dipindai. Gunakan port custom di MikroTik untuk keamanan.">⚠ Keamanan</span>
                        </label>
                        <input type="number" name="api_port" value="{{ old('api_port', $mikrotikConnection->api_port) }}" class="form-control @error('api_port') is-invalid @enderror" placeholder="Contoh: 29412">
                        @error('api_port')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <small class="text-muted">Default: 8728</small>
                    </div>
                    <div class="form-group col-md-4" id="ssl-port-group" style="{{ old('use_ssl', $mikrotikConnection->use_ssl) ? '' : 'display:none' }}">
                        <label>Port API SSL
                            <span class="badge badge-warning ml-1" style="font-size:10px;" title="Port default 8729 mudah dipindai. Gunakan port custom di MikroTik untuk keamanan.">⚠ Keamanan</span>
                        </label>
                        <input type="number" name="api_ssl_port" value="{{ old('api_ssl_port', $mikrotikConnection->api_ssl_port) }}" class="form-control @error('api_ssl_port') is-invalid @enderror" placeholder="Contoh: 29413">
                        @error('api_ssl_port')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <small class="text-muted">Default: 8729</small>
                    </div>
                    <div class="form-group col-md-2">
                        <label>Timeout (detik)</label>
                        <input type="number" name="api_timeout" value="{{ old('api_timeout', $mikrotikConnection->api_timeout) }}" class="form-control @error('api_timeout') is-invalid @enderror">
                        @error('api_timeout')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group col-md-2">
                        <label class="d-block">Gunakan SSL</label>
                        <div class="custom-control custom-switch mt-1">
                            <input type="checkbox" class="custom-control-input" name="use_ssl" value="1" id="use_ssl" @checked(old('use_ssl', $mikrotikConnection->use_ssl))>
                            <label class="custom-control-label" for="use_ssl">SSL API</label>
                        </div>
                    </div>
                </div>
                <div class="alert alert-warning py-2" id="api-port-warning" style="display:none;">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    <strong>Port API masih menggunakan nilai default MikroTik (8728/8729).</strong>
                    Port default mudah dipindai dan menjadi target serangan brute-force.
                    Klik <strong>SCRIPT GENERATOR</strong> di atas untuk mendapatkan perintah ganti port API sekaligus konfigurasi RADIUS.
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

    {{-- ── Tombol buat WireGuard — hanya tampil jika belum punya tunnel --}}
    @if(! $mikrotikConnection->wgPeer)
        <div class="alert alert-info mt-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <span>
                <i class="fas fa-shield-alt mr-1"></i>
                Router ini menggunakan <strong>IP publik</strong> langsung.
                Optionally, gunakan <strong>WireGuard Tunnel</strong> agar RADIUS terhubung via jaringan privat.
            </span>
            <a href="{{ route('settings.wg') }}?router_id={{ $mikrotikConnection->id }}&router_name={{ urlencode($mikrotikConnection->name) }}"
               class="btn btn-sm btn-outline-info text-nowrap">
                <i class="fas fa-plus mr-1"></i> Buat Tunnel WireGuard
            </a>
        </div>
    @endif

    {{-- ── WireGuard Tunnel Card — hanya tampil jika router menggunakan IP tunnel WG --}}
    @php
        $hasWgPeer      = (bool) $mikrotikConnection->wgPeer;
        $connIsOnline   = $mikrotikConnection->is_online ?? null;
        $connUnstable   = (bool) ($mikrotikConnection->ping_unstable ?? false);
        $wgCardClass    = $hasWgPeer
            ? (($connIsOnline && ! $connUnstable) ? 'card-success'
                : ($connUnstable ? 'card-orange' : 'card-secondary'))
            : 'card-secondary';
    @endphp
    <div class="card card-outline mt-3 {{ $wgCardClass }}" id="wg-tunnel-card" style="{{ $hasWgPeer ? '' : 'display:none;' }}">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-network-wired mr-1"></i> WireGuard Tunnel</h5>
            @if($mikrotikConnection->wgPeer)
                @if($connIsOnline === null)
                    <span class="badge badge-secondary">Belum Dicek</span>
                @elseif($connUnstable)
                    <span class="badge badge-warning">Tidak Stabil</span>
                @elseif($connIsOnline)
                    <span class="badge badge-success">Terhubung</span>
                @else
                    <span class="badge badge-danger">Tidak Terhubung</span>
                @endif
            @else
                <span class="badge badge-warning">Belum dikonfigurasi</span>
            @endif
        </div>
        <div class="card-body">
            @if($mikrotikConnection->wgPeer)
                @php $wgPeer = $mikrotikConnection->wgPeer; @endphp
                @if($connIsOnline === null)
                    <div class="alert alert-secondary py-2 d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <span>
                            <i class="fas fa-clock mr-1"></i>
                            Tunnel WireGuard terdaftar. Status koneksi akan dicek otomatis setiap <strong>5 menit</strong> oleh scheduler.
                        </span>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="wg-ping-now-btn">
                            <i class="fas fa-satellite-dish mr-1"></i>Cek Sekarang
                        </button>
                    </div>
                @elseif($connIsOnline && ! $connUnstable)
                    <div class="alert alert-success py-2">
                        <i class="fas fa-check-circle mr-1"></i>
                        Tunnel <strong>terhubung</strong>. Ping reply konsisten.
                        RADIUS NAS menggunakan IP tunnel <strong>{{ $wgPeer->vpn_ip ?? '-' }}</strong>.
                    </div>
                @elseif($connUnstable)
                    <div class="alert alert-warning py-2">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        Tunnel <strong>tidak stabil</strong> — ping putus-nyambung.
                        Periksa konfigurasi WireGuard di MikroTik.
                        RADIUS NAS tetap menggunakan IP tunnel <strong>{{ $wgPeer->vpn_ip ?? '-' }}</strong>.
                    </div>
                @else
                    <div class="alert alert-danger py-2">
                        <i class="fas fa-times-circle mr-1"></i>
                        Tunnel <strong>tidak terhubung</strong> (RTO). Ping gagal melewati threshold.
                        Periksa konfigurasi WireGuard di MikroTik dan pastikan endpoint server dapat dijangkau.
                    </div>
                @endif
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-2"><strong>Nama Peer:</strong> {{ $wgPeer->name }}</div>
                        <div class="mb-2">
                            <strong>IP Tunnel (NAS IP):</strong>
                            <code>{{ $wgPeer->vpn_ip ?? '-' }}</code>
                            <span class="badge badge-info ml-1">RADIUS NAS</span>
                        </div>
                        <div class="mb-2">
                            <strong>Status Peer:</strong>
                            {{ $wgPeer->is_active ? 'Aktif' : 'Nonaktif' }}
                        </div>
                        <div class="mb-2">
                            <strong>Sync Terakhir:</strong>
                            {{ $wgPeer->last_synced_at ? $wgPeer->last_synced_at->format('d/m/Y H:i') : '-' }}
                        </div>
                        @if($mikrotikConnection->last_ping_message)
                            <div class="mb-2">
                                <strong>Detail Ping:</strong>
                                <span class="text-muted">{{ $mikrotikConnection->last_ping_message }}</span>
                            </div>
                        @endif
                    </div>
                    <div class="col-md-6">
                        <div class="mb-2">
                            <strong>Public Key:</strong><br>
                            <code style="font-size:11px; word-break:break-all;">{{ $wgPeer->public_key }}</code>
                        </div>
                    </div>
                </div>
                <a href="{{ route('settings.wg') }}" class="btn btn-sm btn-outline-primary mt-2">
                    <i class="fas fa-cog mr-1"></i> Kelola di Pengaturan WireGuard
                </a>
            @else
                <div class="alert alert-warning py-2">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    <strong>Tunnel WireGuard belum dikonfigurasi.</strong>
                    WireGuard diperlukan agar server RAFEN dapat terhubung ke MikroTik melalui jaringan privat.
                    Setelah tunnel aktif dan ping reply, RADIUS NAS akan otomatis menggunakan IP tunnel.
                </div>
                <p class="text-muted mb-3">
                    Langkah-langkah:
                    <ol>
                        <li>Buat WireGuard peer untuk router ini di halaman Pengaturan WireGuard</li>
                        <li>Copy script WireGuard ke terminal MikroTik</li>
                        <li>Verifikasi tunnel aktif (ping reply dari server ke MikroTik)</li>
                        <li>Sync RADIUS dari <a href="{{ route('settings.freeradius') }}">Pengaturan FreeRADIUS</a> — NAS IP akan otomatis menjadi IP tunnel</li>
                    </ol>
                </p>
                <a href="{{ route('settings.wg') }}?router_id={{ $mikrotikConnection->id }}&router_name={{ urlencode($mikrotikConnection->name) }}"
                   class="btn btn-warning">
                    <i class="fas fa-plus mr-1"></i> Buat Tunnel WireGuard untuk Router Ini
                </a>
            @endif
        </div>
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
                    @if($mikrotikConnection->wgPeer)
                        <div class="alert alert-info py-2 mb-2">
                            <i class="fas fa-network-wired mr-1"></i>
                            Router ini menggunakan <strong>WireGuard Tunnel</strong>.
                            Login address RADIUS menggunakan <strong>IP gateway tunnel: {{ config('wg.server_ip', '10.0.0.1') }}</strong>
                            (bukan IP publik server).
                        </div>
                    @endif
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

            const user = existingUser || `TMD-${randomString(6, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ')}`;
            const pass = existingPass || randomString(17, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789');
            const secret = existingSecret || pass;

            document.querySelector('input[name="username"]').value = user;
            document.querySelector('input[name="password"]').value = pass;
            document.querySelector('input[name="radius_secret"]').value = secret;

            document.getElementById('generated-username').textContent = user;
            document.getElementById('generated-secret').textContent = pass;
        }

        function escapeForRouter(value) {
            return String(value).replace(/(["\\])/g, '\\$1');
        }

        function buildScript() {
            const host = @json($radiusHost);
            const apiUser = document.querySelector('input[name="username"]').value;
            const apiPass = document.querySelector('input[name="password"]').value;
            const secret = document.querySelector('input[name="radius_secret"]').value || apiPass;
            const authPort = document.querySelector('input[name="auth_port"]').value || 1812;
            const acctPort = document.querySelector('input[name="acct_port"]').value || 1813;
            const apiPort = parseInt(document.querySelector('input[name="api_port"]').value, 10) || 8728;
            const apiSslPort = parseInt(document.querySelector('input[name="api_ssl_port"]').value, 10) || 8729;
            const safeUser = escapeForRouter(apiUser);
            const safePass = escapeForRouter(apiPass);
            const safeSecret = escapeForRouter(secret);

            const lines = [];

            // Port API non-default: tambahkan perintah ganti port
            if (apiPort !== 8728 || apiSslPort !== 8729) {
                lines.push(`# --- Ganti port API (keamanan: hindari port default) ---`);
                if (apiPort !== 8728) {
                    lines.push(`/ip service set api port=${apiPort}`);
                }
                if (apiSslPort !== 8729) {
                    lines.push(`/ip service set api-ssl port=${apiSslPort}`);
                }
                lines.push('');
            }

            lines.push(
                '# --- Konfigurasi user API & RADIUS ---',
                '/radius remove [find comment="added by TMDRadius"]',
                '/user remove [find comment="user for TMDRadius authentication"]',
                '/user group remove [find comment="group for TMDRadius authentication"]',
                `/user group add name="TMDRadius.group" policy=read,write,api,test,policy,sensitive comment="group for TMDRadius authentication"`,
                `/user add name="${safeUser}" group="TMDRadius.group" password="${safePass}" comment="user for TMDRadius authentication"`,
                `/radius add authentication-port=${authPort} accounting-port=${acctPort} timeout=2s comment="added by TMDRadius" service=ppp,hotspot,login address="${host}" secret="${safeSecret}"`,
                `/ip hotspot profile set use-radius=yes radius-accounting=yes radius-interim-update="00:10:00" nas-port-type="wireless-802.11" [find name!=""]`,
                `/ppp aaa set use-radius=yes accounting=yes interim-update="00:10:00"`,
                `/radius incoming set accept=yes port=3799`
            );

            document.getElementById('generated-script').value = lines.join('\n');
        }

        // Toggle SSL port group
        const sslToggle = document.getElementById('use_ssl');
        const sslPortGroup = document.getElementById('ssl-port-group');
        if (sslToggle && sslPortGroup) {
            sslToggle.addEventListener('change', function () {
                sslPortGroup.style.display = sslToggle.checked ? '' : 'none';
                checkApiPortWarning();
            });
        }

        function checkApiPortWarning() {
            const apiPort = parseInt(document.querySelector('input[name="api_port"]').value, 10);
            const apiSslPort = parseInt(document.querySelector('input[name="api_ssl_port"]').value, 10);
            const warning = document.getElementById('api-port-warning');
            if (!warning) return;
            const sslActive = sslToggle ? sslToggle.checked : false;
            warning.style.display = (apiPort === 8728 || (sslActive && apiSslPort === 8729)) ? '' : 'none';
        }

        function init() {
            generateCredentials();
            buildScript();
            checkApiPortWarning();

            var scriptBtn = document.getElementById('script-generator-btn');
            if (scriptBtn) {
                scriptBtn.addEventListener('click', function () {
                    generateCredentials();
                    buildScript();
                });
            }

            var modal = document.getElementById('scriptGeneratorModal');
            if (modal) {
                modal.addEventListener('show.bs.modal', function () {
                    generateCredentials();
                    buildScript();
                });
            }

            var apiPortInput = document.querySelector('input[name="api_port"]');
            var apiSslPortInput = document.querySelector('input[name="api_ssl_port"]');
            if (apiPortInput) apiPortInput.addEventListener('input', checkApiPortWarning);
            if (apiSslPortInput) apiSslPortInput.addEventListener('input', checkApiPortWarning);
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }

        document.getElementById('copy-script-btn')?.addEventListener('click', function () {
            const textarea = document.getElementById('generated-script');
            textarea.select();
            textarea.setSelectionRange(0, 99999);
            document.execCommand('copy');
        });

        // ── Sync RADIUS Clients ───────────────────────────────────────────────
        var syncRadiusBtn = document.getElementById('sync-radius-clients-btn');
        if (syncRadiusBtn) {
            syncRadiusBtn.addEventListener('click', function () {
                var btn = this;
                var origHtml = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Menyinkron…';

                fetch('{{ route('mikrotik-connections.radius-sync-clients') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    btn.disabled = false;
                    btn.innerHTML = origHtml;
                    if (data.error) {
                        alert('Sync gagal: ' + data.error);
                    } else {
                        // Sembunyikan alert mismatch
                        var alert = document.getElementById('radius-mismatch-alert');
                        if (alert) alert.style.display = 'none';
                        btn.innerHTML = '<i class="fas fa-check mr-1"></i>Tersinkron';
                        btn.classList.remove('btn-warning');
                        btn.classList.add('btn-success');
                    }
                })
                .catch(function () {
                    btn.disabled = false;
                    btn.innerHTML = origHtml;
                    alert('Gagal menghubungi server.');
                });
            });
        }

        // ── Cek Sekarang (ping manual) ────────────────────────────────────────
        const pingNowBtn = document.getElementById('wg-ping-now-btn');
        if (pingNowBtn) {
            pingNowBtn.addEventListener('click', function () {
                const btn = this;
                const origHtml = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Mengecek…';

                fetch('{{ route('mikrotik-connections.ping-now', $mikrotikConnection) }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    btn.disabled = false;
                    btn.innerHTML = origHtml;
                    if (data.error) {
                        alert('Ping gagal: ' + data.error);
                        return;
                    }
                    // Reload halaman agar status card diperbarui
                    window.location.reload();
                })
                .catch(function () {
                    btn.disabled = false;
                    btn.innerHTML = origHtml;
                    alert('Gagal menghubungi server.');
                });
            });
        }
    </script>
@endsection
