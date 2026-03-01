@extends('layouts.admin')

@section('title', 'Session User')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0">Session PPPoE Aktif</h3>
            <button type="button" id="btn-refresh-all" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-sync-alt"></i> Refresh Semua Router
            </button>
        </div>

        <div class="card-body">
            {{-- Stats --}}
            <div class="row text-center mb-3">
                <div class="col-md-4 col-sm-6 mb-2">
                    <div class="p-3 border rounded h-100 d-flex align-items-center">
                        <div class="mr-3 text-success"><i class="fas fa-wifi fa-2x"></i></div>
                        <div class="text-left">
                            <div class="small text-uppercase text-muted">Total PPPoE Online</div>
                            <div class="h4 mb-0 font-weight-bold" id="total-count">{{ $total }}</div>
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
                            <div class="small text-uppercase text-muted">Auto-Refresh</div>
                            <div class="h4 mb-0 font-weight-bold" id="refresh-countdown">60s</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Filter --}}
            <div class="row mb-3">
                <div class="col-md-4 col-sm-6">
                    <select id="filter-router" class="form-control form-control-sm">
                        <option value="">-- Semua Router --</option>
                        @foreach ($routers as $router)
                            <option value="{{ $router->id }}">{{ $router->name }} ({{ $router->host }})</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="table-responsive">
                <table id="dt-pppoe" class="table table-hover table-sm align-middle mb-0 w-100">
                    <thead class="thead-light">
                        <tr>
                            <th>Username</th>
                            <th>IP Address</th>
                            <th>Uptime</th>
                            <th>Caller-ID</th>
                            <th>Upload</th>
                            <th>Download</th>
                            <th>Profile</th>
                            <th>Router</th>
                            <th>Diperbarui</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
    (function () {
        var dtTable = null;
        var autoRefreshTimer = null;
        var countdown = 60;
        var countdownTimer = null;
        var dtUrl = '{{ route("sessions.pppoe.datatable") }}';
        var refreshAllUrl = '{{ route("sessions.refresh-all") }}';
        var csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

        function startCountdown() {
            if (countdownTimer) clearInterval(countdownTimer);
            countdown = 60;
            countdownTimer = setInterval(function () {
                countdown--;
                var el = document.getElementById('refresh-countdown');
                if (el) el.textContent = countdown + 's';
                if (countdown <= 0) {
                    countdown = 60;
                    if (dtTable) dtTable.ajax.reload(null, false);
                }
            }, 1000);
        }

        function init() {
            if (!document.getElementById('dt-pppoe')) return;
            if (dtTable) { dtTable.destroy(); dtTable = null; }
            if (autoRefreshTimer) { clearInterval(autoRefreshTimer); }
            if (countdownTimer) { clearInterval(countdownTimer); }

            dtTable = $('#dt-pppoe').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: dtUrl,
                    data: function (d) {
                        d.router_id = $('#filter-router').val();
                    },
                },
                columns: [
                    { data: 'username', render: function (v) { return '<strong>' + v + '</strong>'; } },
                    { data: 'ipv4', render: function (v) { return '<code>' + v + '</code>'; } },
                    { data: 'uptime', render: function (v) { return '<span class="badge badge-success">' + v + '</span>'; } },
                    { data: 'caller_id' },
                    { data: 'bytes_in', render: function (v) { return '<small>' + v + '</small>'; } },
                    { data: 'bytes_out', render: function (v) { return '<small>' + v + '</small>'; } },
                    { data: 'profile' },
                    { data: 'router', render: function (v) { return '<span class="badge badge-info">' + v + '</span>'; } },
                    { data: 'updated_at', render: function (v) { return '<small class="text-muted">' + v + '</small>'; } },
                ],
                language: { url: false, emptyTable: 'Belum ada sesi PPPoE aktif.', processing: 'Memuat...', search: 'Cari:', lengthMenu: 'Tampilkan _MENU_ baris', info: 'Menampilkan _START_-_END_ dari _TOTAL_', paginate: { next: 'Berikutnya', previous: 'Sebelumnya' } },
                pageLength: 25,
            });

            $('#filter-router').off('change.pppoe').on('change.pppoe', function () {
                if (dtTable) dtTable.ajax.reload(null, false);
            });

            startCountdown();
        }

        document.addEventListener('turbo:before-cache', function () {
            if (dtTable) { dtTable.destroy(); dtTable = null; }
            if (countdownTimer) { clearInterval(countdownTimer); countdownTimer = null; }
        });

        document.getElementById('btn-refresh-all') && document.getElementById('btn-refresh-all').addEventListener('click', function () {
            var btn = this;
            btn.disabled = true;
            fetch(refreshAllUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (dtTable) dtTable.ajax.reload(null, false);
                    countdown = 60;
                })
                .finally(function () { btn.disabled = false; });
        });

        document.addEventListener('turbo:load', init);
        if (document.readyState !== 'loading') init();
    })();
    </script>
@endsection
