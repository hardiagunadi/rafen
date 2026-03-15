@extends('layouts.admin')

@section('title', 'CPE Management')

@section('content')
@php
    $currentUser = auth()->user();
    $canManage = $currentUser->isSuperAdmin() || in_array($currentUser->role, ['administrator', 'noc', 'it_support'], true);
@endphp

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0">
            <i class="fas fa-router mr-2"></i> CPE Management
        </h3>
    </div>
    <div class="card-body p-0">
        <ul class="nav nav-tabs px-3 pt-2" id="cpeTab">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="tab" href="#tab-linked">
                    <i class="fas fa-link mr-1 text-success"></i> Terhubung
                </a>
            </li>
            @if($canManage)
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#tab-unlinked" id="tab-unlinked-link">
                    <i class="fas fa-unlink mr-1 text-warning"></i> Belum Terhubung
                    <span class="badge badge-warning ml-1" id="unlinked-count" style="display:none"></span>
                </a>
            </li>
            @endif
        </ul>

        <div class="tab-content px-3 pb-3 pt-2">
            {{-- Tab: Terhubung --}}
            <div class="tab-pane fade show active" id="tab-linked">
                <table id="cpe-table" class="table table-bordered table-hover mt-2" style="width:100%">
                    <thead>
                        <tr>
                            <th>Pelanggan</th>
                            <th>Username PPPoE</th>
                            <th>Pabrikan / Model</th>
                            <th>Firmware</th>
                            <th>Inform Period</th>
                            <th>Status</th>
                            <th>Terakhir Online</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>

            {{-- Tab: Belum Terhubung --}}
            @if($canManage)
            <div class="tab-pane fade" id="tab-unlinked">
                <div class="d-flex justify-content-between align-items-center mt-2 mb-2">
                    <small class="text-muted">Device yang sudah inform ke GenieACS tapi belum terhubung ke PPP user manapun.</small>
                    <button class="btn btn-sm btn-outline-secondary" id="btn-reload-unlinked">
                        <i class="fas fa-sync-alt mr-1"></i> Refresh
                    </button>
                </div>
                <table id="unlinked-table" class="table table-bordered table-hover" style="width:100%">
                    <thead>
                        <tr>
                            <th>GenieACS Device ID</th>
                            <th>Pabrikan / Model</th>
                            <th>Serial</th>
                            <th>PPPoE Username (dari modem)</th>
                            <th>Terakhir Inform</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="unlinked-tbody">
                        <tr><td colspan="6" class="text-center text-muted">Klik tab untuk memuat data...</td></tr>
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>
</div>

{{-- Modal: Link ke PPP User --}}
@if($canManage)
<div class="modal fade" id="modalLinkDevice" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-link mr-1"></i> Hubungkan ke PPP User</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="link-genieacs-id">
                <div class="form-group mb-2">
                    <label class="small mb-1">GenieACS Device ID</label>
                    <input type="text" class="form-control form-control-sm" id="link-genieacs-id-display" readonly>
                </div>
                <div class="form-group mb-2">
                    <label class="small mb-1">PPPoE Username di Modem</label>
                    <input type="text" class="form-control form-control-sm" id="link-pppoe-hint" readonly>
                </div>
                <div class="form-group mb-0">
                    <label class="small mb-1">Pilih PPP User <span class="text-danger">*</span></label>
                    <select class="form-control form-control-sm" id="link-ppp-user-select">
                        <option value="">-- Ketik untuk cari --</option>
                    </select>
                    <small class="form-text text-muted">Cari berdasarkan nama pelanggan atau username PPPoE.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-do-link">
                    <i class="fas fa-link mr-1"></i> Hubungkan
                </button>
            </div>
        </div>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
$(function () {
    var csrfToken = '{{ csrf_token() }}';

    // ── Tab: Terhubung ───────────────────────────────────────────────────────
    var table = $('#cpe-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: { url: '{{ route('cpe.datatable') }}', type: 'GET' },
        columns: [
            {
                data: 'customer_name',
                render: function (data, type, row) {
                    if (row.ppp_user_id) {
                        return '<a href="/ppp-users/' + row.ppp_user_id + '/edit">' + data + '</a>';
                    }
                    return '<span class="text-muted">-</span>';
                }
            },
            { data: 'username' },
            {
                data: 'manufacturer',
                render: function (data, type, row) {
                    var m = row.model !== '-' ? row.model : '';
                    return data + (m ? ' / ' + m : '');
                }
            },
            { data: 'firmware' },
            {
                data: 'inform_interval',
                render: function (data) {
                    if (!data) return '<span class="text-muted">-</span>';
                    if (data <= 300) return '<span class="badge badge-success">' + data + 's</span>';
                    if (data <= 900) return '<span class="badge badge-warning">' + data + 's</span>';
                    return '<span class="badge badge-danger">' + data + 's</span>';
                }
            },
            {
                data: 'status',
                render: function (data) {
                    var map = {
                        online:  '<span class="badge badge-success">Online</span>',
                        offline: '<span class="badge badge-danger">Offline</span>',
                        unknown: '<span class="badge badge-secondary">Tidak Diketahui</span>',
                    };
                    return map[data] || '<span class="badge badge-secondary">' + data + '</span>';
                }
            },
            { data: 'last_seen_at' },
            {
                data: 'ppp_user_id',
                orderable: false,
                searchable: false,
                render: function (data, type, row) {
                    var html = '<div class="text-right">';
                    if (data) {
                        html += '<a href="/ppp-users/' + data + '/edit" class="btn btn-sm btn-outline-primary" title="Detail Pelanggan"><i class="fas fa-user"></i></a> ';
                    }
                    @if($canManage)
                    html += '<button class="btn btn-sm btn-outline-warning btn-reboot" data-id="' + data + '" title="Reboot"><i class="fas fa-power-off"></i></button>';
                    @endif
                    html += '</div>';
                    return html;
                }
            },
        ],
        language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json' },
    });

    @if($canManage)
    $('#cpe-table').on('click', '.btn-reboot', function () {
        var pppUserId = $(this).data('id');
        if (!pppUserId || !confirm('Yakin ingin mereboot perangkat ini?')) return;
        $.ajax({
            url: '/ppp-users/' + pppUserId + '/cpe/reboot',
            method: 'POST',
            data: { _token: csrfToken },
            success: function (res) { toastr.success(res.message); },
            error: function (xhr) { toastr.error(xhr.responseJSON?.message || 'Gagal reboot.'); },
        });
    });

    // ── Tab: Belum Terhubung ─────────────────────────────────────────────────
    var unlinkedLoaded = false;

    function loadUnlinked() {
        $('#unlinked-tbody').html('<tr><td colspan="6" class="text-center"><i class="fas fa-spinner fa-spin mr-1"></i> Memuat...</td></tr>');
        $.get('{{ route('cpe.unlinked') }}', function (data) {
            var count = data.length;
            if (count > 0) {
                $('#unlinked-count').text(count).show();
            } else {
                $('#unlinked-count').hide();
            }

            if (count === 0) {
                $('#unlinked-tbody').html('<tr><td colspan="6" class="text-center text-muted py-3"><i class="fas fa-check-circle text-success mr-1"></i> Semua device sudah terhubung.</td></tr>');
                return;
            }

            var rows = '';
            data.forEach(function (d) {
                var pppHint = d.pppoe_user !== '-'
                    ? '<span class="text-danger font-weight-bold">' + d.pppoe_user + '</span><br><small class="text-muted">Tidak cocok dengan PPP user manapun</small>'
                    : '<span class="text-muted">-</span>';

                rows += '<tr>';
                rows += '<td><small class="text-monospace">' + d.genieacs_id + '</small></td>';
                rows += '<td>' + d.manufacturer + (d.model !== '-' ? ' / ' + d.model : '') + '</td>';
                rows += '<td>' + d.serial + '</td>';
                rows += '<td>' + pppHint + '</td>';
                rows += '<td>' + d.last_inform + '</td>';
                rows += '<td class="text-right">'
                    + '<button class="btn btn-sm btn-primary btn-link-device" '
                    + 'data-id="' + d.genieacs_id + '" '
                    + 'data-pppoe="' + d.pppoe_user + '" '
                    + 'title="Hubungkan ke PPP User">'
                    + '<i class="fas fa-link mr-1"></i> Hubungkan</button>'
                    + '</td>';
                rows += '</tr>';
            });
            $('#unlinked-tbody').html(rows);
        }).fail(function () {
            $('#unlinked-tbody').html('<tr><td colspan="6" class="text-center text-danger">Gagal memuat data.</td></tr>');
        });
    }

    // Load saat tab dibuka
    $(document).on('shown.bs.tab', 'a[href="#tab-unlinked"]', function () {
        if (!unlinkedLoaded) {
            loadUnlinked();
            unlinkedLoaded = true;
        }
    });

    $('#btn-reload-unlinked').on('click', function () {
        unlinkedLoaded = false;
        loadUnlinked();
        unlinkedLoaded = true;
    });

    // ── Modal: Link ke PPP User ──────────────────────────────────────────────
    $(document).on('click', '.btn-link-device', function () {
        var genieId = $(this).data('id');
        var pppHint = $(this).data('pppoe');
        $('#link-genieacs-id').val(genieId);
        $('#link-genieacs-id-display').val(genieId);
        $('#link-pppoe-hint').val(pppHint !== '-' ? pppHint : '(tidak terdeteksi)');
        $('#link-ppp-user-select').val('').trigger('change');
        $('#modalLinkDevice').modal('show');
    });

    // Select2 untuk pilih PPP user
    if ($.fn.select2) {
        $('#link-ppp-user-select').select2({
            dropdownParent: $('#modalLinkDevice'),
            placeholder: 'Ketik nama atau username PPPoE...',
            minimumInputLength: 2,
            ajax: {
                url: '{{ route('cpe.search-ppp-users') }}',
                dataType: 'json',
                delay: 300,
                data: function (params) { return { q: params.term }; },
                processResults: function (data) { return { results: data.results || [] }; },
            },
        });
    }

    $('#btn-do-link').on('click', function () {
        var genieId    = $('#link-genieacs-id').val();
        var pppUserId  = $('#link-ppp-user-select').val();
        if (!pppUserId) { toastr.warning('Pilih PPP user terlebih dahulu.'); return; }

        var $btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Menyimpan...');
        $.ajax({
            url: '{{ route('cpe.link') }}',
            method: 'POST',
            data: { _token: csrfToken, genieacs_id: genieId, ppp_user_id: pppUserId },
            success: function (res) {
                toastr.success(res.message);
                $('#modalLinkDevice').modal('hide');
                table.ajax.reload();
                unlinkedLoaded = false;
                loadUnlinked();
                unlinkedLoaded = true;
            },
            error: function (xhr) {
                toastr.error(xhr.responseJSON?.message || 'Gagal menghubungkan.');
            },
            complete: function () {
                $btn.prop('disabled', false).html('<i class="fas fa-link mr-1"></i> Hubungkan');
            },
        });
    });
    @endif
});
</script>
@endpush
