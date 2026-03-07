@extends('layouts.admin')

@section('title', 'Rekonsiliasi Nota Teknisi')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap" style="gap:.5rem;">
        <h4 class="mb-0">Rekonsiliasi Nota Teknisi</h4>
        <div class="d-flex align-items-center" style="gap:.5rem;">
            @if(auth()->user()->role === 'teknisi')
                <button class="btn btn-primary btn-sm" id="btn-buat-setoran">
                    <i class="fas fa-plus mr-1"></i>Buat Setoran Hari Ini
                </button>
            @endif
            <select id="filter-status" class="form-control form-control-sm" style="width:160px;">
                <option value="">Semua Status</option>
                <option value="draft">Draft</option>
                <option value="submitted">Disubmit</option>
                <option value="verified">Terverifikasi</option>
            </select>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="setoran-table" class="table table-hover table-sm mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>Tanggal</th>
                        <th>Teknisi</th>
                        <th class="text-center">Jml Nota</th>
                        <th class="text-right">Total Tagihan</th>
                        <th class="text-right">Total Tunai Setor</th>
                        <th class="text-center">Status</th>
                        <th>Diverifikasi Oleh</th>
                        <th class="text-right" style="width:100px;">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function () {
    var ROLE = '{{ auth()->user()->role }}';

    function statusBadge(status) {
        var map = {
            draft:     '<span class="badge badge-secondary">Draft</span>',
            submitted: '<span class="badge badge-warning">Disubmit</span>',
            verified:  '<span class="badge badge-success">Terverifikasi</span>',
        };
        return map[status] || status;
    }

    function init() {
        if (!document.getElementById('setoran-table')) return;
        if ($.fn.DataTable.isDataTable('#setoran-table')) return;

        var table = $('#setoran-table').DataTable({
            processing: true, serverSide: true,
            ajax: {
                url: '{{ route("teknisi-setoran.datatable") }}',
                data: function (d) {
                    d.status = $('#filter-status').val();
                }
            },
            columns: [
                { data: 'period_date' },
                { data: 'teknisi_name' },
                { data: 'total_invoices', className: 'text-center' },
                { data: 'total_tagihan', render: function(d) { return 'Rp ' + d; }, className: 'text-right' },
                { data: 'total_cash',    render: function(d) { return 'Rp ' + d; }, className: 'text-right' },
                { data: 'status', render: statusBadge, className: 'text-center', orderable: false },
                { data: 'verified_by' },
                { data: null, orderable: false, className: 'text-right', render: function(d, t, row) {
                    return '<a href="' + row.show_url + '" class="btn btn-info btn-sm"><i class="fas fa-eye"></i></a>';
                }},
            ],
            pageLength: 20, stateSave: false, order: [[0, 'desc']],
        });

        $('#filter-status').on('change', function () { table.ajax.reload(); });

        // Tombol buat setoran hari ini (teknisi)
        var btnBuat = document.getElementById('btn-buat-setoran');
        if (btnBuat) {
            btnBuat.addEventListener('click', function () {
                if (!confirm('Buat setoran untuk hari ini?')) return;
                btnBuat.disabled = true;
                fetch('{{ route("teknisi-setoran.store") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ period_date: '{{ today()->toDateString() }}' }),
                })
                .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
                .then(function (res) {
                    btnBuat.disabled = false;
                    if (res.ok) {
                        if (window.showToast) showToast(res.data.status || 'Setoran dibuat.', 'success');
                        window.location.href = '{{ route("teknisi-setoran.index") }}/' + res.data.id;
                    } else {
                        if (window.showToast) showToast(res.data.error || 'Gagal membuat setoran.', 'danger');
                    }
                })
                .catch(function () {
                    btnBuat.disabled = false;
                    if (window.showToast) showToast('Terjadi kesalahan.', 'danger');
                });
            });
        }
    }

    document.addEventListener('DOMContentLoaded', init);
    if (document.readyState !== 'loading') init();
})();
</script>
@endsection
