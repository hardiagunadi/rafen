@extends('layouts.admin')

@section('title', 'Data Tagihan')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap" style="gap:.5rem;">
        <h4 class="mb-0">Rekap Tagihan</h4>
        <select id="filter-status" class="form-control form-control-sm" style="width:160px;">
            <option value="">Semua Status</option>
            <option value="unpaid">Belum Bayar</option>
            <option value="paid">Lunas</option>
        </select>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="invoice-table" class="table table-hover table-sm mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>Invoice</th>
                        <th>Pelanggan</th>
                        <th>Tipe / Paket</th>
                        <th>Tagihan</th>
                        <th>Jatuh Tempo</th>
                        <th style="width:80px;">Status</th>
                        <th class="text-right" style="width:160px;">Aksi</th>
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
    }
    document.addEventListener('DOMContentLoaded', init);
    if (document.readyState !== 'loading') init();
})();
</script>
@endsection
