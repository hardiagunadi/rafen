@extends('layouts.admin')

@section('title', 'Data Tagihan')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap" style="gap:.5rem;">
        <h4 class="mb-0">Rekap Tagihan</h4>
        <div class="d-flex align-items-center" style="gap:.5rem;">
            <select id="filter-status" class="form-control form-control-sm" style="width:160px;">
                <option value="">Semua Status</option>
                <option value="unpaid">Belum Bayar</option>
                <option value="paid">Lunas</option>
            </select>
            <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-toggle="dropdown" title="Pilih otomatis">
                    <i class="fas fa-check-square"></i>
                </button>
                <div class="dropdown-menu">
                    <a class="dropdown-item btn-auto-check" data-n="5" href="#">Pilih 5 teratas</a>
                    <a class="dropdown-item btn-auto-check" data-n="10" href="#">Pilih 10 teratas</a>
                    <a class="dropdown-item btn-auto-check" data-n="20" href="#">Pilih 20 teratas</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" id="btn-uncheck-all" href="#">Batal semua pilihan</a>
                </div>
            </div>
            <button id="btn-bulk-nota" class="btn btn-secondary btn-sm" disabled>
                <i class="fas fa-receipt mr-1"></i><span>Cetak Nota Terpilih</span>
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="invoice-table" class="table table-hover table-sm mb-0">
                <thead class="thead-light">
                    <tr>
                        <th style="width:30px;"><input type="checkbox" id="check-all"></th>
                        <th>Invoice</th>
                        <th>Pelanggan</th>
                        <th>Tipe / Paket</th>
                        <th>Tagihan</th>
                        <th>Jatuh Tempo</th>
                        <th style="width:80px;">Status</th>
                        <th class="text-right" style="width:170px;">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function () {
    function statusBadge(status) {
        return status === 'paid'
            ? '<span class="badge badge-success">Lunas</span>'
            : '<span class="badge badge-warning">Belum Bayar</span>';
    }

    function renderAksi(d, type, row) {
        var renew = '<button class="btn btn-primary btn-sm mr-1"'
            + (row.can_renew ? ' data-ajax-post="' + row.renew_url + '" data-confirm="Perpanjang layanan tanpa pembayaran?"' : ' disabled')
            + '><i class="fas fa-bolt"></i></button>';
        var pay = '<button class="btn btn-success btn-sm mr-1"'
            + (row.can_pay ? ' data-ajax-post="' + row.pay_url + '" data-confirm="Bayar dan perpanjang layanan sekarang?"' : ' disabled')
            + '><i class="fas fa-check"></i></button>';
        var view = '<a href="' + row.show_url + '" class="btn btn-info btn-sm mr-1" title="Lihat Invoice"><i class="fas fa-eye"></i></a>';
        var nota = '<a href="' + row.nota_url + '" target="_blank" class="btn btn-secondary btn-sm mr-1" title="Cetak Nota"><i class="fas fa-receipt"></i></a>';
        var del = '<button class="btn btn-danger btn-sm"'
            + ' data-ajax-delete="' + row.destroy_url + '" data-confirm="Hapus invoice ini?"'
            + '><i class="fas fa-trash"></i></button>';
        return '<div class="text-right d-flex justify-content-end" style="gap:2px;">' + renew + pay + view + nota + del + '</div>';
    }

    function updateBulkBtn() {
        var checked = $('#invoice-table tbody input.row-check:checked').length;
        $('#btn-bulk-nota').prop('disabled', checked === 0);
        $('#btn-bulk-nota span').text(checked > 0 ? 'Cetak Nota Terpilih (' + checked + ')' : 'Cetak Nota Terpilih');
    }

    function init() {
        if (!document.getElementById('invoice-table')) return;
        if ($.fn.DataTable.isDataTable('#invoice-table')) return;

        var table = $('#invoice-table').DataTable({
            processing: true, serverSide: true,
            ajax: {
                url: '{{ route("invoices.datatable") }}',
                data: function (d) { d.status = $('#filter-status').val(); }
            },
            columns: [
                { data: 'id', orderable: false, render: function(d) {
                    return '<input type="checkbox" class="row-check" value="' + d + '">';
                }},
                { data: 'invoice_number', render: function(d, t, row) {
                    return '<div class="font-weight-bold">' + d + '</div>'
                         + '<div class="text-muted small">' + row.owner_name + '</div>';
                }},
                { data: 'customer_name', render: function(d, t, row) {
                    return '<div>' + $.fn.dataTable.render.text().display(d) + '</div>'
                         + '<div class="text-muted small">' + (row.customer_id || '') + '</div>';
                }},
                { data: 'tipe_service', render: function(d, t, row) {
                    return d + '<br><small class="text-muted">' + $.fn.dataTable.render.text().display(row.paket_langganan) + '</small>';
                }},
                { data: 'total', render: function(d) { return 'Rp ' + d; }},
                { data: 'due_date' },
                { data: 'status', render: statusBadge, orderable: false },
                { data: null, render: renderAksi, orderable: false },
            ],
            pageLength: 20, stateSave: false,
        });

        $('#filter-status').on('change', function () { table.ajax.reload(); });

        $(document).on('ajax:success', function () { table.ajax.reload(null, false); });

        // Check-all
        $(document).on('change', '#check-all', function () {
            var checked = $(this).prop('checked');
            $('#invoice-table tbody input.row-check').prop('checked', checked);
            updateBulkBtn();
        });

        $(document).on('change', '#invoice-table tbody input.row-check', function () {
            var total = $('#invoice-table tbody input.row-check').length;
            var checkedCount = $('#invoice-table tbody input.row-check:checked').length;
            $('#check-all').prop('indeterminate', checkedCount > 0 && checkedCount < total);
            $('#check-all').prop('checked', checkedCount === total && total > 0);
            updateBulkBtn();
        });

        // Auto-check N teratas
        $(document).on('click', '.btn-auto-check', function (e) {
            e.preventDefault();
            var n = parseInt($(this).data('n'));
            var boxes = $('#invoice-table tbody input.row-check');
            boxes.prop('checked', false);
            boxes.slice(0, n).prop('checked', true);
            var total = boxes.length;
            var checkedCount = boxes.filter(':checked').length;
            $('#check-all').prop('indeterminate', checkedCount > 0 && checkedCount < total);
            $('#check-all').prop('checked', checkedCount === total && total > 0);
            updateBulkBtn();
        });

        // Batal semua pilihan
        $(document).on('click', '#btn-uncheck-all', function (e) {
            e.preventDefault();
            $('#invoice-table tbody input.row-check').prop('checked', false);
            $('#check-all').prop('checked', false).prop('indeterminate', false);
            updateBulkBtn();
        });

        // Reset on page change
        table.on('draw', function () {
            $('#check-all').prop('checked', false).prop('indeterminate', false);
            updateBulkBtn();
        });

        // Bulk print
        $('#btn-bulk-nota').on('click', function () {
            var ids = $('#invoice-table tbody input.row-check:checked').map(function () {
                return $(this).val();
            }).get();
            if (ids.length === 0) return;
            var url = '{{ route("invoices.nota-bulk") }}?ids=' + ids.join(',');
            window.open(url, '_blank');
        });
    }
    document.addEventListener('DOMContentLoaded', init);
    if (document.readyState !== 'loading') init();
})();
</script>
@endsection
