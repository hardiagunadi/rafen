@extends('layouts.admin')

@section('title', 'Detail Tiket #' . $waTicket->id)

@section('content')
{{-- Callout: ada outage aktif di area pelanggan ini --}}
@if(isset($relatedOutage) && $relatedOutage)
<div class="callout callout-warning">
    <h6 class="mb-1"><i class="fas fa-broadcast-tower"></i> Gangguan Jaringan Aktif di Area Pelanggan Ini</h6>
    <p class="mb-1 small">
        <strong>{{ $relatedOutage->title }}</strong> —
        Sejak {{ $relatedOutage->started_at->format('d/m/Y H:i') }}
        @if($relatedOutage->estimated_resolved_at)
        · Estimasi: {{ $relatedOutage->estimated_resolved_at->format('d/m/Y H:i') }}
        @endif
    </p>
    <a href="{{ route('outages.show', $relatedOutage) }}" class="btn btn-sm btn-warning mr-1">
        <i class="fas fa-eye"></i> Detail Insiden
    </a>
    <a href="{{ route('outage.public-status', $relatedOutage->public_token) }}" target="_blank" class="btn btn-sm btn-outline-warning">
        <i class="fas fa-external-link-alt"></i> Halaman Publik
    </a>
</div>
@endif

<div class="row">
    {{-- Kiri: info + timeline + form catatan --}}
    <div class="col-md-8">
        {{-- Info tiket --}}
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-ticket-alt"></i> Tiket #{{ $waTicket->id }}</h5>
                <a href="{{ route('wa-tickets.index') }}" class="btn btn-sm btn-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
            <div class="card-body pb-2">
                <table class="table table-sm table-borderless mb-0">
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
                        <span class="badge {{ $sMap[$waTicket->status] ?? 'badge-light' }}" id="currentStatusBadge">{{ $waTicket->status }}</span>
                    </td></tr>
                    <tr><th>Pelanggan WA</th><td>
                        {{ $waTicket->conversation?->contact_name ?? $waTicket->conversation?->contact_phone ?? '-' }}
                    </td></tr>
                    @php $cust = $waTicket->customerModel(); @endphp
                    @if($cust)
                    <tr><th>Pelanggan</th><td>
                        <a href="{{ $waTicket->customer_type === 'ppp' ? route('ppp-users.show', $cust->id) : route('hotspot-users.show', $cust->id) }}" target="_blank">
                            <span class="badge badge-{{ $waTicket->customer_type === 'ppp' ? 'primary' : 'warning' }} mr-1">{{ strtoupper($waTicket->customer_type) }}</span>{{ $cust->customer_name }}
                        </a>
                    </td></tr>
                    @endif
                    <tr><th>Teknisi</th><td>
                        {{ $waTicket->assignedTo ? ($waTicket->assignedTo->nickname ?? $waTicket->assignedTo->name) : '-' }}
                    </td></tr>
                    <tr><th>Dibuat</th><td>{{ $waTicket->created_at->format('d/m/Y H:i') }}</td></tr>
                    @if($waTicket->resolved_at)
                    <tr><th>Diselesaikan</th><td>{{ $waTicket->resolved_at->format('d/m/Y H:i') }}</td></tr>
                    @endif
                </table>
                @if($waTicket->description)
                <hr class="my-2">
                <h6>Deskripsi</h6>
                <p class="text-muted mb-1">{{ $waTicket->description }}</p>
                @endif
                @if($waTicket->image_path)
                <hr class="my-2">
                <h6>Foto Awal</h6>
                <img src="{{ asset('storage/' . $waTicket->image_path) }}" alt="Gambar tiket"
                     class="ticket-lightbox-img"
                     style="max-width:100%;max-height:240px;border-radius:8px;cursor:zoom-in;border:1px solid #ddd;">
                @endif
            </div>
        </div>

        {{-- Timeline --}}
        <div class="card mt-3">
            <div class="card-header py-2">
                <h6 class="mb-0"><i class="fas fa-history mr-1"></i>Timeline Pengerjaan</h6>
            </div>
            <div class="card-body py-3" id="timelineContainer">
                @forelse($waTicket->notes as $note)
                    @include('wa-chat.partials.ticket-note', ['note' => $note])
                @empty
                <p class="text-muted small mb-0">Belum ada aktivitas.</p>
                @endforelse
            </div>
        </div>

        {{-- Form tambah catatan --}}
        <div class="card mt-3">
            <div class="card-header py-2">
                <h6 class="mb-0"><i class="fas fa-comment-medical mr-1"></i>Tambah Catatan</h6>
            </div>
            <div class="card-body">
                <form id="formAddNote" enctype="multipart/form-data">
                    @csrf
                    <div class="form-group mb-2">
                        <textarea id="noteText" name="note" class="form-control" rows="3"
                                  placeholder="Tulis catatan pengerjaan, hasil diagnosa, atau update progress..."></textarea>
                    </div>
                    <div class="form-group mb-2">
                        <div class="custom-file">
                            <input type="file" class="custom-file-input" id="noteImage" name="image" accept="image/*">
                            <label class="custom-file-label" for="noteImage">Pilih foto bukti... (opsional)</label>
                        </div>
                        <div id="noteImagePreviewWrap" class="mt-2 d-none">
                            <img id="noteImagePreview" src="" alt="Preview"
                                 style="max-width:100%;max-height:160px;border-radius:6px;border:1px solid #ddd;cursor:zoom-in;">
                            <button type="button" id="btnRemoveNoteImage" class="btn btn-xs btn-danger mt-1 d-block">
                                <i class="fas fa-times mr-1"></i>Hapus foto
                            </button>
                        </div>
                    </div>
                    <button type="submit" id="btnAddNote" class="btn btn-primary btn-sm">
                        <i class="fas fa-save mr-1"></i>Simpan Catatan
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Kanan: aksi --}}
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Aksi</h6></div>
            <div class="card-body">
                <div class="form-group mb-2">
                    <label class="mb-1">Ubah Status</label>
                    <select id="newStatus" class="form-control form-control-sm">
                        <option value="open" @selected($waTicket->status === 'open')>Open</option>
                        <option value="in_progress" @selected($waTicket->status === 'in_progress')>In Progress</option>
                        <option value="resolved" @selected($waTicket->status === 'resolved')>Resolved</option>
                        <option value="closed" @selected($waTicket->status === 'closed')>Closed</option>
                    </select>
                    <small class="text-muted">Otomatis tersimpan saat diubah</small>
                </div>
                @if($user->role !== 'teknisi')
                <hr>
                <div class="form-group mb-1">
                    <label class="mb-1">Assign Teknisi</label>
                    <select id="assignTechnician" class="form-control form-control-sm">
                        <option value="">— Pilih Teknisi —</option>
                        @foreach(App\Models\User::where(function($q) { $q->where('id', auth()->user()->effectiveOwnerId())->orWhere('parent_id', auth()->user()->effectiveOwnerId()); })->where('role', 'teknisi')->get() as $tech)
                        <option value="{{ $tech->id }}" @selected($waTicket->assigned_to_id == $tech->id)>{{ $tech->nickname ?? $tech->name }}</option>
                        @endforeach
                    </select>
                    <small class="text-muted">Otomatis tersimpan saat dipilih</small>
                </div>
                @endif
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

{{-- Lightbox --}}
<div class="modal fade" id="modalImgLightbox" tabindex="-1" style="z-index:1060;">
    <div class="modal-dialog modal-dialog-centered" style="max-width:90vw;">
        <div class="modal-content" style="background:transparent;border:none;box-shadow:none;">
            <div class="modal-body text-center p-0 position-relative">
                <button type="button" class="close position-absolute" data-dismiss="modal"
                    style="top:-12px;right:-12px;z-index:10;background:#fff;border-radius:50%;width:32px;height:32px;opacity:1;line-height:32px;padding:0;font-size:1.2rem;box-shadow:0 2px 8px rgba(0,0,0,.4);">&times;</button>
                <img id="lbImg" src="" alt="" style="max-width:88vw;max-height:85vh;border-radius:8px;box-shadow:0 8px 40px rgba(0,0,0,.5);">
                <div class="mt-2">
                    <a id="lbDownload" href="" target="_blank" class="btn btn-sm btn-light">
                        <i class="fas fa-download mr-1"></i>Buka / Unduh
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<style>
#modalImgLightbox { background: rgba(0,0,0,.85); }

/* Timeline */
.timeline-item { display:flex; gap:12px; margin-bottom:16px; }
.timeline-item:last-child { margin-bottom:0; }
.tl-icon { flex-shrink:0; width:30px; height:30px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:.8rem; }
.tl-icon.created    { background:#d4edda; color:#155724; }
.tl-icon.assigned   { background:#cce5ff; color:#004085; }
.tl-icon.status_change { background:#fff3cd; color:#856404; }
.tl-icon.note       { background:#f0f0f0; color:#555; }
.tl-body { flex:1; font-size:.875rem; }
.tl-body .tl-meta   { font-size:.72rem; color:#888; }
.tl-body .tl-note   { margin-top:4px; white-space:pre-wrap; }
.tl-body .tl-img    { margin-top:6px; border-radius:6px; max-width:240px; max-height:160px; cursor:zoom-in; border:1px solid #ddd; display:block; }
</style>

<script>
// Lightbox
$(document).on('click', '.ticket-lightbox-img, .tl-img', function() {
    const src = $(this).attr('src');
    $('#lbImg').attr('src', src);
    $('#lbDownload').attr('href', src);
    $('#modalImgLightbox').modal('show');
});
$('#modalImgLightbox').on('click', function(e) {
    if ($(e.target).is('#modalImgLightbox') || $(e.target).is('.modal-body')) $(this).modal('hide');
});

// Preview foto catatan
$('#noteImage').on('change', function() {
    const file = this.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function(e) {
        $('#noteImagePreview').attr('src', e.target.result);
        $('#noteImagePreviewWrap').removeClass('d-none');
    };
    reader.readAsDataURL(file);
    $(this).next('.custom-file-label').text(file.name);
});
$('#btnRemoveNoteImage').on('click', function() {
    $('#noteImage').val('');
    $('#noteImagePreviewWrap').addClass('d-none');
    $('#noteImagePreview').attr('src', '');
    $('#noteImage').next('.custom-file-label').text('Pilih foto bukti... (opsional)');
});

// Preview foto catatan → lightbox
$(document).on('click', '#noteImagePreview', function() {
    const src = $(this).attr('src');
    $('#lbImg').attr('src', src);
    $('#lbDownload').attr('href', src);
    $('#modalImgLightbox').modal('show');
});

// Auto-save status on change
$('#newStatus').on('change', function() {
    const status = $(this).val();
    $.ajax({
        url: '{{ route("wa-tickets.update", $waTicket) }}',
        method: 'PUT',
        data: { status, _token: '{{ csrf_token() }}' },
        success: function(res) {
            if (res.success) {
                toastr.success('Status diperbarui.');
                // Reload timeline untuk tampilkan entry status_change
                reloadTimeline();
            }
        },
        error: function() { toastr.error('Gagal memperbarui status.'); }
    });
});

// Auto-save assign on change
$('#assignTechnician').on('change', function() {
    const id = $(this).val();
    if (!id) return;
    $.post('{{ route("wa-tickets.assign", $waTicket) }}', {
        assigned_to_id: id, _token: '{{ csrf_token() }}'
    }, function(res) {
        if (res.success) {
            toastr.success('Teknisi berhasil di-assign.');
            reloadTimeline();
        }
    }).fail(function(xhr) { toastr.error(xhr.responseJSON?.message || 'Gagal assign teknisi.'); });
});

// Submit form catatan
$('#formAddNote').on('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    $('#btnAddNote').prop('disabled', true);
    $.ajax({
        url: '{{ route("wa-tickets.notes.store", $waTicket) }}',
        method: 'POST',
        data: fd,
        processData: false,
        contentType: false,
        success: function(res) {
            if (res.success) {
                // Append note baru ke timeline
                $('#timelineContainer').append(renderTimelineNote(res.note));
                // Reset form
                $('#noteText').val('');
                $('#noteImage').val('');
                $('#noteImagePreviewWrap').addClass('d-none');
                $('#noteImagePreview').attr('src', '');
                $('#noteImage').next('.custom-file-label').text('Pilih foto bukti... (opsional)');
                toastr.success('Catatan berhasil disimpan.');
            }
        },
        error: function(xhr) { toastr.error(xhr.responseJSON?.message || 'Gagal menyimpan catatan.'); },
        complete: function() { $('#btnAddNote').prop('disabled', false); },
    });
});

function esc(str) { return $('<span>').text(str || '').html(); }

function renderTimelineNote(n) {
    const iconMap = {
        created:       ['fa-plus-circle', 'created'],
        assigned:      ['fa-user-tag',    'assigned'],
        status_change: ['fa-exchange-alt','status_change'],
        note:          ['fa-comment',     'note'],
    };
    const [icon, cls] = iconMap[n.type] || ['fa-circle', 'note'];
    const imgHtml = n.image_url
        ? `<img src="${esc(n.image_url)}" class="tl-img" alt="foto">`
        : '';
    const noteHtml = n.note ? `<div class="tl-note">${esc(n.note)}</div>` : '';
    const metaHtml = n.meta ? `<span>${esc(n.meta)}</span> · ` : '';
    return `<div class="timeline-item">
        <div class="tl-icon ${cls}"><i class="fas ${icon}"></i></div>
        <div class="tl-body">
            <div><strong>${esc(n.user_name)}</strong></div>
            ${noteHtml}${imgHtml}
            <div class="tl-meta">${metaHtml}${esc(n.created_at)}</div>
        </div>
    </div>`;
}

function reloadTimeline() {
    // Reload halaman ringan hanya untuk refresh timeline setelah status/assign change
    setTimeout(() => location.reload(), 800);
}
</script>
@endpush
