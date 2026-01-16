@extends('layouts.admin')

@section('title', 'Pengaturan OpenVPN')

@section('content')
    <div class="card mb-4">
        <div class="card-header">
            <h4 class="mb-0">Informasi Koneksi OpenVPN</h4>
        </div>
        <div class="card-body">
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
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-2"><strong>IP/Host:</strong> {{ $ovpn['host'] !== '' ? $ovpn['host'] : '-' }}</div>
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
        <form action="{{ route('settings.ovpn.clients.store') }}" method="POST">
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
            <div class="card-footer d-flex justify-content-between">
                <span class="text-muted">IP VPN akan di-assign otomatis jika kosong.</span>
                <button type="submit" class="btn btn-primary">Simpan</button>
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
                    <tbody>
                    @forelse($clients as $client)
                        <tr>
                            <td>{{ $client->mikrotikConnection?->name ?? '-' }}</td>
                            <td>{{ $client->name }}</td>
                            <td>{{ $client->common_name }}</td>
                            <td>{{ $client->vpn_ip ?? '-' }}</td>
                            <td>{{ $client->username ?? '-' }}</td>
                            <td>{{ $client->password ?? '-' }}</td>
                            <td>{{ $client->is_active ? 'Aktif' : 'Nonaktif' }}</td>
                            <td>{{ $client->last_synced_at?->format('Y-m-d H:i:s') ?? '-' }}</td>
                            <td class="text-right">
                                <form action="{{ route('settings.ovpn.clients.sync', $client) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-success">Sync CCD</button>
                                </form>
                                <button type="button" class="btn btn-sm btn-outline-primary" data-toggle="collapse" data-target="#ovpn-edit-{{ $client->id }}">
                                    Edit
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="collapse" data-target="#ovpn-script-{{ $client->id }}">
                                    Script Mikrotik
                                </button>
                                <form action="{{ route('settings.ovpn.clients.destroy', $client) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus client ini?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <tr class="collapse" id="ovpn-edit-{{ $client->id }}">
                            <td colspan="9">
                                <form action="{{ route('settings.ovpn.clients.update', $client) }}" method="POST">
                                    @csrf
                                    @method('PATCH')
                                    <div class="form-row">
                                        <div class="form-group col-md-4">
                                            <label>Nama</label>
                                            <input type="text" name="name" value="{{ old('name', $client->name) }}" class="form-control" required>
                                        </div>
                                        <div class="form-group col-md-4">
                                            <label>Common Name</label>
                                            <input type="text" name="common_name" value="{{ old('common_name', $client->common_name) }}" class="form-control" required>
                                        </div>
                                        <div class="form-group col-md-4">
                                            <label>IP VPN</label>
                                            <input type="text" name="vpn_ip" value="{{ old('vpn_ip', $client->vpn_ip) }}" class="form-control">
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group col-md-4">
                                            <label>Username</label>
                                            <input type="text" name="username" value="{{ old('username', $client->username) }}" class="form-control" required>
                                        </div>
                                        <div class="form-group col-md-4">
                                            <label>Password</label>
                                            <input type="text" name="password" value="{{ old('password', $client->password) }}" class="form-control" required>
                                        </div>
                                        <div class="form-group col-md-4">
                                            <label>Status</label>
                                            <select name="is_active" class="form-control">
                                                <option value="1" @selected(old('is_active', $client->is_active ? '1' : '0') == '1')>Aktif</option>
                                                <option value="0" @selected(old('is_active', $client->is_active ? '1' : '0') == '0')>Nonaktif</option>
                                            </select>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-sm">Simpan Perubahan</button>
                                </form>
                            </td>
                        </tr>
                        <tr class="collapse" id="ovpn-script-{{ $client->id }}">
                            <td colspan="9">
                                @php
                                    $ovpnHost = $ovpn['host'] !== '' ? $ovpn['host'] : '<IP/Host>';
                                    $ovpnPort = $ovpn['port'] !== '' ? $ovpn['port'] : '1194';
                                    $ovpnProto = $ovpn['proto'] !== '' ? $ovpn['proto'] : 'udp';
                                    $ovpnUser = $client->username ?: '<username>';
                                    $ovpnPass = $client->password ?: '<password>';
                                    $ovpnName = 'ovpn-'.$client->common_name;
                                    $scriptId = 'ovpn-script-'.$client->id;
                                    $routeDst = $ovpn['route_dst'];
                                    $routeComment = 'static route '.$ovpnName;
                                    $scriptLines = [
                                        '/interface ovpn-client remove [find name="'.$ovpnName.'"]',
                                        '/interface sstp-client remove [find name="'.$ovpnName.'"]',
                                        '/interface l2tp-client remove [find name="'.$ovpnName.'"]',
                                        '/interface pptp-client remove [find name="'.$ovpnName.'"]',
                                        '/routing table remove [find name="'.$ovpnName.'"]',
                                        '/routing rule remove [find comment="'.$routeComment.'"]',
                                        '/ip route remove [find comment="'.$routeComment.'"]',
                                        '/interface ovpn-client add disabled=no connect-to="'.$ovpnHost.'" name="'.$ovpnName.'" user="'.$ovpnUser.'" password="'.$ovpnPass.'" protocol='.$ovpnProto.' port='.$ovpnPort.' comment="IPADDR : '.($client->vpn_ip ?? '-').'"',
                                    ];
                                    if ($routeDst !== '') {
                                        $scriptLines[] = '/routing table add name="'.$ovpnName.'" fib';
                                        $scriptLines[] = '/routing rule add dst-address="'.$routeDst.'" action=lookup-only-in-table table="'.$ovpnName.'" comment="'.$routeComment.'"';
                                        $scriptLines[] = '/ip route add disabled=no gateway="'.$ovpnName.'" dst-address="'.$routeDst.'" routing-table="'.$ovpnName.'" comment="'.$routeComment.'"';
                                    }
                                    $scriptPayload = implode(';', $scriptLines).';';
                                @endphp
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div class="text-muted">Salin perintah berikut ke Mikrotik.</div>
                                    <button type="button" class="btn btn-sm btn-outline-secondary copy-ovpn-script" data-target="{{ $scriptId }}">Copy Script</button>
                                </div>
                                <pre class="mb-0" id="{{ $scriptId }}">{{ $scriptPayload }}</pre>
                                <div class="text-muted mt-2">Jika server membutuhkan sertifikat, import cert di Mikrotik lalu tambahkan parameter <code>certificate=</code>.</div>
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

    <script>
        document.querySelectorAll('.copy-ovpn-script').forEach(button => {
            button.addEventListener('click', () => {
                const targetId = button.dataset.target;
                const node = targetId ? document.getElementById(targetId) : null;
                if (! node) {
                    return;
                }

                const text = node.textContent || '';
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(text);
                } else {
                    const textarea = document.createElement('textarea');
                    textarea.value = text;
                    document.body.appendChild(textarea);
                    textarea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textarea);
                }
            });
        });
    </script>
@endsection
