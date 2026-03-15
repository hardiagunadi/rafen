@extends('layouts.admin')

@section('title', 'Log GenieACS')

@section('content')
<div class="row mb-3">
    <div class="col-md-4">
        <div class="small-box bg-danger">
            <div class="inner"><h3>{{ $stats['faults'] }}</h3><p>Fault / Error</p></div>
            <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="small-box bg-warning">
            <div class="inner"><h3>{{ $stats['tasks'] }}</h3><p>Task Pending</p></div>
            <div class="icon"><i class="fas fa-tasks"></i></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="small-box bg-info">
            <div class="inner"><h3>{{ $stats['devices'] }}</h3><p>Total Perangkat</p></div>
            <div class="icon"><i class="fas fa-router"></i></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h4 class="mb-0"><i class="fas fa-router text-primary mr-1"></i> Log GenieACS / TR-069</h4>
        <div class="d-flex align-items-center gap-2">
            <div class="btn-group btn-group-sm mr-2" id="genieacs-tabs">
                <button class="btn btn-danger active" data-tab="faults"><i class="fas fa-exclamation-triangle mr-1"></i>Faults</button>
                <button class="btn btn-outline-warning" data-tab="tasks"><i class="fas fa-tasks mr-1"></i>Tasks Pending</button>
                <button class="btn btn-outline-info" data-tab="devices"><i class="fas fa-router mr-1"></i>Devices</button>
            </div>
            <button class="btn btn-sm btn-outline-secondary" id="btn-auto-refresh" title="Auto-refresh 30 detik">
                <i class="fas fa-sync-alt mr-1"></i><span id="refresh-label">Auto Refresh</span>
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="genieacs-table" class="table table-hover table-sm mb-0">
                <thead class="thead-light"><tr id="genieacs-thead"></tr></thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function () {
    var currentTab = 'faults';
    var table;
    var autoRefreshTimer = null;
    var autoRefreshEnabled = false;

    var config = {
        faults: {
            headers: ['Perangkat', 'Pelanggan', 'Kode Error', 'Pesan', 'Retries', 'Waktu'],
            columns: [
                { data: 'device_id', render: (d) => `<code class="small">${escHtml(d)}</code>` },
                { data: 'customer_name' },
                { data: 'code', render: (d) => `<span class="badge badge-danger">${escHtml(d)}</span>` },
                { data: 'message', render: (d) => `<span class="text-danger small">${escHtml(d)}</span>` },
                { data: 'retries' },
                { data: 'timestamp' },
            ],
        },
        tasks: {
            headers: ['Perangkat', 'Pelanggan', 'Tipe Task', 'Detail', 'Waktu'],
            columns: [
                { data: 'device_id', render: (d) => `<code class="small">${escHtml(d)}</code>` },
                { data: 'customer_name' },
                { data: 'task_name', render: (d) => `<span class="badge badge-warning text-dark">${escHtml(d)}</span>` },
                { data: 'detail', render: (d) => `<span class="small text-muted">${escHtml(d)}</span>` },
                { data: 'timestamp' },
            ],
        },
        devices: {
            headers: ['Perangkat', 'Pelanggan', 'Serial', 'Model', 'Status', 'Inform Terakhir'],
            columns: [
                { data: 'device_id', render: (d) => `<code class="small">${escHtml(d)}</code>` },
                { data: 'customer_name' },
                { data: 'serial_number' },
                { data: 'model', render: (d, t, row) => escHtml((row.manufacturer !== '-' ? row.manufacturer + ' ' : '') + d) },
                { data: 'status', render: (d) => d === 'online'
                    ? '<span class="badge badge-success">Online</span>'
                    : '<span class="badge badge-secondary">Offline</span>' },
                { data: 'last_inform' },
            ],
        },
    };

    function escHtml(str) {
        return String(str ?? '-').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    function buildTable(tab) {
        var cfg = config[tab];

        // Update header
        document.getElementById('genieacs-thead').innerHTML = cfg.headers.map(h => `<th>${h}</th>`).join('');

        if ($.fn.DataTable.isDataTable('#genieacs-table')) {
            $('#genieacs-table').DataTable().destroy();
        }

        table = $('#genieacs-table').DataTable({
            processing: true,
            ajax: {
                url: '{{ route("logs.genieacs.data") }}',
                data: { tab: tab },
                dataSrc: function (json) {
                    if (json.error) {
                        toastr.error(json.error);
                        return [];
                    }
                    return json.data || [];
                },
            },
            columns: cfg.columns,
            pageLength: 25,
            order: [],
            stateSave: false,
            language: {
                processing: '<i class="fas fa-spinner fa-spin"></i> Memuat...',
                emptyTable: 'Tidak ada data.',
                zeroRecords: 'Tidak ada data yang cocok.',
            },
        });
    }

    function setTab(tab) {
        currentTab = tab;
        document.querySelectorAll('#genieacs-tabs .btn').forEach(function (btn) {
            var t = btn.dataset.tab;
            var active = (t === tab);
            btn.className = active
                ? (t === 'faults' ? 'btn btn-danger active' : t === 'tasks' ? 'btn btn-warning active' : 'btn btn-info active')
                : (t === 'faults' ? 'btn btn-outline-danger' : t === 'tasks' ? 'btn btn-outline-warning' : 'btn btn-outline-info');
        });
        buildTable(tab);
    }

    function toggleAutoRefresh() {
        autoRefreshEnabled = !autoRefreshEnabled;
        var label = document.getElementById('refresh-label');
        var btn   = document.getElementById('btn-auto-refresh');
        if (autoRefreshEnabled) {
            label.textContent = 'Stop Refresh';
            btn.className = 'btn btn-sm btn-warning';
            autoRefreshTimer = setInterval(function () {
                if (table) table.ajax.reload(null, false);
            }, 30000);
        } else {
            label.textContent = 'Auto Refresh';
            btn.className = 'btn btn-sm btn-outline-secondary';
            clearInterval(autoRefreshTimer);
        }
    }

    function init() {
        if (!document.getElementById('genieacs-table')) return;

        buildTable(currentTab);

        document.querySelectorAll('#genieacs-tabs .btn').forEach(function (btn) {
            btn.addEventListener('click', function () { setTab(this.dataset.tab); });
        });

        document.getElementById('btn-auto-refresh').addEventListener('click', toggleAutoRefresh);
    }

    document.addEventListener('DOMContentLoaded', init);
    if (document.readyState !== 'loading') init();
})();
</script>
@endsection
