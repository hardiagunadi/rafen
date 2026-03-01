@extends('layouts.admin')

@section('title', 'Manajemen Voucher')

@section('content')
    <div class="card" style="overflow: visible;">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap" style="overflow: visible;">
            <div class="btn-group">
                <div class="dropdown">
                    <button class="btn btn-success btn-sm dropdown-toggle" type="button" data-toggle="dropdown" data-display="static" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-bars"></i> Manajemen Voucher
                    </button>
                    <div class="dropdown-menu dropdown-menu-left" style="min-width: 200px;">
                        <a class="dropdown-item" href="{{ route('vouchers.create') }}">Generate Voucher Baru</a>
                        <a class="dropdown-item" href="{{ route('vouchers.index') }}">List Semua Voucher</a>
                        <div class="dropdown-header text-danger text-uppercase">Aksi Massal</div>
                        <a class="dropdown-item text-danger bulk-delete-action" href="#">Hapus Unused Terpilih</a>
                    </div>
                </div>
            </div>
            <div class="mt-2 mt-sm-0">
                <h4 class="mb-0">Voucher Hotspot</h4>
            </div>
        </div>

        <div class="card-body">
            <div class="row text-center mb-3">
                <div class="col-md-4 mb-2">
                    <div class="p-3 border rounded h-100 d-flex align-items-center">
                        <div class="mr-3 text-success"><i class="fas fa-ticket-alt fa-2x"></i></div>
                        <div class="text-left">
                            <div class="small text-uppercase text-muted">Unused</div>
                            <div class="h5 mb-0">{{ number_format($stats['unused']) }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-2">
                    <div class="p-3 border rounded h-100 d-flex align-items-center">
                        <div class="mr-3 text-info"><i class="fas fa-check-circle fa-2x"></i></div>
                        <div class="text-left">
                            <div class="small text-uppercase text-muted">Used</div>
                            <div class="h5 mb-0">{{ number_format($stats['used']) }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-2">
                    <div class="p-3 border rounded h-100 d-flex align-items-center">
                        <div class="mr-3 text-secondary"><i class="fas fa-times-circle fa-2x"></i></div>
                        <div class="text-left">
                            <div class="small text-uppercase text-muted">Expired</div>
                            <div class="h5 mb-0">{{ number_format($stats['expired']) }}</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Filter status & batch --}}
            <div class="d-flex flex-wrap mb-3">
                <div class="form-group mb-2 mr-2">
                    <select id="filter-status" class="form-control form-control-sm">
                        <option value="">- Semua Status -</option>
                        <option value="unused">UNUSED</option>
                        <option value="used">USED</option>
                        <option value="expired">EXPIRED</option>
                    </select>
                </div>
                <div class="form-group mb-2 mr-2">
                    <select id="filter-batch" class="form-control form-control-sm">
                        <option value="">- Semua Batch -</option>
                        @foreach($batches as $b)
                            <option value="{{ $b }}">{{ $b }}</option>
                        @endforeach
                    </select>
                </div>
                <div id="print-batch-btn" class="mb-2" style="display:none;">
                    <a id="print-batch-link" href="#" class="btn btn-sm btn-outline-secondary" target="_blank">
                        <i class="fas fa-print"></i> Print Batch
                    </a>
                </div>
            </div>

            <div class="table-responsive">
                <table id="vouchers-table" class="table table-striped table-hover mb-0" style="width:100%;">
                    <thead class="thead-light">
                        <tr>
                            <th style="width:40px;"><input type="checkbox" id="select-all"></th>
                            <th>Kode</th>
                            <th>Batch</th>
                            <th>Profil Hotspot</th>
                            <th>Status</th>
                            <th>Expired</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

        <form id="bulk-delete-form" action="{{ route('vouchers.bulk-destroy') }}" method="POST">
            @csrf
            @method('DELETE')
        </form>
    </div>

    <script>
    (function () {
        var dtTable = null;

        function init() {
            if (!document.getElementById('vouchers-table')) return;
            if (dtTable) { dtTable.destroy(); dtTable = null; }

            dtTable = $('#vouchers-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route("vouchers.datatable") }}',
                    type: 'GET',
                    data: function (d) {
                        d.status = $('#filter-status').val();
                        d.batch  = $('#filter-batch').val();
                    },
                },
                columns: [
                    { data: 'checkbox', orderable: false, searchable: false, width: '40px' },
                    { data: 'code',     orderable: true },
                    { data: 'batch',    orderable: true },
                    { data: 'profil',   orderable: false },
                    { data: 'status',   orderable: false },
                    { data: 'expired',  orderable: true },
                    { data: 'aksi',     orderable: false, searchable: false, className: 'text-right' },
                ],
                language: {
                    search: 'Cari:', lengthMenu: 'Tampilkan _MENU_ data',
                    info: 'Menampilkan _START_ - _END_ dari _TOTAL_ data',
                    infoEmpty: 'Tidak ada data', infoFiltered: '(disaring dari _MAX_ total data)',
                    zeroRecords: 'Tidak ada voucher yang cocok.', emptyTable: 'Belum ada voucher.',
                    paginate: { first: 'Pertama', last: 'Terakhir', next: 'Selanjutnya', previous: 'Sebelumnya' },
                    processing: 'Memuat...',
                },
                pageLength: 20,
                lengthMenu: [[20, 50, 100, 200], [20, 50, 100, 200]],
                order: [[1, 'asc']],
                stateSave: false,
                drawCallback: function () {
                    document.querySelectorAll('[data-ajax-delete]').forEach(function (btn) {
                        if (btn._bound) return;
                        btn._bound = true;
                        btn.addEventListener('click', function () {
                            if (!confirm(btn.dataset.confirm || 'Hapus?')) return;
                            fetch(btn.dataset.ajaxDelete, {
                                method: 'POST',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                },
                                body: new URLSearchParams({ _method: 'DELETE' }),
                            }).then(function (r) { return r.json(); }).then(function (data) {
                                if (typeof AppAjax !== 'undefined') AppAjax.showToast(data.status || 'Dihapus.', 'success');
                                if (dtTable) dtTable.ajax.reload(null, false);
                            });
                        });
                    });
                },
            });

            $('#filter-status').off('change.voucher').on('change.voucher', function () {
                if (dtTable) dtTable.ajax.reload(null, true);
            });

            $('#filter-batch').off('change.voucher').on('change.voucher', function () {
                var batchVal = $(this).val();
                if (batchVal) {
                    $('#print-batch-btn').show();
                    $('#print-batch-link')
                        .attr('href', '{{ url("vouchers") }}/' + encodeURIComponent(batchVal) + '/print')
                        .html('<i class="fas fa-print"></i> Print Batch "' + $('<span>').text(batchVal).html() + '"');
                } else {
                    $('#print-batch-btn').hide();
                }
                if (dtTable) dtTable.ajax.reload(null, true);
            });

            $('#select-all').off('change.voucher').on('change.voucher', function () {
                $('#vouchers-table tbody input[type="checkbox"]:not(:disabled)').prop('checked', this.checked);
            });

            $('.bulk-delete-action').off('click.voucher').on('click.voucher', function (e) {
                e.preventDefault();
                var ids = $('#vouchers-table tbody input[name="ids[]"]:checked').map(function () { return this.value; }).get();
                if (!ids.length) { alert('Pilih voucher unused terlebih dahulu.'); return; }
                if (!confirm('Hapus ' + ids.length + ' voucher terpilih?')) return;
                var form = document.getElementById('bulk-delete-form');
                ids.forEach(function (id) {
                    var inp = document.createElement('input');
                    inp.type = 'hidden'; inp.name = 'ids[]'; inp.value = id;
                    form.appendChild(inp);
                });
                form.submit();
            });
        }

        document.addEventListener('turbo:before-cache', function () {
            if (dtTable) { dtTable.destroy(); dtTable = null; }
        });
        document.addEventListener('turbo:load', init);
        if (document.readyState !== 'loading') init();
    })();
    </script>
@endsection
