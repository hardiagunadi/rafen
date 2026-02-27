@extends('layouts.admin')

@section('title', 'Session Hotspot Aktif')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0">Session Hotspot Aktif</h3>
            <div>
                @foreach ($routers as $router)
                    <button type="button"
                        class="btn btn-sm btn-info text-white ml-1 btn-refresh-router"
                        data-router-id="{{ $router->id }}"
                        data-router-name="{{ $router->name }}"
                        data-refresh-url="{{ route('sessions.refresh-router', $router) }}"
                        data-service="hotspot"
                        title="Refresh sesi dari {{ $router->name }}">
                        <i class="fas fa-sync-alt"></i> {{ $router->name }}
                    </button>
                @endforeach
            </div>
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
                                    <td colspan="7" class="text-center p-4 text-muted">
                                        <i class="fas fa-info-circle"></i>
                                        Belum ada sesi Hotspot aktif.
                                        Klik tombol <strong>Refresh</strong> di atas untuk mengambil data dari router.
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
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

        function refreshTable() {
            fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newWrapper = doc.getElementById('session-table-wrapper');
                    const currentWrapper = document.getElementById('session-table-wrapper');
                    if (newWrapper && currentWrapper) {
                        currentWrapper.innerHTML = newWrapper.innerHTML;
                    }
                })
                .catch(() => {});
        }

        document.querySelectorAll('.btn-refresh-router').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const routerName = this.dataset.routerName;
                const refreshUrl = this.dataset.refreshUrl;
                const service = this.dataset.service;
                const icon = this.querySelector('i');

                icon.classList.add('fa-spin');
                this.disabled = true;

                fetch(refreshUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ service: service }),
                })
                .then(r => r.json())
                .then(data => {
                    if (typeof AppAjax !== 'undefined') {
                        AppAjax.showToast(data.message ?? (data.success ? 'Berhasil' : 'Gagal'), data.success ? 'success' : 'error');
                    }
                    if (data.success) {
                        refreshTable();
                    }
                })
                .catch(() => {
                    if (typeof AppAjax !== 'undefined') {
                        AppAjax.showToast('Terjadi kesalahan saat menghubungi server.', 'error');
                    }
                })
                .finally(() => {
                    icon.classList.remove('fa-spin');
                    this.disabled = false;
                });
            });
        });

        // Auto-refresh every 60 seconds
        setInterval(refreshTable, 60000);
    </script>
@endsection
