@extends('layouts.admin')

@section('title', 'Session User')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title mb-0">Session Hotspot Aktif</h3>
        </div>

        <div class="card-body">
            {{-- Stats --}}
            <div class="row text-center mb-3">
                <div class="col-md-4 col-sm-6 mb-2">
                    <div class="p-3 border rounded h-100 d-flex align-items-center">
                        <div class="mr-3 text-success"><i class="fas fa-broadcast-tower fa-2x"></i></div>
                        <div class="text-left">
                            <div class="small text-uppercase text-muted">Total Hotspot Online</div>
                            <div class="h4 mb-0 font-weight-bold">{{ $total }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-sm-6 mb-2">
                    <div class="p-3 border rounded h-100 d-flex align-items-center">
                        <div class="mr-3 text-info"><i class="fas fa-server fa-2x"></i></div>
                        <div class="text-left">
                            <div class="small text-uppercase text-muted">Jumlah Router</div>
                            <div class="h4 mb-0 font-weight-bold">{{ $routers->count() }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-sm-6 mb-2">
                    <div class="p-3 border rounded h-100 d-flex align-items-center">
                        <div class="mr-3 text-warning"><i class="fas fa-clock fa-2x"></i></div>
                        <div class="text-left">
                            <div class="small text-uppercase text-muted">Halaman ini</div>
                            <div class="h4 mb-0 font-weight-bold">{{ $sessions->total() }}</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Filter --}}
            <form method="GET" action="{{ route('sessions.hotspot') }}" class="mb-3">
                <div class="row">
                    <div class="col-md-4 col-sm-6">
                        <select name="router_id" class="form-control form-control-sm" onchange="this.form.submit()">
                            <option value="">-- Semua Router --</option>
                            @foreach ($routers as $router)
                                <option value="{{ $router->id }}" {{ request('router_id') == $router->id ? 'selected' : '' }}>
                                    {{ $router->name }} ({{ $router->host }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                <div id="session-table-wrapper">
                    <table class="table table-hover table-sm align-middle mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Username</th>
                                <th>IP Address</th>
                                <th>MAC Address</th>
                                <th>Uptime</th>
                                <th>Upload</th>
                                <th>Download</th>
                                <th>Server</th>
                                <th>Router</th>
                                <th>Diperbarui</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($sessions as $session)
                                <tr>
                                    <td>
                                        <strong>{{ $session->username }}</strong>
                                    </td>
                                    <td>
                                        <code>{{ $session->ipv4_address ?? '-' }}</code>
                                    </td>
                                    <td>
                                        <code class="text-muted">{{ $session->caller_id ?? '-' }}</code>
                                    </td>
                                    <td>
                                        <span class="badge badge-success">{{ $session->uptime ?? '-' }}</span>
                                    </td>
                                    @php
                                        $bytesIn  = $session->bytes_in  ? number_format($session->bytes_in / 1073741824, 2).' GB'  : '-';
                                        $bytesOut = $session->bytes_out ? number_format($session->bytes_out / 1073741824, 2).' GB' : '-';
                                    @endphp
                                    <td><small>{{ $bytesIn }}</small></td>
                                    <td><small>{{ $bytesOut }}</small></td>
                                    <td>{{ $session->server_name ?? '-' }}</td>
                                    <td>
                                        <span class="badge badge-info">{{ $session->mikrotikConnection?->name ?? '-' }}</span>
                                    </td>
                                    <td>
                                        <small class="text-muted">{{ $session->updated_at?->diffForHumans() }}</small>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center p-4 text-muted">
                                        <i class="fas fa-info-circle"></i>
                                        Belum ada sesi Hotspot aktif.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>

                    @if ($sessions->hasPages())
                        <div class="d-flex justify-content-end mt-3">
                            {{ $sessions->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script>
    (function () {
        var autoRefreshTimer = null;

        function ajaxRefresh() {
            var wrapper = document.getElementById('session-table-wrapper');
            if (!wrapper) return;
            fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.text(); })
                .then(function (html) {
                    var doc = new DOMParser().parseFromString(html, 'text/html');
                    var fresh = doc.getElementById('session-table-wrapper');
                    if (fresh) wrapper.innerHTML = fresh.innerHTML;
                })
                .catch(function () {});
        }

        function init() {
            if (!document.getElementById('session-table-wrapper')) return;
            if (autoRefreshTimer) { clearInterval(autoRefreshTimer); }
            autoRefreshTimer = setInterval(ajaxRefresh, 60000);
        }

        document.addEventListener('turbo:before-cache', function () {
            if (autoRefreshTimer) { clearInterval(autoRefreshTimer); autoRefreshTimer = null; }
        });

        document.addEventListener('turbo:load', init);
        if (document.readyState !== 'loading') init();
    })();
    </script>
@endsection
