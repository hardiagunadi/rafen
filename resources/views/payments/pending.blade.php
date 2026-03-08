@extends('layouts.admin')

@section('title', 'Konfirmasi Pembayaran Manual')

@section('content_header')
    <h1>Konfirmasi Pembayaran Manual</h1>
@endsection

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-clock mr-2"></i>Bukti Transfer Menunggu Konfirmasi</h3>
    </div>
    <div class="card-body p-0">
        <table id="pending-table" class="table table-bordered table-hover mb-0">
            <thead>
                <tr>
                    <th>Invoice / Payment</th>
                    <th>Pelanggan</th>
                    <th>Tagihan</th>
                    <th>Transfer</th>
                    <th>Tgl Upload</th>
                    <th>Aksi</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

{{-- Modal Lihat & Konfirmasi --}}
<div class="modal fade" id="modal-confirm" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-check-circle text-success mr-2"></i>Konfirmasi Pembayaran</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless">
                            <tr><th class="text-muted" style="width:40%">Invoice</th><td id="c-invoice">-</td></tr>
                            <tr><th class="text-muted">Payment #</th><td id="c-payment">-</td></tr>
                            <tr><th class="text-muted">Pelanggan</th><td id="c-customer">-</td></tr>
                            <tr><th class="text-muted">Tagihan</th><td id="c-amount">-</td></tr>
                            <tr><th class="text-muted">Jml Transfer</th><td id="c-transferred">-</td></tr>
                            <tr><th class="text-muted">Tgl Transfer</th><td id="c-date">-</td></tr>
                            <tr><th class="text-muted">Catatan</th><td id="c-notes" class="text-muted small">-</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6 text-center">
                        <div id="proof-wrapper">
                            <a id="proof-link" href="#" target="_blank">
                                <img id="proof-img" src="" alt="Bukti Transfer" class="img-fluid rounded border" style="max-height:300px; cursor:pointer;">
                            </a>
                            <div id="proof-empty" class="text-muted mt-3" style="display:none">
                                <i class="fas fa-image fa-3x mb-2"></i><br>Tidak ada bukti gambar
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <button type="button" class="btn btn-danger" id="btn-open-reject">
                    <i class="fas fa-times mr-1"></i>Tolak
                </button>
                <div>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-success ml-2" id="btn-confirm-pay">
                        <i class="fas fa-check mr-1"></i>Konfirmasi Bayar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal Tolak --}}
<div class="modal fade" id="modal-reject" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-times-circle mr-2"></i>Tolak Pembayaran</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p class="text-muted">Masukkan alasan penolakan. Pelanggan tidak akan diberitahu secara otomatis.</p>
                <div class="form-group mb-0">
                    <label>Alasan Penolakan <span class="text-danger">*</span></label>
                    <textarea id="rejection-reason" class="form-control" rows="3" maxlength="500" placeholder="mis. Nominal tidak sesuai, bukti tidak jelas..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="btn-back-to-confirm">Kembali</button>
                <button type="button" class="btn btn-danger" id="btn-do-reject">
                    <i class="fas fa-times mr-1"></i>Tolak Pembayaran
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('js')
<script>
(function () {
    var confirmUrl  = null;
    var rejectUrl   = null;
    var paymentId   = null;

    var table = $('#pending-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("payments.pending.datatable") }}',
        columns: [
            { data: null, render: function (d, t, row) {
                var inv = row.invoice_url
                    ? '<a href="' + row.invoice_url + '" class="font-weight-bold text-primary">' + row.invoice_number + '</a>'
                    : '<span class="font-weight-bold">' + row.invoice_number + '</span>';
                return inv + '<br><small class="text-muted">' + row.payment_number + '</small>';
            }},
            { data: null, render: function (d, t, row) {
                return '<div>' + row.customer_name + '</div>'
                     + '<small class="text-muted">' + (row.customer_id || '') + '</small>';
            }},
            { data: 'amount', render: function (d) { return 'Rp ' + d; }},
            { data: null, render: function (d, t, row) {
                return '<div>Rp ' + row.amount_transferred + '</div>'
                     + '<small class="text-muted">' + row.transfer_date + '</small>';
            }},
            { data: 'uploaded_at' },
            { data: null, orderable: false, render: function (d, t, row) {
                return '<button class="btn btn-sm btn-primary btn-view" '
                     + 'data-row=\'' + JSON.stringify(row).replace(/'/g, "&#39;") + '\'>'
                     + '<i class="fas fa-search mr-1"></i>Lihat & Konfirmasi</button>';
            }},
        ],
        pageLength: 20,
        stateSave: false,
        language: { processing: 'Memuat...', zeroRecords: 'Tidak ada pembayaran menunggu konfirmasi.' },
    });

    // Open confirm modal
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.btn-view');
        if (!btn) return;
        var row = JSON.parse(btn.dataset.row);

        confirmUrl = row.confirm_url;
        rejectUrl  = row.reject_url;

        document.getElementById('c-invoice').textContent    = row.invoice_number;
        document.getElementById('c-payment').textContent    = row.payment_number;
        document.getElementById('c-customer').textContent   = row.customer_name + (row.customer_id ? ' (' + row.customer_id + ')' : '');
        document.getElementById('c-amount').textContent     = 'Rp ' + row.amount;
        document.getElementById('c-transferred').textContent= 'Rp ' + row.amount_transferred;
        document.getElementById('c-date').textContent       = row.transfer_date;
        document.getElementById('c-notes').textContent      = row.catatan || '-';

        var img      = document.getElementById('proof-img');
        var link     = document.getElementById('proof-link');
        var empty    = document.getElementById('proof-empty');
        var wrapper  = document.getElementById('proof-wrapper');

        if (row.proof_url) {
            img.src      = row.proof_url;
            link.href    = row.proof_url;
            img.style.display  = '';
            empty.style.display = 'none';
        } else {
            img.style.display  = 'none';
            empty.style.display = '';
        }

        document.getElementById('rejection-reason').value = '';
        $('#modal-confirm').modal('show');
    });

    // Confirm payment
    document.getElementById('btn-confirm-pay').addEventListener('click', function () {
        if (!confirmUrl) return;
        var btn = this;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Memproses...';

        fetch(confirmUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
        .then(function (res) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check mr-1"></i>Konfirmasi Bayar';
            $('#modal-confirm').modal('hide');
            if (res.ok) {
                toastr.success(res.data.message || 'Pembayaran dikonfirmasi.');
                table.ajax.reload(null, false);
            } else {
                toastr.error(res.data.message || 'Gagal mengkonfirmasi.');
            }
        })
        .catch(function () {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check mr-1"></i>Konfirmasi Bayar';
            toastr.error('Terjadi kesalahan. Coba lagi.');
        });
    });

    // Open reject modal
    document.getElementById('btn-open-reject').addEventListener('click', function () {
        $('#modal-confirm').modal('hide');
        $('#modal-reject').modal('show');
    });

    // Back to confirm modal
    document.getElementById('btn-back-to-confirm').addEventListener('click', function () {
        $('#modal-reject').modal('hide');
        $('#modal-confirm').modal('show');
    });

    // Do reject
    document.getElementById('btn-do-reject').addEventListener('click', function () {
        var reason = document.getElementById('rejection-reason').value.trim();
        if (!reason) {
            toastr.warning('Masukkan alasan penolakan.');
            return;
        }
        if (!rejectUrl) return;

        var btn = this;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Memproses...';

        var body = new FormData();
        body.append('_token', '{{ csrf_token() }}');
        body.append('rejection_reason', reason);

        fetch(rejectUrl, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: body,
        })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
        .then(function (res) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-times mr-1"></i>Tolak Pembayaran';
            $('#modal-reject').modal('hide');
            if (res.ok) {
                toastr.success(res.data.message || 'Pembayaran ditolak.');
                table.ajax.reload(null, false);
            } else {
                toastr.error(res.data.message || 'Gagal menolak.');
            }
        })
        .catch(function () {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-times mr-1"></i>Tolak Pembayaran';
            toastr.error('Terjadi kesalahan. Coba lagi.');
        });
    });
}());
</script>
@endpush
