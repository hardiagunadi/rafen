@extends('layouts.admin')

@section('title', 'Pengaturan Koneksi')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0">Router [ NAS ]</h3>
        <a href="{{ route('mikrotik-connections.create') }}" class="btn btn-info btn-sm text-white">
            <i class="fas fa-plus"></i> TAMBAH ROUTER [NAS]
        </a>
    </div>
    <div class="card-body">
        <div class="mb-3">
            <strong>INFO :</strong>
            <ul class="mb-0">
                <li>Sistem mengecek ping ke router setiap 5 menit, tabel refresh otomatis setiap 1 menit.</li>
                <li><span class="badge badge-success">Terhubung</span> — ping reply konsisten &amp; port API terbuka.</li>
                <li><span class="badge badge-warning">Tidak Stabil</span> — ping putus-nyambung (ada kegagalan tapi belum melewati threshold).</li>
                <li><span class="badge badge-danger">Tidak Terhubung</span> — RTO, ping gagal melewati threshold.</li>
            </ul>
        </div>
        <div class="table-responsive">
            <table id="router-table" class="table table-hover table-sm mb-0">
                <thead class="thead-light">
                    <tr>
                        <th style="width:56px;">API</th>
                        <th style="width:130px;">Status Ping</th>
                        <th>Detail</th>
                        <th>Nama Router</th>
                        <th>IP Address</th>
                        <th>User Online</th>
                        <th>Cek Terakhir</th>
                        <th class="text-right" style="width:90px;">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function () {
    var dtTable;
    var refreshTimer;

    function renderPing(d, t, row) {
        return '<span class="badge ' + row.ping_class + '">' + row.ping_status + '</span>';
    }

    function renderAksi(d, t, row) {
        return '<div class="text-right">'
            + '<a href="' + row.edit_url + '" class="btn btn-sm btn-warning text-white mr-1"><i class="fas fa-pen"></i></a>'
            + '<button class="btn btn-sm btn-danger" data-ajax-delete="' + row.destroy_url + '" data-confirm="Hapus koneksi ini?"><i class="fas fa-trash"></i></button>'
            + '</div>';
    }

    function init() {
        if (!document.getElementById('router-table')) return;
        if ($.fn.DataTable.isDataTable('#router-table')) return;

        dtTable = $('#router-table').DataTable({
            processing: true, serverSide: true,
            ajax: { url: '{{ route("mikrotik-connections.datatable") }}' },
            columns: [
                { data: null, orderable: false, render: function(d, t, row) {
                    return '<a href="' + row.api_url + '" class="btn btn-info btn-sm text-white" title="Buka API Dashboard"><i class="fas fa-plug"></i></a>';
                }},
                { data: 'ping_status', render: renderPing, orderable: false },
                { data: 'ping_message', orderable: false },
                { data: 'name' },
                { data: 'host', orderable: false },
                { data: 'radius_count', render: function(d) {
                    return '<i class="fas fa-chart-bar"></i> <strong>active ' + d + '</strong>';
                }, orderable: false },
                { data: 'last_ping_at', orderable: false },
                { data: null, render: renderAksi, orderable: false },
            ],
            pageLength: 20, stateSave: false,
        });

        refreshTimer = setInterval(function () {
            if (dtTable) dtTable.ajax.reload(null, false);
        }, 60000);
    }
    document.addEventListener('DOMContentLoaded', init);
    if (document.readyState !== 'loading') init();
})();
</script>
@endsection
