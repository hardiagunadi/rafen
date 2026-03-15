@extends('layouts.admin')

@section('title', 'Laporkan Gangguan Jaringan')

@section('content')
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-exclamation-triangle text-danger"></i> Laporkan Gangguan</h5>
                <a href="{{ route('outages.index') }}" class="btn btn-sm btn-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
            <form id="createOutageForm">
                @csrf
                <div class="card-body">
                    <div class="form-group">
                        <label>Judul Gangguan <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required
                               placeholder="Contoh: Putus Fiber Backbone Jalur A – Desa Binangun">
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Severity <span class="text-danger">*</span></label>
                                <select name="severity" class="form-control" required>
                                    <option value="medium" selected>Medium – Berdampak pada sebagian pelanggan</option>
                                    <option value="high">High – Berdampak luas</option>
                                    <option value="critical">Critical – Backbone / down total</option>
                                    <option value="low">Low – Dampak kecil</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Teknisi Penanggungjawab</label>
                                <select name="assigned_teknisi_id" class="form-control">
                                    <option value="">– Pilih Teknisi –</option>
                                    @foreach($teknisiList as $t)
                                    <option value="{{ $t->id }}">{{ $t->nickname ?? $t->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Waktu Mulai Gangguan <span class="text-danger">*</span></label>
                                <input type="datetime-local" name="started_at" class="form-control" required
                                       value="{{ now()->format('Y-m-d\TH:i') }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Estimasi Selesai</label>
                                <input type="datetime-local" name="estimated_resolved_at" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Deskripsi / Catatan Internal</label>
                        <textarea name="description" class="form-control" rows="2"
                                  placeholder="Deskripsi singkat penyebab gangguan (opsional)"></textarea>
                    </div>
                    <hr>
                    <h6><i class="fas fa-map-marker-alt text-danger"></i> Area Terdampak</h6>
                    <p class="text-muted small mb-2">
                        Isi minimal salah satu: ketik nama desa/wilayah <strong>atau</strong> pilih ODP spesifik.
                        Sistem akan mencocokkan dengan alamat pelanggan dan ODP yang terpilih.
                    </p>
                    <div class="form-group mb-2">
                        <label>Kata Kunci Wilayah <span class="badge badge-info badge-sm">Cara Cepat</span></label>
                        <input type="text" id="customAreasInput" class="form-control"
                               placeholder="Contoh: Desa Semayu, Kel. Wonoroto (Enter atau koma untuk tambah)">
                        <small class="text-muted">Dicocokkan dengan field alamat pelanggan. Bisa lebih dari satu.</small>
                        <div id="customAreasTags" class="mt-1"></div>
                        <div id="hiddenCustomAreas"></div>
                    </div>
                    <div class="form-group">
                        <label>ODP Spesifik <span class="text-muted small font-weight-normal">(opsional — jika ingin lebih presisi)</span></label>
                        <select name="odp_ids[]" class="form-control select2-odp" multiple
                                data-placeholder="Cari ODP..." style="width:100%">
                        </select>
                        <small class="text-muted">Kosongkan jika sudah mengisi kata kunci wilayah di atas.</small>
                    </div>
                    <div id="affectedPreview" class="alert alert-info d-none">
                        <i class="fas fa-users"></i> <span id="affectedCount">0</span> pelanggan aktif terdampak
                        <span id="affectedSamples" class="text-muted small ml-2"></span>
                    </div>
                    <hr>
                    <div class="form-check">
                        <input type="checkbox" name="send_wa_blast" value="1" id="sendWaBlast" class="form-check-input" checked>
                        <label class="form-check-label" for="sendWaBlast">
                            <i class="fab fa-whatsapp text-success"></i> Kirim notifikasi WA ke pelanggan terdampak
                        </label>
                    </div>
                    <div class="form-check mt-1 ml-4">
                        <input type="checkbox" name="include_status_link" value="1" id="includeStatusLink" class="form-check-input" checked>
                        <label class="form-check-label small" for="includeStatusLink">
                            <i class="fas fa-link text-info"></i> Sertakan link <em>Pantau status perbaikan</em> dalam pesan
                        </label>
                    </div>
                    <div class="mt-2">
                        <button type="button" class="btn btn-sm btn-outline-success" id="btnTestBlast">
                            <i class="fas fa-vial"></i> Test Kirim Pesan
                        </button>
                        <small class="text-muted ml-1">Kirim pesan contoh ke 1 nomor sebelum broadcast</small>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-danger" id="submitBtn">
                        <i class="fas fa-broadcast-tower"></i> Buat Insiden & Notifikasi
                    </button>
                </div>
            </form>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h6 class="mb-0"><i class="fas fa-info-circle text-info"></i> Panduan</h6></div>
            <div class="card-body small text-muted">
                <p><strong>Cara cepat:</strong> Ketik nama desa/kampung/jalan yang tertera di alamat pelanggan. Sistem otomatis cocokkan tanpa perlu pilih ODP satu per satu.</p>
                <p><strong>ODP (opsional):</strong> Tambahkan jika ingin lebih presisi, misalnya gangguan hanya di 1-2 ODP spesifik.</p>
                <p><strong>Kombinasi:</strong> Bisa pakai keduanya. Sistem ambil pelanggan yang cocok di salah satu (OR).</p>
                <p><strong>Notifikasi WA:</strong> Pesan otomatis berisi link halaman status perbaikan yang bisa dipantau pelanggan secara real-time.</p>
            </div>
        </div>
    </div>
</div>
@endsection

{{-- Modal Test Blast --}}
<div class="modal fade" id="modalTestBlast" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-vial text-success"></i> Test Kirim Pesan WA</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">
                    Kirim pesan <strong>contoh</strong> ke nomor berikut untuk memastikan WA Gateway berjalan normal sebelum broadcast ke seluruh pelanggan.
                </p>
                <div class="form-group mb-2">
                    <label>Nomor Tujuan Test</label>
                    <input type="text" id="testBlastPhone" class="form-control"
                           value="{{ $testBlastPhone }}"
                           placeholder="628xxxxxxxxxx">
                    <small class="text-muted">Default: nomor bisnis dari Pengaturan Tenant.</small>
                </div>
                <div id="testBlastResult" class="d-none mt-3"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-success" id="btnDoTestBlast">
                    <i class="fab fa-whatsapp"></i> Kirim Test
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
// Select2 untuk ODP — destroy dulu jika sudah di-init oleh global layout
$(function() {
    const $odp = $('.select2-odp');
    if ($odp.hasClass('select2-hidden-accessible')) {
        $odp.select2('destroy');
    }
    $odp.select2({
        theme: 'bootstrap4',
        width: '100%',
        ajax: {
            url: '{{ route('odps.autocomplete') }}',
            dataType: 'json',
            delay: 250,
            data: params => ({search: params.term || ''}),
            processResults: data => ({
                results: (data.data || data).map(o => ({id: o.id, text: o.name + (o.area ? ' – '+o.area : '')}))
            }),
        },
        minimumInputLength: 0,
        placeholder: 'Cari atau pilih ODP...',
    }).on('change', updatePreview);
});

// Tag input untuk custom areas
const customAreasSet = new Set();
document.getElementById('customAreasInput').addEventListener('keydown', function(e) {
    if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault();
        const val = this.value.trim().replace(/,$/, '');
        if (val) addCustomArea(val);
        this.value = '';
    }
});
document.getElementById('customAreasInput').addEventListener('blur', function() {
    const val = this.value.trim();
    if (val) { addCustomArea(val); this.value = ''; }
});

function addCustomArea(val) {
    if (!val || customAreasSet.has(val)) return;
    customAreasSet.add(val);
    renderTags();
    updatePreview();
}
function removeCustomArea(val) {
    customAreasSet.delete(val);
    renderTags();
    updatePreview();
}
function renderTags() {
    const container = document.getElementById('customAreasTags');
    const hidden    = document.getElementById('hiddenCustomAreas');
    container.innerHTML = [...customAreasSet].map(v =>
        `<span class="badge badge-secondary mr-1 mb-1" style="font-size:.85em;padding:5px 8px">
            ${v} <a href="#" onclick="removeCustomArea('${v}');return false" class="text-white ml-1">&times;</a>
        </span>`
    ).join('');
    hidden.innerHTML = [...customAreasSet].map(v => `<input type="hidden" name="custom_areas[]" value="${v}">`).join('');
}

// Preview pelanggan terdampak
let previewTimer;
function updatePreview() {
    clearTimeout(previewTimer);
    previewTimer = setTimeout(() => {
        const odpIds = $('.select2-odp').val() || [];
        const keywords = [...customAreasSet];
        if (!odpIds.length && !keywords.length) {
            document.getElementById('affectedPreview').classList.add('d-none');
            return;
        }
        const formData = new FormData();
        formData.append('_token', '{{ csrf_token() }}');
        odpIds.forEach(id => formData.append('odp_ids[]', id));
        keywords.forEach(kw => formData.append('custom_areas[]', kw));

        fetch('{{ route('outages.affected-users-preview') }}', {method:'POST', body:formData})
            .then(r => r.json())
            .then(res => {
                document.getElementById('affectedCount').textContent = res.count;
                const samples = (res.samples||[]).map(s => s.name).join(', ');
                document.getElementById('affectedSamples').textContent = samples ? `(${samples}${res.count>5?', ...':''})` : '';
                document.getElementById('affectedPreview').classList.remove('d-none');
            });
    }, 400);
}

// Test Blast
document.getElementById('btnTestBlast').addEventListener('click', function() {
    document.getElementById('testBlastResult').className = 'd-none';
    $('#modalTestBlast').modal('show');
});

document.getElementById('btnDoTestBlast').addEventListener('click', function() {
    const phone = document.getElementById('testBlastPhone').value.trim();
    if (!phone) { alert('Isi nomor tujuan test.'); return; }

    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengirim...';

    const odpIds = $('.select2-odp').val() || [];
    const keywords = [...customAreasSet];
    const formData = new FormData();
    formData.append('_token', '{{ csrf_token() }}');
    formData.append('test_phone', phone);
    odpIds.forEach(id => formData.append('odp_ids[]', id));
    keywords.forEach(kw => formData.append('custom_areas[]', kw));
    formData.append('include_status_link', document.getElementById('includeStatusLink')?.checked ? '1' : '0');

    fetch('{{ route('outages.test-blast') }}', {
        method: 'POST',
        body: formData,
        headers: {'X-Requested-With': 'XMLHttpRequest'},
    })
    .then(r => r.json())
    .then(res => {
        const box = document.getElementById('testBlastResult');
        box.className = 'mt-3 alert ' + (res.success ? 'alert-success' : 'alert-danger');
        let info = res.message;
        if (res.success && res.recipient_count !== undefined) {
            info += `<br><small class="mt-1 d-block">Estimasi penerima broadcast: <strong>${res.recipient_count} pelanggan</strong> berdasarkan area yang dipilih.</small>`;
        }
        box.innerHTML = (res.success ? '<i class="fas fa-check-circle"></i> ' : '<i class="fas fa-times-circle"></i> ') + info;
    })
    .catch(() => {
        const box = document.getElementById('testBlastResult');
        box.className = 'mt-3 alert alert-danger';
        box.innerHTML = '<i class="fas fa-times-circle"></i> Terjadi kesalahan jaringan.';
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fab fa-whatsapp"></i> Kirim Test';
    });
});

// Submit
document.getElementById('createOutageForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';

    const fd = new FormData(this);
    fd.set('include_status_link', document.getElementById('includeStatusLink')?.checked ? '1' : '0');

    fetch('{{ route('outages.store') }}', {
        method: 'POST',
        body: fd,
        headers: {'X-Requested-With': 'XMLHttpRequest'},
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            window.location.href = res.show_url;
        } else {
            alert(res.message || 'Terjadi kesalahan.');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-broadcast-tower"></i> Buat Insiden & Notifikasi';
        }
    })
    .catch(() => {
        alert('Terjadi kesalahan jaringan.');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-broadcast-tower"></i> Buat Insiden & Notifikasi';
    });
});
</script>
@endpush
