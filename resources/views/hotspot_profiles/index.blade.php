@extends('layouts.admin')

@section('title', 'Profil Hotspot')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Profil Hotspot</h4>
            <div class="btn-group">
                <a href="{{ route('hotspot-profiles.create') }}" class="btn btn-primary btn-sm">Tambah Profil</a>
                <button type="button" class="btn btn-danger btn-sm" id="bulk-delete-btn">Hapus</button>
            </div>
        </div>
        <div class="card-body p-0">
            <table id="hotspot-profiles-table" class="table table-striped table-hover mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:40px;"><input type="checkbox" id="select-all"></th>
                        <th>Nama</th>
                        <th>Owner</th>
                        <th>Harga Jual</th>
                        <th>Harga Promo</th>
                        <th>PPN</th>
                        <th>Bandwidth</th>
                        <th>Tipe Profil</th>
                        <th>Profil Group</th>
                        <th>Shared Users</th>
                        <th>Prioritas</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <script>
    (function () {
        var table = null;

        function initDataTable() {
            if (!document.getElementById('hotspot-profiles-table')) return;
            if (table) { table.destroy(); table = null; }

            table = $('#hotspot-profiles-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: '{{ route('hotspot-profiles.datatable') }}',
                columns: [
                    {
                        data: 'id',
                        orderable: false,
                        searchable: false,
                        render: function (id) {
                            return '<input type="checkbox" class="row-check" value="' + id + '">';
                        }
                    },
                    { data: 'name' },
                    { data: 'owner_name', searchable: false },
                    {
                        data: 'harga_jual',
                        render: function (v) { return parseFloat(v).toLocaleString('id-ID', {minimumFractionDigits:2}); }
                    },
                    {
                        data: 'harga_promo',
                        render: function (v) { return parseFloat(v).toLocaleString('id-ID', {minimumFractionDigits:2}); }
                    },
                    {
                        data: 'ppn',
                        render: function (v) { return parseFloat(v).toLocaleString('id-ID', {minimumFractionDigits:2}) + '%'; }
                    },
                    { data: 'bandwidth_name', searchable: false },
                    { data: 'tipe_profil', orderable: false, searchable: false },
                    { data: 'profile_group_name', searchable: false },
                    { data: 'shared_users' },
                    { data: 'prioritas_label', searchable: false },
                    { data: 'aksi', orderable: false, searchable: false, className: 'text-right' },
                ],
                language: {
                    processing: 'Memuat data...',
                    search: 'Cari:',
                    lengthMenu: 'Tampilkan _MENU_ data',
                    info: 'Menampilkan _START_ - _END_ dari _TOTAL_ profil',
                    infoEmpty: 'Tidak ada data',
                    infoFiltered: '(disaring dari _MAX_ total)',
                    zeroRecords: 'Tidak ada profil hotspot.',
                    paginate: { first: '«', previous: '‹', next: '›', last: '»' },
                },
                order: [[1, 'asc']],
                pageLength: 25,
                drawCallback: function () {
                    bindDeleteButtons();
                },
            });
        }

        function bindDeleteButtons() {
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
                            'X-HTTP-Method-Override': 'DELETE',
                        },
                        body: new URLSearchParams({ _method: 'DELETE' }),
                    }).then(function (r) { return r.json(); }).then(function (data) {
                        AppAjax.showToast(data.status || 'Dihapus.', 'success');
                        if (table) table.ajax.reload(null, false);
                    }).catch(function () {
                        AppAjax.showToast('Gagal menghapus.', 'danger');
                    });
                });
            });
        }

        // select-all
        document.addEventListener('change', function (e) {
            if (e.target && e.target.id === 'select-all') {
                document.querySelectorAll('.row-check').forEach(function (cb) { cb.checked = e.target.checked; });
            }
        });

        // bulk delete
        document.addEventListener('click', function (e) {
            if (e.target && e.target.id === 'bulk-delete-btn') {
                var checked = Array.from(document.querySelectorAll('.row-check:checked')).map(function (cb) { return cb.value; });
                if (!checked.length) { alert('Pilih minimal satu profil untuk dihapus.'); return; }
                if (!confirm('Hapus profil terpilih?')) return;
                var params = new URLSearchParams({ _method: 'DELETE' });
                checked.forEach(function (id) { params.append('ids[]', id); });
                fetch('{{ route('hotspot-profiles.bulk-destroy') }}', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: params,
                }).then(function (r) { return r.json(); }).then(function (data) {
                    AppAjax.showToast(data.status || 'Profil dihapus.', 'success');
                    document.getElementById('select-all').checked = false;
                    if (table) table.ajax.reload(null, false);
                }).catch(function () {
                    AppAjax.showToast('Gagal menghapus.', 'danger');
                });
            }
        });

        document.addEventListener('DOMContentLoaded', initDataTable);
        if (document.readyState !== 'loading') initDataTable();
    })();
    </script>
@endsection
