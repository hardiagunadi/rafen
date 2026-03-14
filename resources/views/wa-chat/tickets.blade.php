@extends('layouts.admin')

@section('title', 'Tiket Pengaduan')

@section('content')
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h3 class="card-title"><i class="fas fa-ticket-alt"></i> Tiket Pengaduan</h3>
        <div class="d-flex gap-2">
            <select id="filterStatus" class="form-control form-control-sm" style="width:auto;">
                <option value="">Semua Status</option>
                <option value="open">Open</option>
                <option value="in_progress">In Progress</option>
                <option value="resolved">Resolved</option>
                <option value="closed">Closed</option>
            </select>
            <select id="filterType" class="form-control form-control-sm ml-1" style="width:auto;">
                <option value="">Semua Tipe</option>
                <option value="complaint">Komplain</option>
                <option value="troubleshoot">Troubleshoot</option>
                <option value="installation">Instalasi</option>
                <option value="other">Lainnya</option>
            </select>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0" id="ticketTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Judul</th>
                        <th>Tipe</th>
                        <th>Prioritas</th>
                        <th>Status</th>
                        <th>Pelanggan</th>
                        <th>Teknisi</th>
                        <th>Tgl Buat</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="ticketBody">
                    <tr><td colspan="9" class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin"></i> Memuat...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <small id="ticketTotal" class="text-muted"></small>
        <div id="ticketPagination"></div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function() {
    let currentPage = 1;

    function statusBadge(s) {
        const map = {open:'badge-success', in_progress:'badge-warning', resolved:'badge-secondary', closed:'badge-dark'};
        return `<span class="badge ${map[s] || 'badge-light'}">${s.replace('_',' ')}</span>`;
    }
    function priorityBadge(p) {
        const map = {low:'badge-light', normal:'badge-info', high:'badge-danger'};
        return `<span class="badge ${map[p] || 'badge-light'}">${p}</span>`;
    }
    function typeBadge(t) {
        const map = {complaint:'Komplain', troubleshoot:'Troubleshoot', installation:'Instalasi', other:'Lainnya'};
        return map[t] || t;
    }

    function loadTickets(page) {
        page = page || 1;
        currentPage = page;
        $.get('{{ route("wa-tickets.datatable") }}', {
            status: $('#filterStatus').val(),
            type: $('#filterType').val(),
            page: page,
        }, function(res) {
            const $body = $('#ticketBody');
            $body.empty();
            if (!res.data || !res.data.length) {
                $body.html('<tr><td colspan="9" class="text-center text-muted py-3">Tidak ada tiket</td></tr>');
                return;
            }
            res.data.forEach(function(t) {
                $body.append(`<tr>
                    <td><small>#${t.id}</small></td>
                    <td>${$('<span>').text(t.title).html()}</td>
                    <td><small>${typeBadge(t.type)}</small></td>
                    <td>${priorityBadge(t.priority)}</td>
                    <td>${statusBadge(t.status)}</td>
                    <td><small>${$('<span>').text(t.contact).html()}</small></td>
                    <td><small>${$('<span>').text(t.assigned_to).html()}</small></td>
                    <td><small>${t.created_at}</small></td>
                    <td>
                        <a href="${t.actions_url}" class="btn btn-xs btn-primary"><i class="fas fa-eye"></i></a>
                    </td>
                </tr>`);
            });
            $('#ticketTotal').text(`Total: ${res.total} tiket`);

            // Pagination
            let pages = '';
            for (let i = 1; i <= res.last_page; i++) {
                pages += `<button class="btn btn-xs ${i == res.current_page ? 'btn-primary' : 'btn-outline-secondary'} mr-1 page-btn" data-page="${i}">${i}</button>`;
            }
            $('#ticketPagination').html(pages);
        });
    }

    $(document).on('click', '.page-btn', function() { loadTickets($(this).data('page')); });
    $('#filterStatus, #filterType').on('change', function() { loadTickets(1); });

    loadTickets();
})();
</script>
@endpush

