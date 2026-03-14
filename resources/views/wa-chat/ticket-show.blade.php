@extends('layouts.admin')

@section('title', 'Detail Tiket #' . $waTicket->id)

@section('content')
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-ticket-alt"></i> Tiket #{{ $waTicket->id }}</h5>
                <a href="{{ route('wa-tickets.index') }}" class="btn btn-sm btn-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless">
                    <tr><th style="width:150px">Judul</th><td>{{ $waTicket->title }}</td></tr>
                    <tr><th>Tipe</th><td>
                        @php $typeMap = ['complaint'=>'Komplain','troubleshoot'=>'Troubleshoot','installation'=>'Instalasi','other'=>'Lainnya']; @endphp
                        {{ $typeMap[$waTicket->type] ?? $waTicket->type }}
                    </td></tr>
                    <tr><th>Prioritas</th><td>
                        @php $pMap = ['low'=>'badge-light','normal'=>'badge-info','high'=>'badge-danger']; @endphp
                        <span class="badge {{ $pMap[$waTicket->priority] ?? 'badge-light' }}">{{ $waTicket->priority }}</span>
                    </td></tr>
                    <tr><th>Status</th><td>
                        @php $sMap = ['open'=>'badge-success','in_progress'=>'badge-warning','resolved'=>'badge-secondary','closed'=>'badge-dark']; @endphp
                        <span class="badge {{ $sMap[$waTicket->status] ?? 'badge-light' }}" id="currentStatus">{{ $waTicket->status }}</span>
                    </td></tr>
                    <tr><th>Pelanggan</th><td>
                        {{ $waTicket->conversation?->contact_name ?? $waTicket->conversation?->contact_phone ?? '-' }}
                    </td></tr>
                    <tr><th>Teknisi</th><td>
                        {{ $waTicket->assignedTo ? ($waTicket->assignedTo->nickname ?? $waTicket->assignedTo->name) : '-' }}
                    </td></tr>
                    <tr><th>Dibuat</th><td>{{ $waTicket->created_at->format('d/m/Y H:i') }}</td></tr>
                    @if($waTicket->resolved_at)
                    <tr><th>Diselesaikan</th><td>{{ $waTicket->resolved_at->format('d/m/Y H:i') }}</td></tr>
                    @endif
                </table>
                @if($waTicket->description)
                <hr>
                <h6>Deskripsi</h6>
                <p class="text-muted">{{ $waTicket->description }}</p>
                @endif
            </div>
        </div>
    </div>

    <div class="col-md-4">
        @can('update-ticket', $waTicket)
        @endcan
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Aksi</h6></div>
            <div class="card-body">
                <div class="form-group">
                    <label>Ubah Status</label>
                    <select id="newStatus" class="form-control form-control-sm">
                        <option value="open" @selected($waTicket->status === 'open')>Open</option>
                        <option value="in_progress" @selected($waTicket->status === 'in_progress')>In Progress</option>
                        <option value="resolved" @selected($waTicket->status === 'resolved')>Resolved</option>
                        <option value="closed" @selected($waTicket->status === 'closed')>Closed</option>
                    </select>
                </div>
                <button id="btnUpdateStatus" class="btn btn-sm btn-primary btn-block">Simpan Status</button>
                <hr>
                <div class="form-group">
                    <label>Assign Teknisi</label>
                    <select id="assignTechnician" class="form-control form-control-sm">
                        <option value="">— Pilih Teknisi —</option>
                        @foreach(App\Models\User::where(function($q) { $q->where('id', auth()->user()->effectiveOwnerId())->orWhere('parent_id', auth()->user()->effectiveOwnerId()); })->where('role', 'teknisi')->get() as $tech)
                        <option value="{{ $tech->id }}" @selected($waTicket->assigned_to_id == $tech->id)>{{ $tech->nickname ?? $tech->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button id="btnAssignTech" class="btn btn-sm btn-info btn-block">Assign Teknisi</button>
            </div>
        </div>

        @if($waTicket->conversation)
        <div class="card mt-3">
            <div class="card-header"><h6 class="mb-0">Percakapan Terkait</h6></div>
            <div class="card-body p-2">
                <a href="{{ route('wa-chat.index') }}" class="btn btn-sm btn-outline-success btn-block">
                    <i class="fab fa-whatsapp"></i> Buka Chat
                </a>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
$('#btnUpdateStatus').on('click', function() {
    const status = $('#newStatus').val();
    $.ajax({
        url: '{{ route("wa-tickets.update", $waTicket) }}',
        method: 'PUT',
        data: { status, _token: '{{ csrf_token() }}' },
        success: function(res) {
            if (res.success) {
                toastr.success('Status diperbarui.');
                setTimeout(() => location.reload(), 1000);
            }
        }
    });
});
$('#btnAssignTech').on('click', function() {
    const id = $('#assignTechnician').val();
    if (!id) return alert('Pilih teknisi terlebih dahulu.');
    $.post('{{ route("wa-tickets.assign", $waTicket) }}', {
        assigned_to_id: id, _token: '{{ csrf_token() }}'
    }, function(res) {
        if (res.success) { toastr.success('Teknisi berhasil di-assign.'); setTimeout(() => location.reload(), 1000); }
    }).fail(function(xhr) { toastr.error(xhr.responseJSON?.message || 'Gagal assign teknisi.'); });
});
</script>
@endpush
