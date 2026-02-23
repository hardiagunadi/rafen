@extends('layouts.admin')

@section('title', 'Pengaturan OpenVPN')

@section('content')
    <div class="card mb-4">
        <div class="card-header">
            <h4 class="mb-0">Informasi Koneksi OpenVPN</h4>
        </div>
        <div class="card-body">
            <div id="ovpn-alert" style="display:none;" class="alert mb-3"></div>
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-2">
                        <strong>IP/Host:</strong>
                        @if ($ovpn['host'] !== '')
                            <span class="text-dark">{{ $ovpn['host'] }}</span>
                        @elseif ($detectedIp !== null)
                            <span class="text-success font-weight-bold">{{ $detectedIp }}</span>
                            <span class="badge badge-warning ml-1" title="IP publik terdeteksi otomatis. Set OVPN_HOST di .env untuk menetapkan permanen.">Auto-detect</span>
                        @else
                            <span class="text-danger">-</span>
                            <small class="text-muted ml-1">(set OVPN_HOST di .env)</small>
                        @endif
                    </div>
                    <div class="mb-2"><strong>Port:</strong> {{ $ovpn['port'] !== '' ? $ovpn['port'] : '-' }}</div>
                    <div class="mb-2"><strong>Proto:</strong> {{ $ovpn['proto'] !== '' ? strtoupper($ovpn['proto']) : '-' }}</div>
                    <div class="mb-2"><strong>Network:</strong> {{ $ovpn['network'] !== '' ? $ovpn['network'] : '-' }}</div>
                    <div class="mb-2"><strong>Netmask:</strong> {{ $ovpn['netmask'] !== '' ? $ovpn['netmask'] : '-' }}</div>
                    <div class="mb-2">
                        <strong>Pool IP:</strong>
                        {{ $ovpn['pool_start'] !== '' ? $ovpn['pool_start'] : '-' }}
                        -
                        {{ $ovpn['pool_end'] !== '' ? $ovpn['pool_end'] : '-' }}
                    </div>
                    <div class="mb-2"><strong>Route Dst:</strong> {{ $ovpn['route_dst'] !== '' ? $ovpn['route_dst'] : '-' }}</div>
                </div>
                <div class="col-md-6">
                    <div class="mb-2"><strong>Username:</strong> {{ $ovpn['username'] !== '' ? $ovpn['username'] : '-' }}</div>
                    <div class="mb-2"><strong>Password:</strong> {{ $ovpn['password'] !== '' ? $ovpn['password'] : '-' }}</div>
                </div>
            </div>
            @if ($ovpn['host'] === '' && $detectedIp !== null)
                <div class="alert alert-warning mb-0 mt-3 py-2">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    IP publik <strong>{{ $detectedIp }}</strong> terdeteksi otomatis dari server.
                    Tambahkan <code>OVPN_HOST={{ $detectedIp }}</code> di file <code>.env</code> untuk menetapkannya secara permanen.
                </div>
            @endif
            <div class="alert alert-info mb-0 mt-3">
                Untuk banyak Mikrotik, disarankan membuat client OpenVPN per router dan atur IP lokal
                statis via CCD (client-config-dir) agar tiap router punya IP berbeda.
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h4 class="mb-0">Tambah Client OpenVPN</h4>
        </div>
        <form id="ovpn-store-form" action="{{ route('settings.ovpn.clients.store') }}" method="POST">
            @csrf
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>Router (NAS)</label>
                        <select name="mikrotik_connection_id" class="form-control @error('mikrotik_connection_id') is-invalid @enderror">
                            <option value="">Tanpa Router (gunakan VPN saja)</option>
                            @foreach($routers as $router)
                                <option value="{{ $router->id }}" @selected(old('mikrotik_connection_id') == $router->id)>{{ $router->name }}</option>
                            @endforeach
                        </select>
                        @error('mikrotik_connection_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group col-md-4">
                        <label>Nama Client</label>
                        <input type="text" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group col-md-4">
                        <label>Common Name (opsional)</label>
                        <input type="text" name="common_name" value="{{ old('common_name') }}" class="form-control @error('common_name') is-invalid @enderror" placeholder="auto jika kosong">
                        @error('common_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>IP VPN (opsional)</label>
                        <input type="text" name="vpn_ip" value="{{ old('vpn_ip') }}" class="form-control @error('vpn_ip') is-invalid @enderror" placeholder="auto jika kosong">
                        @error('vpn_ip')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group col-md-4">
                        <label>Username (opsional)</label>
                        <input type="text" name="username" value="{{ old('username') }}" class="form-control @error('username') is-invalid @enderror" placeholder="auto jika kosong">
                        @error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group col-md-4">
                        <label>Password (opsional)</label>
                        <input type="text" name="password" value="{{ old('password') }}" class="form-control @error('password') is-invalid @enderror" placeholder="auto jika kosong">
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>Status</label>
                        <select name="is_active" class="form-control @error('is_active') is-invalid @enderror">
                            <option value="1" @selected(old('is_active', '1') == '1')>Aktif</option>
                            <option value="0" @selected(old('is_active') == '0')>Nonaktif</option>
                        </select>
                        @error('is_active')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-between align-items-center">
                <span class="text-muted">IP VPN akan di-assign otomatis jika kosong.</span>
                <button type="submit" class="btn btn-primary" id="ovpn-store-btn">Simpan</button>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-header">
            <h4 class="mb-0">Daftar Client OpenVPN</h4>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped mb-0">
                    <thead>
                    <tr>
                        <th>Router</th>
                        <th>Nama</th>
                        <th>Common Name</th>
                        <th>IP VPN</th>
                        <th>Username</th>
                        <th>Password</th>
                        <th>Status</th>
                        <th>Sync Terakhir</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                    </thead>
                    <tbody id="ovpn-clients-tbody">
                    @forelse($clients as $client)
                        @php
                            $ovpnHost = $ovpn['host'] !== '' ? $ovpn['host'] : '<IP/Host>';
                            $ovpnPort = $ovpn['port'] !== '' ? $ovpn['port'] : '1194';
                            $ovpnProto = $ovpn['proto'] !== '' ? $ovpn['proto'] : 'tcp';
                            $ovpnUser = $client->username ?: '<username>';
                            $ovpnPass = $client->password ?: '<password>';
                            $ovpnName = 'ovpn-'.$client->common_name;
                            $routeDst = $ovpn['route_dst'];
                            $routeComment = 'added by TMDRadius '.$ovpnName;
                            $protoWarning = ($ovpnProto === 'udp')
                                ? '# PERINGATAN: Proto UDP dapat menyebabkan "poll error" di Mikrotik.'
                                : null;

                            // ROS v6: hanya support TCP, cipher terbatas
                            $protoV6 = 'tcp'; // ROS v6 hanya support TCP untuk ovpn-client
                            $cleanupLines = [
                                '/interface ovpn-client remove [find name="'.$ovpnName.'"]',
                                '/interface sstp-client remove [find name="'.$ovpnName.'"]',
                                '/interface l2tp-client remove [find name="'.$ovpnName.'"]',
                                '/interface pptp-client remove [find name="'.$ovpnName.'"]',
                                '/routing table remove [find name="'.$ovpnName.'"]',
                                '/routing rule remove [find comment="'.$routeComment.'"]',
                                '/ip route remove [find comment="'.$routeComment.'"]',
                            ];

                            // Helper: buat blok routing
                            $routingMultiLines = [];
                            $routingSingleLines = [];
                            if ($routeDst !== '') {
                                $routingMultiLines = [
                                    '',
                                    '# --- Tambah routing ke '.$routeDst.' ---',
                                    '/routing table add name="'.$ovpnName.'" fib',
                                    '/routing rule add \\',
                                    '    dst-address="'.$routeDst.'" \\',
                                    '    action=lookup-only-in-table \\',
                                    '    table="'.$ovpnName.'" \\',
                                    '    comment="'.$routeComment.'"',
                                    '/ip route add \\',
                                    '    disabled=no \\',
                                    '    gateway="'.$ovpnName.'" \\',
                                    '    dst-address="'.$routeDst.'" \\',
                                    '    routing-table="'.$ovpnName.'" \\',
                                    '    distance=2 \\',
                                    '    comment="'.$routeComment.'"',
                                ];
                                $routingSingleLines = [
                                    '/routing table add name="'.$ovpnName.'" fib',
                                    '/routing rule add dst-address="'.$routeDst.'" action=lookup-only-in-table table="'.$ovpnName.'" comment="'.$routeComment.'"',
                                    '/ip route add disabled=no gateway="'.$ovpnName.'" dst-address="'.$routeDst.'" routing-table="'.$ovpnName.'" distance=2 comment="'.$routeComment.'"',
                                ];
                            }

                            // === Script ROS v6 (TCP only, minimal params - biarkan Mikrotik negotiate) ===
                            $v6MultiLines = array_merge([
                                '# ============================================',
                                '# OVPN Client : '.$client->name,
                                '# RouterOS    : v6 (TCP only)',
                                '# IP VPN      : '.($client->vpn_ip ?? '-'),
                                '# Interface   : '.$ovpnName,
                                '# ============================================',
                                '',
                                '# --- Hapus interface lama (jika ada) ---',
                            ], $cleanupLines, [
                                '',
                                '# --- Buat interface OVPN client (ROS v6) ---',
                                '/interface ovpn-client add \\',
                                '    disabled=no \\',
                                '    connect-to="'.$ovpnHost.'" \\',
                                '    name="'.$ovpnName.'" \\',
                                '    user="'.$ovpnUser.'" \\',
                                '    password="'.$ovpnPass.'" \\',
                                '    comment="IPADDR : '.($client->vpn_ip ?? '-').'"',
                            ], $routingMultiLines);

                            $v6SingleAdd = '/interface ovpn-client add disabled=no connect-to="'.$ovpnHost.'" name="'.$ovpnName.'" user="'.$ovpnUser.'" password="'.$ovpnPass.'" comment="IPADDR : '.($client->vpn_ip ?? '-').'"';
                            $v6SingleLines = array_merge($cleanupLines, [$v6SingleAdd], $routingSingleLines);

                            // === Script ROS v7 (minimal params, biarkan Mikrotik negotiate cipher/auth) ===
                            $v7MultiLines = array_merge([
                                '# ============================================',
                                '# OVPN Client : '.$client->name,
                                '# RouterOS    : v7',
                                '# IP VPN      : '.($client->vpn_ip ?? '-'),
                                '# Interface   : '.$ovpnName,
                                '# Proto       : '.strtoupper($ovpnProto),
                                '# ============================================',
                                ...($protoWarning ? [$protoWarning] : []),
                                '',
                                '# --- Hapus interface lama (jika ada) ---',
                            ], $cleanupLines, [
                                '',
                                '# --- Buat interface OVPN client (ROS v7) ---',
                                '/interface ovpn-client add \\',
                                '    disabled=no \\',
                                '    connect-to="'.$ovpnHost.'" \\',
                                '    name="'.$ovpnName.'" \\',
                                '    user="'.$ovpnUser.'" \\',
                                '    password="'.$ovpnPass.'" \\',
                                '    comment="IPADDR : '.($client->vpn_ip ?? '-').'"',
                            ], $routingMultiLines);

                            $v7SingleAdd = '/interface ovpn-client add disabled=no connect-to="'.$ovpnHost.'" name="'.$ovpnName.'" user="'.$ovpnUser.'" password="'.$ovpnPass.'" comment="IPADDR : '.($client->vpn_ip ?? '-').'"';
                            $v7SingleLines = array_merge($cleanupLines, [$v7SingleAdd], $routingSingleLines);

                            $scriptData = [
                                'v6multi'  => implode("\n", $v6MultiLines),
                                'v6single' => implode(';', $v6SingleLines).';',
                                'v7multi'  => implode("\n", $v7MultiLines),
                                'v7single' => implode(';', $v7SingleLines).';',
                                'name'     => $client->name,
                                'proto'    => $ovpnProto,
                            ];
                        @endphp
                        <tr id="ovpn-row-{{ $client->id }}"
                            data-client-id="{{ $client->id }}"
                            data-destroy-url="{{ route('settings.ovpn.clients.destroy', $client) }}"
                            data-sync-url="{{ route('settings.ovpn.clients.sync', $client) }}"
                            data-update-url="{{ route('settings.ovpn.clients.update', $client) }}">
                            <td class="ovpn-col-router">{{ $client->mikrotikConnection?->name ?? '-' }}</td>
                            <td class="ovpn-col-name">{{ $client->name }}</td>
                            <td class="ovpn-col-cn">{{ $client->common_name }}</td>
                            <td class="ovpn-col-ip">{{ $client->vpn_ip ?? '-' }}</td>
                            <td class="ovpn-col-user">{{ $client->username ?? '-' }}</td>
                            <td class="ovpn-col-pass">{{ $client->password ?? '-' }}</td>
                            <td class="ovpn-col-status">{{ $client->is_active ? 'Aktif' : 'Nonaktif' }}</td>
                            <td class="ovpn-col-sync">{{ $client->last_synced_at?->format('Y-m-d H:i:s') ?? '-' }}</td>
                            <td class="text-right">
                                <button type="button" class="btn btn-sm btn-outline-success ovpn-sync-btn">Sync CCD</button>
                                <button type="button" class="btn btn-sm btn-outline-primary" data-toggle="collapse" data-target="#ovpn-edit-{{ $client->id }}">
                                    Edit
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary ovpn-script-btn" data-toggle="modal" data-target="#ovpn-script-modal" data-script='@json($scriptData)'>
                                    Script Mikrotik
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger ovpn-delete-btn">Delete</button>
                            </td>
                        </tr>
                        <tr class="collapse" id="ovpn-edit-{{ $client->id }}">
                            <td colspan="9">
                                <form class="ovpn-update-form">
                                    @csrf
                                    <div class="form-row">
                                        <div class="form-group col-md-4">
                                            <label>Nama</label>
                                            <input type="text" name="name" value="{{ $client->name }}" class="form-control" required>
                                        </div>
                                        <div class="form-group col-md-4">
                                            <label>Common Name</label>
                                            <input type="text" name="common_name" value="{{ $client->common_name }}" class="form-control" required>
                                        </div>
                                        <div class="form-group col-md-4">
                                            <label>IP VPN</label>
                                            <input type="text" name="vpn_ip" value="{{ $client->vpn_ip }}" class="form-control">
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group col-md-4">
                                            <label>Username</label>
                                            <input type="text" name="username" value="{{ $client->username }}" class="form-control" required>
                                        </div>
                                        <div class="form-group col-md-4">
                                            <label>Password</label>
                                            <input type="text" name="password" value="{{ $client->password }}" class="form-control" required>
                                        </div>
                                        <div class="form-group col-md-4">
                                            <label>Status</label>
                                            <select name="is_active" class="form-control">
                                                <option value="1" @selected($client->is_active)>Aktif</option>
                                                <option value="0" @selected(!$client->is_active)>Nonaktif</option>
                                            </select>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-sm">Simpan Perubahan</button>
                                    <button type="button" class="btn btn-secondary btn-sm" data-toggle="collapse" data-target="#ovpn-edit-{{ $client->id }}">Batal</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center p-4">Belum ada client OpenVPN.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="ovpn-script-modal" tabindex="-1" aria-labelledby="ovpn-script-modal-label" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="ovpn-script-modal-label">Script Mikrotik</h5>
                        <small class="text-muted" id="ovpn-script-client-name"></small>
                    </div>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label class="font-weight-bold mb-1" for="ovpn-host-override">
                            <i class="fas fa-server mr-1"></i>IP / Host Server OVPN
                        </label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-network-wired"></i></span>
                            </div>
                            <input type="text" id="ovpn-host-override" class="form-control"
                                placeholder="Contoh: 203.0.113.1 atau vpn.domain.com"
                                value="{{ $ovpn['host'] !== '' ? $ovpn['host'] : ($detectedIp ?? '') }}">
                            <div class="input-group-append">
                                <span class="input-group-text text-muted" style="font-size:12px;" id="ovpn-host-status"></span>
                            </div>
                        </div>
                        <small class="text-muted">Ubah IP/Host di sini — script akan diperbarui otomatis.</small>
                    </div>

                    {{-- Pilihan versi ROS --}}
                    <div class="d-flex align-items-center mb-3" style="gap:8px;">
                        <span class="font-weight-bold" style="white-space:nowrap;">RouterOS:</span>
                        <div class="btn-group btn-group-sm" id="ovpn-ros-version">
                            <button type="button" class="btn btn-primary active" data-ros="v6">ROS v6</button>
                            <button type="button" class="btn btn-outline-primary" data-ros="v7">ROS v7</button>
                        </div>
                        <small class="text-muted ml-2" id="ovpn-ros-hint">
                            ROS v6: hanya mendukung TCP &mdash; pastikan server OpenVPN di-set <code>proto tcp-server</code>
                        </small>
                    </div>

                    <ul class="nav nav-tabs mb-3" id="ovpn-script-tabs">
                        <li class="nav-item">
                            <a class="nav-link active" href="#" data-tab="multi">
                                <i class="fas fa-list-ul mr-1"></i>Per Baris
                                <span class="badge badge-success ml-1" style="font-size:10px;">Terminal WinBox/SSH</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#" data-tab="single">
                                <i class="fas fa-terminal mr-1"></i>Satu Baris
                                <span class="badge badge-secondary ml-1" style="font-size:10px;">Scheduler/Script</span>
                            </a>
                        </li>
                    </ul>

                    <div id="tab-multi">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <small class="text-muted">Tempel di <strong>Terminal &gt; New Terminal</strong> pada Mikrotik (WinBox/SSH). Setiap baris dijalankan terpisah.</small>
                            <button type="button" class="btn btn-sm btn-success" id="copy-ovpn-multi">
                                <i class="fas fa-copy mr-1"></i>Copy Script
                            </button>
                        </div>
                        <textarea class="form-control font-weight-normal" rows="22" id="ovpn-script-multi" readonly
                            style="font-family: 'Courier New', monospace; font-size: 12.5px; background:#1e1e2e; color:#cdd6f4; border-radius:6px; resize:vertical;"></textarea>
                    </div>

                    <div id="tab-single" style="display:none;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <small class="text-muted">Script satu baris dipisah titik koma <code>;</code> — cocok untuk <strong>Scheduler</strong> atau <strong>Script</strong> Mikrotik.</small>
                            <button type="button" class="btn btn-sm btn-success" id="copy-ovpn-single">
                                <i class="fas fa-copy mr-1"></i>Copy Script
                            </button>
                        </div>
                        <textarea class="form-control" rows="6" id="ovpn-script-single" readonly
                            style="font-family: 'Courier New', monospace; font-size: 12px; background:#1e1e2e; color:#cdd6f4; border-radius:6px; resize:vertical;"></textarea>
                    </div>

                    <div class="alert alert-info mb-0 mt-3 py-2">
                        <i class="fas fa-info-circle mr-1"></i>
                        Jika server membutuhkan sertifikat, import cert di Mikrotik terlebih dahulu lalu tambahkan parameter
                        <code>certificate=&lt;nama-cert&gt;</code> pada perintah <code>ovpn-client add</code>.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            // ── Helpers ──────────────────────────────────────────────────────
            const csrfToken = document.querySelector('meta[name="csrf-token"]')
                ? document.querySelector('meta[name="csrf-token"]').content
                : '{{ csrf_token() }}';

            function showAlert(message, type) {
                const el = document.getElementById('ovpn-alert');
                if (!el) return;
                el.className = 'alert alert-' + type + ' mb-3';
                el.textContent = message;
                el.style.display = '';
                clearTimeout(el._timer);
                el._timer = setTimeout(function () { el.style.display = 'none'; }, 5000);
            }

            function ajaxJson(method, url, body) {
                return fetch(url, {
                    method: method,
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: body ? JSON.stringify(body) : undefined,
                }).then(function (res) {
                    return res.json().then(function (data) {
                        if (!res.ok) {
                            return Promise.reject(data);
                        }
                        return data;
                    });
                });
            }

            function ajaxForm(method, url, formData) {
                // Kirim sebagai form-encoded supaya Request::validated() bekerja
                const params = new URLSearchParams();
                formData.forEach(function (value, key) { params.append(key, value); });
                params.append('_method', method);
                return fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: params,
                }).then(function (res) {
                    return res.json().then(function (data) {
                        if (!res.ok) {
                            return Promise.reject(data);
                        }
                        return data;
                    });
                });
            }

            function setRowBusy(row, busy) {
                row.querySelectorAll('button').forEach(function (btn) {
                    btn.disabled = busy;
                });
            }

            // ── Update satu baris tabel dari payload client ───────────────────
            function updateRow(row, client) {
                row.querySelector('.ovpn-col-router').textContent = client.mikrotik_connection || '-';
                row.querySelector('.ovpn-col-name').textContent   = client.name;
                row.querySelector('.ovpn-col-cn').textContent     = client.common_name;
                row.querySelector('.ovpn-col-ip').textContent     = client.vpn_ip || '-';
                row.querySelector('.ovpn-col-user').textContent   = client.username || '-';
                row.querySelector('.ovpn-col-pass').textContent   = client.password || '-';
                row.querySelector('.ovpn-col-status').textContent = client.is_active ? 'Aktif' : 'Nonaktif';
                row.querySelector('.ovpn-col-sync').textContent   = client.last_synced_at || '-';

                // Update URL di data attributes
                row.dataset.destroyUrl = client.destroy_url;
                row.dataset.syncUrl    = client.sync_url;
                row.dataset.updateUrl  = client.update_url;

                // Update nilai input di edit form
                var editRow = document.getElementById('ovpn-edit-' + client.id);
                if (editRow) {
                    var f = editRow.querySelector('form');
                    if (f) {
                        f.querySelector('[name="name"]').value        = client.name;
                        f.querySelector('[name="common_name"]').value = client.common_name;
                        f.querySelector('[name="vpn_ip"]').value      = client.vpn_ip || '';
                        f.querySelector('[name="username"]').value    = client.username || '';
                        f.querySelector('[name="password"]').value    = client.password || '';
                        f.querySelector('[name="is_active"]').value   = client.is_active ? '1' : '0';
                    }
                }
            }

            // ── Delete ────────────────────────────────────────────────────────
            document.addEventListener('click', function (e) {
                var btn = e.target.closest('.ovpn-delete-btn');
                if (!btn) return;

                if (!confirm('Hapus client ini?')) return;

                var row = btn.closest('tr[data-client-id]');
                if (!row) return;

                setRowBusy(row, true);

                ajaxJson('DELETE', row.dataset.destroyUrl).then(function (data) {
                    var editRow = document.getElementById('ovpn-edit-' + row.dataset.clientId);
                    if (editRow) editRow.remove();
                    row.remove();
                    showAlert(data.status || 'Client dihapus.', 'success');

                    // Tampilkan baris "belum ada" jika tabel kosong
                    var tbody = document.querySelector('#ovpn-clients-tbody');
                    if (tbody && tbody.querySelectorAll('tr[data-client-id]').length === 0) {
                        tbody.innerHTML = '<tr><td colspan="9" class="text-center p-4">Belum ada client OpenVPN.</td></tr>';
                    }
                }).catch(function (err) {
                    setRowBusy(row, false);
                    showAlert((err && err.error) || 'Gagal menghapus client.', 'danger');
                });
            });

            // ── Sync CCD ──────────────────────────────────────────────────────
            document.addEventListener('click', function (e) {
                var btn = e.target.closest('.ovpn-sync-btn');
                if (!btn) return;

                var row = btn.closest('tr[data-client-id]');
                if (!row) return;

                setRowBusy(row, true);
                btn.textContent = 'Syncing…';

                ajaxJson('POST', row.dataset.syncUrl).then(function (data) {
                    row.querySelector('.ovpn-col-sync').textContent = data.last_synced_at || '-';
                    btn.textContent = 'Sync CCD';
                    setRowBusy(row, false);
                    showAlert(data.status || 'Sinkronisasi berhasil.', 'success');
                }).catch(function (err) {
                    btn.textContent = 'Sync CCD';
                    setRowBusy(row, false);
                    showAlert((err && err.error) || 'Sinkronisasi gagal.', 'danger');
                });
            });

            // ── Update (edit form) ────────────────────────────────────────────
            document.addEventListener('submit', function (e) {
                var form = e.target.closest('.ovpn-update-form');
                if (!form) return;
                e.preventDefault();

                var row = form.closest('tr').previousElementSibling;
                // Cari baris data (bukan baris edit)
                while (row && !row.dataset.clientId) {
                    row = row.previousElementSibling;
                }
                if (!row) return;

                var submitBtn = form.querySelector('[type="submit"]');
                if (submitBtn) submitBtn.disabled = true;

                ajaxForm('PATCH', row.dataset.updateUrl, new FormData(form)).then(function (data) {
                    if (submitBtn) submitBtn.disabled = false;
                    updateRow(row, data.client);
                    // Tutup collapse edit row
                    var editRow = form.closest('tr');
                    if (editRow && $(editRow).hasClass('show')) {
                        $(editRow).collapse('hide');
                    }
                    showAlert(data.status || data.warning || 'Client diperbarui.', data.warning ? 'warning' : 'success');
                }).catch(function (err) {
                    if (submitBtn) submitBtn.disabled = false;
                    var msg = (err && (err.error || err.message)) || 'Gagal menyimpan perubahan.';
                    if (err && err.errors) {
                        msg = Object.values(err.errors).flat().join(' ');
                    }
                    showAlert(msg, 'danger');
                });
            });

            // ── Store (tambah form) ───────────────────────────────────────────
            var storeForm = document.getElementById('ovpn-store-form');
            if (storeForm) {
                storeForm.addEventListener('submit', function (e) {
                    e.preventDefault();

                    var submitBtn = document.getElementById('ovpn-store-btn');
                    if (submitBtn) submitBtn.disabled = true;

                    ajaxForm('POST', storeForm.action, new FormData(storeForm)).then(function (data) {
                        if (submitBtn) submitBtn.disabled = false;
                        storeForm.reset();
                        showAlert(data.status || data.warning || 'Client berhasil dibuat.', data.warning ? 'warning' : 'success');
                        addClientRow(data.client);
                    }).catch(function (err) {
                        if (submitBtn) submitBtn.disabled = false;
                        var msg = (err && (err.error || err.message)) || 'Gagal menyimpan client.';
                        if (err && err.errors) {
                            msg = Object.values(err.errors).flat().join(' ');
                        }
                        showAlert(msg, 'danger');
                    });
                });
            }

            function addClientRow(client) {
                var tbody = document.getElementById('ovpn-clients-tbody');
                if (!tbody) return;

                // Hapus baris "belum ada" jika ada
                var emptyRow = tbody.querySelector('td[colspan]');
                if (emptyRow) emptyRow.closest('tr').remove();

                var html = '<tr id="ovpn-row-' + client.id + '"'
                    + ' data-client-id="' + client.id + '"'
                    + ' data-destroy-url="' + escHtml(client.destroy_url) + '"'
                    + ' data-sync-url="'    + escHtml(client.sync_url)    + '"'
                    + ' data-update-url="'  + escHtml(client.update_url)  + '">'
                    + '<td class="ovpn-col-router">' + escHtml(client.mikrotik_connection || '-') + '</td>'
                    + '<td class="ovpn-col-name">'   + escHtml(client.name)                       + '</td>'
                    + '<td class="ovpn-col-cn">'     + escHtml(client.common_name)                + '</td>'
                    + '<td class="ovpn-col-ip">'     + escHtml(client.vpn_ip || '-')              + '</td>'
                    + '<td class="ovpn-col-user">'   + escHtml(client.username || '-')            + '</td>'
                    + '<td class="ovpn-col-pass">'   + escHtml(client.password || '-')            + '</td>'
                    + '<td class="ovpn-col-status">' + (client.is_active ? 'Aktif' : 'Nonaktif') + '</td>'
                    + '<td class="ovpn-col-sync">'   + escHtml(client.last_synced_at || '-')      + '</td>'
                    + '<td class="text-right">'
                    +   '<button type="button" class="btn btn-sm btn-outline-success ovpn-sync-btn">Sync CCD</button> '
                    +   '<button type="button" class="btn btn-sm btn-outline-primary" data-toggle="collapse" data-target="#ovpn-edit-' + client.id + '">Edit</button> '
                    +   '<button type="button" class="btn btn-sm btn-outline-secondary ovpn-script-btn" data-toggle="modal" data-target="#ovpn-script-modal" data-script="{}">Script Mikrotik</button> '
                    +   '<button type="button" class="btn btn-sm btn-outline-danger ovpn-delete-btn">Delete</button>'
                    + '</td>'
                    + '</tr>'
                    + '<tr class="collapse" id="ovpn-edit-' + client.id + '">'
                    + '<td colspan="9">'
                    + '<form class="ovpn-update-form">'
                    + '<input type="hidden" name="_token" value="' + escHtml(csrfToken) + '">'
                    + '<div class="form-row">'
                    + '<div class="form-group col-md-4"><label>Nama</label><input type="text" name="name" value="' + escHtml(client.name) + '" class="form-control" required></div>'
                    + '<div class="form-group col-md-4"><label>Common Name</label><input type="text" name="common_name" value="' + escHtml(client.common_name) + '" class="form-control" required></div>'
                    + '<div class="form-group col-md-4"><label>IP VPN</label><input type="text" name="vpn_ip" value="' + escHtml(client.vpn_ip || '') + '" class="form-control"></div>'
                    + '</div><div class="form-row">'
                    + '<div class="form-group col-md-4"><label>Username</label><input type="text" name="username" value="' + escHtml(client.username || '') + '" class="form-control" required></div>'
                    + '<div class="form-group col-md-4"><label>Password</label><input type="text" name="password" value="' + escHtml(client.password || '') + '" class="form-control" required></div>'
                    + '<div class="form-group col-md-4"><label>Status</label><select name="is_active" class="form-control"><option value="1"' + (client.is_active ? ' selected' : '') + '>Aktif</option><option value="0"' + (!client.is_active ? ' selected' : '') + '>Nonaktif</option></select></div>'
                    + '</div>'
                    + '<button type="submit" class="btn btn-primary btn-sm">Simpan Perubahan</button> '
                    + '<button type="button" class="btn btn-secondary btn-sm" data-toggle="collapse" data-target="#ovpn-edit-' + client.id + '">Batal</button>'
                    + '</form></td></tr>';

                tbody.insertAdjacentHTML('beforeend', html);
            }

            function escHtml(str) {
                return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
            }

            // ── Script Mikrotik modal ─────────────────────────────────────────
            let currentData = {};
            let activeTab = 'multi';
            let activeRos = 'v6';

            const rosHints = {
                v6: 'ROS v6: hanya mendukung TCP — pastikan server OpenVPN di-set <code>proto tcp-server</code>',
                v7: 'ROS v7: cipher &amp; auth dinegosiasi otomatis oleh Mikrotik — tidak perlu diset manual',
            };

            function getHost() {
                const input = document.getElementById('ovpn-host-override');
                const val = input ? input.value.trim() : '';
                return val !== '' ? val : '<IP/Host>';
            }

            function applyHostToScript(script) {
                const host = getHost();
                return script.replace(/connect-to="[^"]*"/g, 'connect-to="' + host + '"');
            }

            function rebuildScript() {
                if (!currentData.v6multi) return;

                const multiEl = document.getElementById('ovpn-script-multi');
                const singleEl = document.getElementById('ovpn-script-single');

                const multiKey  = activeRos + 'multi';
                const singleKey = activeRos + 'single';

                if (multiEl)  multiEl.value  = applyHostToScript(currentData[multiKey]  || '');
                if (singleEl) singleEl.value = applyHostToScript(currentData[singleKey] || '');

                const statusEl = document.getElementById('ovpn-host-status');
                if (statusEl) {
                    const inputVal = document.getElementById('ovpn-host-override').value.trim();
                    statusEl.textContent = inputVal !== '' ? '✓ Digunakan' : 'Belum diisi';
                    statusEl.style.color  = inputVal !== '' ? '#28a745'    : '#dc3545';
                }
            }

            function switchRos(ros) {
                activeRos = ros;
                document.querySelectorAll('#ovpn-ros-version [data-ros]').forEach(function (btn) {
                    const isActive = btn.dataset.ros === ros;
                    btn.classList.toggle('btn-primary', isActive);
                    btn.classList.toggle('active', isActive);
                    btn.classList.toggle('btn-outline-primary', !isActive);
                });
                const hintEl = document.getElementById('ovpn-ros-hint');
                if (hintEl) hintEl.innerHTML = rosHints[ros] || '';
                rebuildScript();
            }

            function switchTab(tab) {
                activeTab = tab;
                document.getElementById('tab-multi').style.display  = tab === 'multi'  ? '' : 'none';
                document.getElementById('tab-single').style.display = tab === 'single' ? '' : 'none';
                document.querySelectorAll('#ovpn-script-tabs .nav-link').forEach(function (el) {
                    el.classList.toggle('active', el.dataset.tab === tab);
                });
            }

            document.querySelectorAll('#ovpn-ros-version [data-ros]').forEach(function (btn) {
                btn.addEventListener('click', function () { switchRos(btn.dataset.ros); });
            });

            document.querySelectorAll('#ovpn-script-tabs .nav-link').forEach(function (el) {
                el.addEventListener('click', function (e) {
                    e.preventDefault();
                    switchTab(el.dataset.tab);
                });
            });

            document.addEventListener('click', function (e) {
                var btn = e.target.closest('.ovpn-script-btn');
                if (!btn) return;
                const raw = btn.dataset.script || '{}';
                currentData = JSON.parse(raw);

                const nameEl = document.getElementById('ovpn-script-client-name');
                if (nameEl) nameEl.textContent = 'Client: ' + (currentData.name || '');

                switchRos('v6');
                switchTab('multi');
                rebuildScript();
            });

            const hostInput = document.getElementById('ovpn-host-override');
            if (hostInput) hostInput.addEventListener('input', rebuildScript);

            function copyText(elementId, button) {
                const el = document.getElementById(elementId);
                if (!el) return;
                el.focus();
                el.select();
                const text = el.value;
                const originalHtml = button.innerHTML;
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(text).then(function () {
                        button.innerHTML = '<i class="fas fa-check mr-1"></i>Tersalin!';
                        button.classList.replace('btn-success', 'btn-secondary');
                        setTimeout(function () {
                            button.innerHTML = originalHtml;
                            button.classList.replace('btn-secondary', 'btn-success');
                        }, 2000);
                    });
                } else {
                    document.execCommand('copy');
                    button.innerHTML = '<i class="fas fa-check mr-1"></i>Tersalin!';
                    setTimeout(function () { button.innerHTML = originalHtml; }, 2000);
                }
            }

            const copyMultiBtn  = document.getElementById('copy-ovpn-multi');
            const copySingleBtn = document.getElementById('copy-ovpn-single');
            if (copyMultiBtn)  copyMultiBtn.addEventListener('click',  function () { copyText('ovpn-script-multi',  copyMultiBtn); });
            if (copySingleBtn) copySingleBtn.addEventListener('click', function () { copyText('ovpn-script-single', copySingleBtn); });
        })();
    </script>
@endsection
