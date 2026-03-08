@extends('layouts.admin')

@section('title', 'API Dashboard')

{{-- Override sidebar AdminLTE dengan menu MikroTik --}}
@section('sidebar')
<nav class="mt-2">
    <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">

        {{-- Header router selector --}}
        <li class="nav-item px-2 py-2">
            <select id="dashboard-connection" class="form-control form-control-sm" style="background:#3f4a59;color:#c2c7d0;border-color:#4a5568;">
                <option value="">- Pilih Router -</option>
                @foreach($connections as $connection)
                    <option value="{{ $connection->id }}" @selected($selectedConnection && $selectedConnection->id === $connection->id)>
                        {{ $connection->name }}
                    </option>
                @endforeach
            </select>
        </li>

        <li class="nav-header">MIKROTIK</li>

        <li class="nav-item">
            <a href="#" class="nav-link active mikrotik-menu-item" data-menu="resource">
                <i class="nav-icon fas fa-server"></i>
                <p>Resource</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="#" class="nav-link mikrotik-menu-item" data-menu="interface">
                <i class="nav-icon fas fa-network-wired"></i>
                <p>Interface</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="#" class="nav-link mikrotik-menu-item" data-menu="traffic">
                <i class="nav-icon fas fa-chart-area"></i>
                <p>Trafik Live</p>
            </a>
        </li>

        {{-- PPP Group --}}
        <li class="nav-item has-treeview" id="ppp-group-li">
            <a href="#" class="nav-link">
                <i class="nav-icon fas fa-plug"></i>
                <p>PPP <i class="right fas fa-angle-left"></i></p>
            </a>
            <ul class="nav nav-treeview">
                <li class="nav-item">
                    <a href="#" class="nav-link mikrotik-menu-item" data-menu="ppp_active">
                        <i class="far fa-circle nav-icon"></i><p>Active</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link mikrotik-menu-item" data-menu="pppoe_server">
                        <i class="far fa-circle nav-icon"></i><p>PPPoE Servers</p>
                    </a>
                </li>
            </ul>
        </li>

        {{-- Hotspot Group --}}
        <li class="nav-item has-treeview" id="hotspot-group-li">
            <a href="#" class="nav-link">
                <i class="nav-icon fas fa-wifi"></i>
                <p>Hotspot <i class="right fas fa-angle-left"></i></p>
            </a>
            <ul class="nav nav-treeview">
                <li class="nav-item">
                    <a href="#" class="nav-link mikrotik-menu-item" data-menu="hotspot_active">
                        <i class="far fa-circle nav-icon"></i><p>Active</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link mikrotik-menu-item" data-menu="hotspot_setting">
                        <i class="far fa-circle nav-icon"></i><p>Setting</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link mikrotik-menu-item" data-menu="hotspot_ip_binding">
                        <i class="far fa-circle nav-icon"></i><p>IP Binding</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link mikrotik-menu-item" data-menu="hotspot_server">
                        <i class="far fa-circle nav-icon"></i><p>Server</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link mikrotik-menu-item" data-menu="hotspot_profiles">
                        <i class="far fa-circle nav-icon"></i><p>Profiles</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link mikrotik-menu-item" data-menu="hotspot_cookies">
                        <i class="far fa-circle nav-icon"></i><p>Cookies</p>
                    </a>
                </li>
            </ul>
        </li>

        <li class="nav-header">NAVIGASI</li>
        <li class="nav-item">
            <a href="{{ route('mikrotik-connections.index') }}" class="nav-link">
                <i class="nav-icon fas fa-arrow-left"></i>
                <p>Kembali ke Rafen</p>
            </a>
        </li>

    </ul>
</nav>
@endsection

@section('content')
<div class="card">
    <div class="card-header">
        <h5 class="mb-0" id="panel-title">Resource</h5>
    </div>
    <div class="card-body" id="mikrotik-content">

        {{-- Resource (default) --}}
        <div id="panel-resource">
            <div class="text-center my-3">
                <h5 class="mb-0">
                    <i class="far fa-clock mr-1"></i>
                    Uptime : <span data-field="uptime">{{ $resource['uptime'] }}</span>
                </h5>
            </div>
            <div class="row">
                <div class="col-md-3 col-sm-6">
                    <div class="info-box bg-info">
                        <span class="info-box-icon"><i class="fas fa-flag"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Platform</span>
                            <span class="info-box-number" data-field="platform_vendor">{{ $resource['platform_vendor'] }}</span>
                            <span class="text-sm" data-field="platform_model">{{ $resource['platform_model'] }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="info-box bg-success">
                        <span class="info-box-icon"><i class="fas fa-globe"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">RouterOS</span>
                            <span class="info-box-number" data-field="routeros">{{ $resource['routeros'] }}</span>
                            <span class="text-sm">stable</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="info-box bg-warning">
                        <span class="info-box-icon"><i class="fas fa-microchip"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">CPU Type</span>
                            <span class="info-box-number" data-field="cpu_type">{{ $resource['cpu_type'] }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="info-box bg-danger">
                        <span class="info-box-icon"><i class="fas fa-cube"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">CPU Cores</span>
                            <span class="info-box-number" data-field="cpu_mhz">{{ $resource['cpu_mhz'] }}</span>
                            <span class="text-sm" data-field="cpu_cores">{{ $resource['cpu_cores'] }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="info-box bg-secondary">
                        <span class="info-box-icon"><i class="fas fa-tachometer-alt"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">CPU Load</span>
                            <span class="info-box-number" data-field="cpu_load">{{ $resource['cpu_load'] }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="info-box bg-warning">
                        <span class="info-box-icon"><i class="fas fa-server"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Free Memory</span>
                            <span class="info-box-number" data-field="ram_free_percent">{{ $resource['ram_free_percent'] }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="info-box bg-success">
                        <span class="info-box-icon"><i class="fas fa-hdd"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Free Disk</span>
                            <span class="info-box-number" data-field="disk_free_percent">{{ $resource['disk_free_percent'] }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="info-box bg-info">
                        <span class="info-box-icon"><i class="fas fa-calendar-alt"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Build Time</span>
                            <span class="info-box-number" data-field="build_date">{{ $resource['build_date'] }}</span>
                            <span class="text-sm" data-field="build_time">{{ $resource['build_time'] }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Trafik Live --}}
        <div id="panel-traffic" class="d-none">
            <div class="d-flex align-items-center flex-wrap mb-3" style="gap:.5rem;">
                <label class="mb-0 font-weight-bold">Interface:</label>
                <select id="traffic-interface" class="custom-select custom-select-sm" style="max-width:220px;">
                    <option value="">- Memuat interface... -</option>
                </select>
                <button id="traffic-toggle" class="btn btn-sm btn-success">
                    <i class="fas fa-play mr-1"></i> Start
                </button>
                <span class="badge badge-secondary ml-2">TX: <span id="traffic-tx-label">-</span></span>
                <span class="badge badge-info">RX: <span id="traffic-rx-label">-</span></span>
            </div>
            <div style="position:relative;height:320px;">
                <canvas id="traffic-chart"></canvas>
            </div>
            <div id="traffic-error" class="alert alert-danger mt-2 d-none"></div>
        </div>

        {{-- Menu data (tabel dinamis) --}}
        <div id="panel-menu" class="d-none">
            <div class="mb-2" id="menu-action-bar" style="display:none!important;">
                <button class="btn btn-sm btn-success" id="btn-create-record">
                    <i class="fas fa-plus mr-1"></i> Tambah
                </button>
            </div>
            <div id="menu-loading" class="text-center py-5 d-none">
                <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
                <div class="text-muted mt-2">Memuat data...</div>
            </div>
            <div id="menu-error" class="alert alert-danger d-none"></div>
            <div id="menu-table-wrap"></div>
        </div>

    </div>
</div>

<style>
#mikrotik-content table th { white-space: nowrap; font-size: .78rem; }
#mikrotik-content table td { font-size: .8rem; white-space: nowrap; }
</style>

{{-- Modal PPPoE Server --}}
<div class="modal fade" id="modal-pppoe-server" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-pppoe-server-title">Tambah PPPoE Server</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="form-pppoe-server">
                <input type="hidden" id="pppoe-server-id" value="">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Name <span class="text-danger">*</span></label>
                        <input type="text" id="pppoe-server-name" class="form-control form-control-sm" placeholder="pppoe-in1">
                    </div>
                    <div class="form-group">
                        <label>Interface <span class="text-danger">*</span></label>
                        <input type="text" id="pppoe-server-interface" class="form-control form-control-sm" placeholder="ether1">
                    </div>
                    <div class="form-group">
                        <label>Service Name</label>
                        <input type="text" id="pppoe-server-service-name" class="form-control form-control-sm" placeholder="(kosong = semua)">
                    </div>
                    <div class="form-group">
                        <label>Max Sessions</label>
                        <input type="text" id="pppoe-server-max-sessions" class="form-control form-control-sm" placeholder="0 = unlimited">
                    </div>
                    <div class="form-group">
                        <label>Keepalive Timeout</label>
                        <input type="text" id="pppoe-server-keepalive" class="form-control form-control-sm" placeholder="10s">
                    </div>
                    <div class="form-group">
                        <label>Default Profile</label>
                        <input type="text" id="pppoe-server-default-profile" class="form-control form-control-sm" placeholder="default">
                    </div>
                    <div id="pppoe-server-error" class="alert alert-danger d-none"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-pppoe-server-save">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal Hotspot IP Binding --}}
<div class="modal fade" id="modal-ip-binding" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-ip-binding-title">Tambah IP Binding</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="form-ip-binding">
                <input type="hidden" id="ip-binding-id" value="">
                <div class="modal-body">
                    <div class="form-group">
                        <label>MAC Address <span class="text-danger">*</span></label>
                        <input type="text" id="ip-binding-mac" class="form-control form-control-sm" placeholder="AA:BB:CC:DD:EE:FF">
                    </div>
                    <div class="form-group">
                        <label>IP Address</label>
                        <input type="text" id="ip-binding-address" class="form-control form-control-sm" placeholder="192.168.1.100">
                    </div>
                    <div class="form-group">
                        <label>Server</label>
                        <input type="text" id="ip-binding-server" class="form-control form-control-sm" placeholder="all">
                    </div>
                    <div class="form-group">
                        <label>Type</label>
                        <select id="ip-binding-type" class="form-control form-control-sm">
                            <option value="bypassed">bypassed</option>
                            <option value="blocked">blocked</option>
                            <option value="regular">regular</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Comment</label>
                        <input type="text" id="ip-binding-comment" class="form-control form-control-sm">
                    </div>
                    <div id="ip-binding-error" class="alert alert-danger d-none"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-ip-binding-save">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal Hotspot User --}}
<div class="modal fade" id="modal-hotspot-user" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-hotspot-user-title">Tambah Hotspot User</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="form-hotspot-user">
                <input type="hidden" id="hotspot-user-id" value="">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Name <span class="text-danger">*</span></label>
                        <input type="text" id="hotspot-user-name" class="form-control form-control-sm" placeholder="username">
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="text" id="hotspot-user-password" class="form-control form-control-sm" placeholder="password">
                    </div>
                    <div class="form-group">
                        <label>Profile</label>
                        <input type="text" id="hotspot-user-profile" class="form-control form-control-sm" placeholder="default">
                    </div>
                    <div class="form-group">
                        <label>Server</label>
                        <input type="text" id="hotspot-user-server" class="form-control form-control-sm" placeholder="all">
                    </div>
                    <div class="form-group">
                        <label>Comment</label>
                        <input type="text" id="hotspot-user-comment" class="form-control form-control-sm">
                    </div>
                    <div id="hotspot-user-error" class="alert alert-danger d-none"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-hotspot-user-save">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    const connectionSelect = document.getElementById('dashboard-connection');
    const menuEndpoint     = '{{ route("dashboard.api.menu") }}';
    const dataEndpoint     = '{{ route("dashboard.api.data") }}';
    const trafficEndpoint  = '{{ route("dashboard.api.traffic") }}';
    const panelTitle       = document.getElementById('panel-title');
    const csrfToken        = document.querySelector('meta[name="csrf-token"]').content;

    // URL templates for CRUD endpoints
    const pppActiveBaseUrl         = '{{ url("api-dashboard/ppp-active") }}';
    const hotspotUserBaseUrl       = '{{ url("api-dashboard/hotspot-user") }}';
    const hotspotActiveBaseUrl     = '{{ url("api-dashboard/hotspot-active") }}';
    const hotspotIpBindingBaseUrl  = '{{ url("api-dashboard/hotspot-ip-binding") }}';
    const pppoeServerBaseUrl       = '{{ url("api-dashboard/pppoe-server") }}';

    // Menus that support CRUD actions
    const crudMenus       = ['hotspot_setting', 'hotspot_ip_binding', 'pppoe_server'];
    const disconnectMenus = ['ppp_active', 'hotspot_active'];

    const menuLabels = {
        resource        : 'Resource',
        interface       : 'Interface',
        traffic         : 'Trafik Live',
        ppp_active      : 'PPP — Active',
        pppoe_server    : 'PPP — PPPoE Servers',
        hotspot_active  : 'Hotspot — Active',
        hotspot_setting : 'Hotspot — Setting',
        hotspot_ip_binding: 'Hotspot — IP Binding',
        hotspot_server  : 'Hotspot — Server',
        hotspot_profiles: 'Hotspot — Profiles',
        hotspot_cookies : 'Hotspot — Cookies',
    };

    function getConnectionId() { return connectionSelect ? connectionSelect.value : ''; }

    function formatBits(bps) {
        if (bps >= 1e9) return (bps / 1e9).toFixed(2) + ' Gbps';
        if (bps >= 1e6) return (bps / 1e6).toFixed(2) + ' Mbps';
        if (bps >= 1e3) return (bps / 1e3).toFixed(2) + ' Kbps';
        return bps + ' bps';
    }

    function esc(str) {
        return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    async function apiRequest(method, url, body) {
        const opts = {
            method,
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        };
        if (body) {
            opts.headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(body);
        }
        const res  = await fetch(url, opts);
        const json = await res.json();
        if (!res.ok) throw new Error(json.error || 'Permintaan gagal.');
        return json;
    }

    // ── Panels ──────────────────────────────────────────────────────────────
    const panels = {
        resource : document.getElementById('panel-resource'),
        traffic  : document.getElementById('panel-traffic'),
        menu     : document.getElementById('panel-menu'),
    };

    function showPanel(name) {
        Object.entries(panels).forEach(([k, el]) => el.classList.toggle('d-none', k !== name));
    }

    // ── Resource refresh on connection change ────────────────────────────────
    const resourcePanel = panels.resource;
    function updateResourceFields(payload) {
        Object.entries(payload || {}).forEach(([key, val]) => {
            const node = resourcePanel.querySelector('[data-field="' + key + '"]');
            if (node) node.textContent = val ?? '-';
        });
    }

    if (connectionSelect) {
        connectionSelect.addEventListener('change', async () => {
            const id = getConnectionId();
            const params = id ? '?connection_id=' + id : '';
            try {
                const res  = await fetch(dataEndpoint + params, { headers: { Accept: 'application/json' } });
                const json = await res.json();
                if (res.ok && json?.data) updateResourceFields(json.data);
            } catch (e) { /* silent */ }

            if (currentMenu && currentMenu !== 'resource' && currentMenu !== 'traffic') loadMenu(currentMenu);
            if (currentMenu === 'traffic') loadTrafficInterfaces(false);
        });
    }

    // ── Menu item click ──────────────────────────────────────────────────────
    let currentMenu = 'resource';

    document.querySelectorAll('.mikrotik-menu-item').forEach(function (item) {
        item.addEventListener('click', function (e) {
            e.preventDefault();
            document.querySelectorAll('.mikrotik-menu-item').forEach(el => el.classList.remove('active'));
            this.classList.add('active');
            const menu = this.dataset.menu;
            if (panelTitle) panelTitle.textContent = menuLabels[menu] || menu;
            switchMenu(menu);
        });
    });

    function switchMenu(menu) {
        currentMenu = menu;

        const actionBar = document.getElementById('menu-action-bar');
        if (crudMenus.includes(menu)) {
            actionBar.style.removeProperty('display');
        } else {
            actionBar.style.setProperty('display', 'none', 'important');
        }

        if (menu === 'resource') {
            showPanel('resource');
            return;
        }

        if (menu === 'traffic') {
            showPanel('traffic');
            initTrafficChart();
            loadTrafficInterfaces(true);
            return;
        }

        showPanel('menu');
        loadMenu(menu);
    }

    // ── Menu data (tabel) ────────────────────────────────────────────────────
    const menuLoading   = document.getElementById('menu-loading');
    const menuError     = document.getElementById('menu-error');
    const menuTableWrap = document.getElementById('menu-table-wrap');

    async function loadMenu(menu) {
        menuLoading.classList.remove('d-none');
        menuError.classList.add('d-none');
        menuTableWrap.innerHTML = '';

        const id = getConnectionId();
        if (!id) {
            menuLoading.classList.add('d-none');
            menuError.classList.remove('d-none');
            menuError.textContent = 'Pilih router terlebih dahulu.';
            return;
        }

        try {
            const res  = await fetch(menuEndpoint + '?menu=' + menu + '&connection_id=' + id, { headers: { Accept: 'application/json' } });
            const json = await res.json();
            menuLoading.classList.add('d-none');
            if (!res.ok || json.error) {
                menuError.classList.remove('d-none');
                menuError.textContent = json.error || 'Gagal memuat data.';
                return;
            }
            renderTable(json.data || [], menu);
        } catch (e) {
            menuLoading.classList.add('d-none');
            menuError.classList.remove('d-none');
            menuError.textContent = 'Gagal terhubung ke server.';
        }
    }

    function renderTable(rows, menu) {
        const hasCrud       = crudMenus.includes(menu);
        const hasDisconnect = disconnectMenus.includes(menu);
        const hasActions    = hasCrud || hasDisconnect;

        if (!rows.length) {
            menuTableWrap.innerHTML = '<p class="text-muted">Tidak ada data.</p>';
            return;
        }

        // Kolom tetap untuk ip-binding agar mac-address selalu tampil di depan
        let keys;
        if (menu === 'hotspot_ip_binding') {
            const priorityCols = ['mac-address', 'address', 'server', 'type', 'comment'];
            const allKeys = Array.from(new Set([
                ...priorityCols,
                ...Object.keys(rows.reduce((acc, r) => Object.assign(acc, r), {}))
            ])).filter(k => k !== '.id');
            keys = allKeys;
        } else {
            // Exclude internal .id from visible columns
            const allKeys = Object.keys(rows[0]);
            keys = allKeys.filter(k => k !== '.id');
        }

        let html = '<div class="table-responsive"><table class="table table-sm table-hover table-bordered mb-0"><thead class="thead-light"><tr>';
        keys.forEach(k => { html += '<th>' + esc(k) + '</th>'; });
        if (hasActions) html += '<th style="width:1%">Aksi</th>';
        html += '</tr></thead><tbody>';

        rows.forEach(function (row) {
            const rowId = esc(row['.id'] || '');
            html += '<tr>';
            keys.forEach(k => { html += '<td>' + esc(row[k] ?? '') + '</td>'; });
            if (hasActions) {
                html += '<td class="text-nowrap">';
                if (hasCrud) {
                    const rowJson = esc(JSON.stringify(row));
                    html += '<button class="btn btn-xs btn-warning mr-1 btn-edit-row" data-row="' + rowJson + '" title="Edit"><i class="fas fa-pencil-alt"></i></button>';
                    html += '<button class="btn btn-xs btn-danger btn-delete-row" data-id="' + rowId + '" title="Hapus"><i class="fas fa-trash"></i></button>';
                }
                if (hasDisconnect) {
                    html += '<button class="btn btn-xs btn-secondary btn-disconnect-row" data-id="' + rowId + '" title="Disconnect"><i class="fas fa-plug"></i> Disconnect</button>';
                }
                html += '</td>';
            }
            html += '</tr>';
        });
        html += '</tbody></table></div>';
        menuTableWrap.innerHTML = html;
    }

    // ── Tombol Tambah (Create) ───────────────────────────────────────────────
    document.getElementById('btn-create-record').addEventListener('click', function () {
        if (currentMenu === 'hotspot_setting')    openHotspotUserModal(null);
        if (currentMenu === 'hotspot_ip_binding') openIpBindingModal(null);
        if (currentMenu === 'pppoe_server')       openPppoeServerModal(null);
    });

    // ── Delegated: Edit / Delete / Disconnect ────────────────────────────────
    menuTableWrap.addEventListener('click', function (e) {
        const editBtn       = e.target.closest('.btn-edit-row');
        const deleteBtn     = e.target.closest('.btn-delete-row');
        const disconnectBtn = e.target.closest('.btn-disconnect-row');

        if (editBtn) {
            const row = JSON.parse(editBtn.dataset.row);
            if (currentMenu === 'hotspot_setting')    openHotspotUserModal(row);
            if (currentMenu === 'hotspot_ip_binding') openIpBindingModal(row);
            if (currentMenu === 'pppoe_server')       openPppoeServerModal(row);
        }

        if (deleteBtn) {
            if (!confirm('Hapus data ini?')) return;
            const id  = deleteBtn.dataset.id;
            const cid = getConnectionId();
            let url;
            if (currentMenu === 'hotspot_setting')    url = hotspotUserBaseUrl + '/' + id + '?connection_id=' + cid;
            if (currentMenu === 'hotspot_ip_binding') url = hotspotIpBindingBaseUrl + '/' + id + '?connection_id=' + cid;
            if (currentMenu === 'pppoe_server')       url = pppoeServerBaseUrl + '/' + id + '?connection_id=' + cid;
            if (!url) return;
            deleteBtn.disabled = true;
            apiRequest('DELETE', url).then(function (json) {
                AppAjax.showToast(json.message || 'Berhasil dihapus.', 'success');
                loadMenu(currentMenu);
            }).catch(function (err) {
                deleteBtn.disabled = false;
                AppAjax.showToast(err.message || 'Gagal menghapus.', 'danger');
            });
        }

        if (disconnectBtn) {
            if (!confirm('Disconnect session ini?')) return;
            const id  = disconnectBtn.dataset.id;
            const cid = getConnectionId();
            let url;
            if (currentMenu === 'ppp_active')     url = pppActiveBaseUrl + '/' + id + '/disconnect?connection_id=' + cid;
            if (currentMenu === 'hotspot_active') url = hotspotActiveBaseUrl + '/' + id + '/disconnect?connection_id=' + cid;
            if (!url) return;
            disconnectBtn.disabled = true;
            apiRequest('POST', url).then(function (json) {
                AppAjax.showToast(json.message || 'Berhasil disconnect.', 'success');
                loadMenu(currentMenu);
            }).catch(function (err) {
                disconnectBtn.disabled = false;
                AppAjax.showToast(err.message || 'Gagal disconnect.', 'danger');
            });
        }
    });

    // ── Hotspot User Modal ───────────────────────────────────────────────────
    const $hotspotModal = $('#modal-hotspot-user');

    function openHotspotUserModal(row) {
        const isEdit = row !== null;
        document.getElementById('modal-hotspot-user-title').textContent = isEdit ? 'Edit Hotspot User' : 'Tambah Hotspot User';
        document.getElementById('hotspot-user-id').value       = isEdit ? (row['.id'] || '') : '';
        document.getElementById('hotspot-user-name').value     = isEdit ? (row['name'] || '') : '';
        document.getElementById('hotspot-user-password').value = '';
        document.getElementById('hotspot-user-profile').value  = isEdit ? (row['profile'] || '') : '';
        document.getElementById('hotspot-user-server').value   = isEdit ? (row['server'] || '') : '';
        document.getElementById('hotspot-user-comment').value  = isEdit ? (row['comment'] || '') : '';
        document.getElementById('hotspot-user-name').readOnly  = isEdit;
        document.getElementById('hotspot-user-error').classList.add('d-none');
        $hotspotModal.modal('show');
    }

    document.getElementById('form-hotspot-user').addEventListener('submit', async function (e) {
        e.preventDefault();
        const id       = document.getElementById('hotspot-user-id').value;
        const isEdit   = id !== '';
        const cid      = getConnectionId();
        const errEl    = document.getElementById('hotspot-user-error');
        const saveBtn  = document.getElementById('btn-hotspot-user-save');

        const body = {
            connection_id : cid,
            name          : document.getElementById('hotspot-user-name').value,
            password      : document.getElementById('hotspot-user-password').value,
            profile       : document.getElementById('hotspot-user-profile').value,
            server        : document.getElementById('hotspot-user-server').value,
            comment       : document.getElementById('hotspot-user-comment').value,
        };

        errEl.classList.add('d-none');
        saveBtn.disabled = true;

        try {
            const url = isEdit ? hotspotUserBaseUrl + '/' + id : hotspotUserBaseUrl;
            const json = await apiRequest(isEdit ? 'PUT' : 'POST', url, body);
            AppAjax.showToast(json.message || 'Berhasil.', 'success');
            $hotspotModal.modal('hide');
            loadMenu(currentMenu);
        } catch (err) {
            errEl.textContent = err.message || 'Gagal menyimpan.';
            errEl.classList.remove('d-none');
        } finally {
            saveBtn.disabled = false;
        }
    });

    // ── Hotspot IP Binding Modal ─────────────────────────────────────────────
    const $ipBindingModal = $('#modal-ip-binding');

    function openIpBindingModal(row) {
        const isEdit = row !== null;
        document.getElementById('modal-ip-binding-title').textContent = isEdit ? 'Edit IP Binding' : 'Tambah IP Binding';
        document.getElementById('ip-binding-id').value          = isEdit ? (row['.id'] || '') : '';
        document.getElementById('ip-binding-mac').value         = isEdit ? (row['mac-address'] || '') : '';
        document.getElementById('ip-binding-address').value     = isEdit ? (row['address'] || '') : '';
        document.getElementById('ip-binding-server').value      = isEdit ? (row['server'] || '') : '';
        document.getElementById('ip-binding-type').value        = isEdit ? (row['type'] || 'bypassed') : 'bypassed';
        document.getElementById('ip-binding-comment').value     = isEdit ? (row['comment'] || '') : '';
        document.getElementById('ip-binding-mac').readOnly      = isEdit;
        document.getElementById('ip-binding-error').classList.add('d-none');
        $ipBindingModal.modal('show');
    }

    document.getElementById('form-ip-binding').addEventListener('submit', async function (e) {
        e.preventDefault();
        const id      = document.getElementById('ip-binding-id').value;
        const isEdit  = id !== '';
        const cid     = getConnectionId();
        const errEl   = document.getElementById('ip-binding-error');
        const saveBtn = document.getElementById('btn-ip-binding-save');

        const body = {
            connection_id  : cid,
            'mac-address'  : document.getElementById('ip-binding-mac').value,
            address        : document.getElementById('ip-binding-address').value,
            server         : document.getElementById('ip-binding-server').value,
            type           : document.getElementById('ip-binding-type').value,
            comment        : document.getElementById('ip-binding-comment').value,
        };

        errEl.classList.add('d-none');
        saveBtn.disabled = true;

        try {
            const url  = isEdit ? hotspotIpBindingBaseUrl + '/' + id : hotspotIpBindingBaseUrl;
            const json = await apiRequest(isEdit ? 'PUT' : 'POST', url, body);
            AppAjax.showToast(json.message || 'Berhasil.', 'success');
            $ipBindingModal.modal('hide');
            loadMenu(currentMenu);
        } catch (err) {
            errEl.textContent = err.message || 'Gagal menyimpan.';
            errEl.classList.remove('d-none');
        } finally {
            saveBtn.disabled = false;
        }
    });

    // ── PPPoE Server Modal ───────────────────────────────────────────────────
    const $pppoeServerModal = $('#modal-pppoe-server');

    function openPppoeServerModal(row) {
        const isEdit = row !== null;
        document.getElementById('modal-pppoe-server-title').textContent = isEdit ? 'Edit PPPoE Server' : 'Tambah PPPoE Server';
        document.getElementById('pppoe-server-id').value               = isEdit ? (row['.id'] || '') : '';
        document.getElementById('pppoe-server-name').value             = isEdit ? (row['name'] || '') : '';
        document.getElementById('pppoe-server-interface').value        = isEdit ? (row['interface'] || '') : '';
        document.getElementById('pppoe-server-service-name').value     = isEdit ? (row['service-name'] || '') : '';
        document.getElementById('pppoe-server-max-sessions').value     = isEdit ? (row['max-sessions'] || '') : '';
        document.getElementById('pppoe-server-keepalive').value        = isEdit ? (row['keepalive-timeout'] || '') : '';
        document.getElementById('pppoe-server-default-profile').value  = isEdit ? (row['default-profile'] || '') : '';
        document.getElementById('pppoe-server-name').readOnly          = isEdit;
        document.getElementById('pppoe-server-error').classList.add('d-none');
        $pppoeServerModal.modal('show');
    }

    document.getElementById('form-pppoe-server').addEventListener('submit', async function (e) {
        e.preventDefault();
        const id      = document.getElementById('pppoe-server-id').value;
        const isEdit  = id !== '';
        const cid     = getConnectionId();
        const errEl   = document.getElementById('pppoe-server-error');
        const saveBtn = document.getElementById('btn-pppoe-server-save');

        const body = {
            connection_id       : cid,
            name                : document.getElementById('pppoe-server-name').value,
            interface           : document.getElementById('pppoe-server-interface').value,
            'service-name'      : document.getElementById('pppoe-server-service-name').value,
            'max-sessions'      : document.getElementById('pppoe-server-max-sessions').value,
            'keepalive-timeout' : document.getElementById('pppoe-server-keepalive').value,
            'default-profile'   : document.getElementById('pppoe-server-default-profile').value,
        };

        errEl.classList.add('d-none');
        saveBtn.disabled = true;

        try {
            const url  = isEdit ? pppoeServerBaseUrl + '/' + id : pppoeServerBaseUrl;
            const json = await apiRequest(isEdit ? 'PUT' : 'POST', url, body);
            AppAjax.showToast(json.message || 'Berhasil.', 'success');
            $pppoeServerModal.modal('hide');
            loadMenu(currentMenu);
        } catch (err) {
            errEl.textContent = err.message || 'Gagal menyimpan.';
            errEl.classList.remove('d-none');
        } finally {
            saveBtn.disabled = false;
        }
    });

    // ── Trafik Live ──────────────────────────────────────────────────────────
    const trafficInterfaceSelect = document.getElementById('traffic-interface');
    const trafficToggleBtn       = document.getElementById('traffic-toggle');
    const trafficTxLabel         = document.getElementById('traffic-tx-label');
    const trafficRxLabel         = document.getElementById('traffic-rx-label');
    const trafficError           = document.getElementById('traffic-error');
    let trafficChart   = null;
    let trafficTimer   = null;
    let trafficRunning = false;

    function initTrafficChart() {
        if (trafficChart) return;
        const ctx = document.getElementById('traffic-chart').getContext('2d');
        trafficChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: Array(60).fill(''),
                datasets: [
                    { label: 'TX (Upload)',   data: Array(60).fill(null), borderColor: 'rgba(255,140,0,.9)',  backgroundColor: 'rgba(255,140,0,.1)',  borderWidth: 2, pointRadius: 0, tension: .3, fill: true },
                    { label: 'RX (Download)', data: Array(60).fill(null), borderColor: 'rgba(23,162,184,.9)', backgroundColor: 'rgba(23,162,184,.1)', borderWidth: 2, pointRadius: 0, tension: .3, fill: true },
                ],
            },
            options: {
                animation: false,
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, ticks: { callback: v => formatBits(v) } },
                    x: { display: false },
                },
                plugins: {
                    tooltip: { callbacks: { label: ctx => ctx.dataset.label + ': ' + formatBits(ctx.raw || 0) } },
                },
            },
        });
    }

    function pushTrafficData(tx, rx) {
        if (!trafficChart) return;
        trafficChart.data.labels.push(new Date().toLocaleTimeString());
        trafficChart.data.labels.shift();
        trafficChart.data.datasets[0].data.push(tx); trafficChart.data.datasets[0].data.shift();
        trafficChart.data.datasets[1].data.push(rx); trafficChart.data.datasets[1].data.shift();
        trafficChart.update('none');
    }

    async function fetchTraffic() {
        const id    = getConnectionId();
        const iface = trafficInterfaceSelect ? trafficInterfaceSelect.value : '';
        if (!id || !iface) return;

        try {
            const res  = await fetch(trafficEndpoint + '?connection_id=' + id + '&interface=' + encodeURIComponent(iface), { headers: { Accept: 'application/json' } });
            const json = await res.json();
            if (!res.ok || json.error) {
                trafficError.classList.remove('d-none');
                trafficError.textContent = json.error || 'Gagal memuat trafik.';
                stopTraffic();
                return;
            }
            trafficError.classList.add('d-none');
            trafficTxLabel.textContent = formatBits(json.tx || 0);
            trafficRxLabel.textContent = formatBits(json.rx || 0);
            pushTrafficData(json.tx || 0, json.rx || 0);
        } catch (e) {
            trafficError.classList.remove('d-none');
            trafficError.textContent = 'Gagal terhubung ke server.';
            stopTraffic();
        }
    }

    function startTraffic() {
        trafficRunning = true;
        trafficToggleBtn.innerHTML = '<i class="fas fa-stop mr-1"></i> Stop';
        trafficToggleBtn.classList.replace('btn-success', 'btn-danger');
        fetchTraffic();
        trafficTimer = setInterval(fetchTraffic, 2000);
    }

    function stopTraffic() {
        trafficRunning = false;
        clearInterval(trafficTimer);
        trafficTimer = null;
        trafficToggleBtn.innerHTML = '<i class="fas fa-play mr-1"></i> Start';
        trafficToggleBtn.classList.replace('btn-danger', 'btn-success');
    }

    trafficToggleBtn.addEventListener('click', function () {
        if (trafficRunning) { stopTraffic(); return; }
        const id = getConnectionId();
        if (!id)                           { alert('Pilih router terlebih dahulu.'); return; }
        if (!trafficInterfaceSelect.value) { alert('Pilih interface terlebih dahulu.'); return; }
        startTraffic();
    });

    trafficInterfaceSelect.addEventListener('change', function () {
        if (trafficRunning) { stopTraffic(); startTraffic(); }
    });

    async function loadTrafficInterfaces(autoStart) {
        if (trafficRunning) stopTraffic();
        const id = getConnectionId();
        trafficInterfaceSelect.innerHTML = '<option value="">- Memuat... -</option>';
        if (!id) { trafficInterfaceSelect.innerHTML = '<option value="">- Pilih router dulu -</option>'; return; }

        try {
            const res  = await fetch(menuEndpoint + '?menu=interface&connection_id=' + id, { headers: { Accept: 'application/json' } });
            const json = await res.json();
            if (!res.ok || json.error || !json.data) {
                trafficInterfaceSelect.innerHTML = '<option value="">- Gagal memuat interface -</option>';
                return;
            }
            trafficInterfaceSelect.innerHTML = '<option value="">- Pilih Interface -</option>';
            let firstRunning = null;
            json.data.forEach(function (iface) {
                const name    = iface.name || '';
                const running = iface.running === 'true';
                const type    = iface.type || '';
                if (type === 'loopback') return;
                const opt = document.createElement('option');
                opt.value       = name;
                opt.textContent = name + (running ? ' ✓' : '');
                if (running && !firstRunning) firstRunning = opt;
                trafficInterfaceSelect.appendChild(opt);
            });
            if (firstRunning) firstRunning.selected = true;
            if (autoStart && trafficInterfaceSelect.value) startTraffic();
        } catch (e) {
            trafficInterfaceSelect.innerHTML = '<option value="">- Gagal memuat interface -</option>';
        }
    }

    // stop polling when leaving traffic
    document.querySelectorAll('.mikrotik-menu-item').forEach(function (item) {
        item.addEventListener('click', function () {
            if (this.dataset.menu !== 'traffic' && trafficRunning) stopTraffic();
        });
    });
})();
</script>
@endpush
