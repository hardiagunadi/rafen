@extends('layouts.admin')

@section('title', 'WA Blast')

@section('content')
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fab fa-whatsapp text-success"></i> Kirim WA Blast</h3>
            </div>
            <div class="card-body">
                @if(!$settings->hasWaConfigured())
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        WA Gateway belum dikonfigurasi. Silakan atur di
                        <a href="{{ route('tenant-settings.index') }}#whatsapp">Pengaturan → WhatsApp</a>.
                    </div>
                @endif

                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>Tipe Pelanggan</label>
                        <select id="blast_tipe" class="form-control">
                            <option value="ppp">PPPoE</option>
                            <option value="hotspot">Hotspot</option>
                            <option value="all">Semua</option>
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                        <label>Status Akun</label>
                        <select id="blast_status_akun" class="form-control">
                            <option value="">Semua Status</option>
                            <option value="enable">Enable</option>
                            <option value="disable">Disable</option>
                            <option value="isolir">Isolir</option>
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                        <label>Status Bayar</label>
                        <select id="blast_status_bayar" class="form-control">
                            <option value="">Semua</option>
                            <option value="sudah_bayar">Sudah Bayar</option>
                            <option value="belum_bayar">Belum Bayar</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6" id="ppp-profile-group">
                        <label>Filter Paket PPP (Opsional)</label>
                        <select id="blast_ppp_profile_id" class="form-control">
                            <option value="">Semua Paket</option>
                            @foreach($pppProfiles as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-md-6" id="hotspot-profile-group" style="display:none;">
                        <label>Filter Paket Hotspot (Opsional)</label>
                        <select id="blast_hotspot_profile_id" class="form-control">
                            <option value="">Semua Paket</option>
                            @foreach($hotspotProfiles as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Pesan</label>
                    <textarea id="blast_message" class="form-control" rows="5" placeholder="Ketik pesan Anda di sini..."></textarea>
                    <small class="text-muted"><span id="char-count">0</span> karakter</small>
                </div>

                <div class="d-flex align-items-center">
                    <button type="button" class="btn btn-info mr-2" onclick="previewBlast()">
                        <i class="fas fa-eye"></i> Preview Penerima
                    </button>
                    <button type="button" class="btn btn-success" id="btn-send-blast" onclick="sendBlast()">
                        <i class="fab fa-whatsapp"></i> Kirim Blast
                    </button>
                </div>

                <div id="preview-result" class="mt-3" style="display:none;">
                    <div class="alert alert-info">
                        <strong><span id="preview-count">0</span> penerima</strong> akan menerima pesan ini.
                    </div>
                </div>

                <div id="send-result" class="mt-3" style="display:none;"></div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Panduan Penggunaan</h3>
            </div>
            <div class="card-body">
                <ol class="pl-3">
                    <li class="mb-2">Pilih tipe pelanggan (PPPoE / Hotspot / Semua)</li>
                    <li class="mb-2">Filter berdasarkan status akun atau bayar jika diperlukan</li>
                    <li class="mb-2">Pilih paket jika ingin targetkan paket tertentu</li>
                    <li class="mb-2">Ketik pesan yang ingin dikirim</li>
                    <li class="mb-2">Klik <strong>Preview</strong> untuk melihat jumlah penerima</li>
                    <li class="mb-2">Klik <strong>Kirim Blast</strong> untuk mengirim</li>
                </ol>
                <hr>
                <small class="text-muted">
                    <i class="fas fa-info-circle"></i>
                    Hanya pelanggan dengan nomor HP yang terdaftar yang akan menerima pesan.
                </small>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
var sendInProgress = false;

document.getElementById('blast_tipe').addEventListener('change', function() {
    var tipe = this.value;
    document.getElementById('ppp-profile-group').style.display = (tipe === 'ppp' || tipe === 'all') ? '' : 'none';
    document.getElementById('hotspot-profile-group').style.display = (tipe === 'hotspot' || tipe === 'all') ? '' : 'none';
    document.getElementById('preview-result').style.display = 'none';
});

document.getElementById('blast_message').addEventListener('input', function() {
    document.getElementById('char-count').textContent = this.value.length;
});

function getBlastParams() {
    var tipe = document.getElementById('blast_tipe').value;
    var profileId = tipe === 'ppp' ? document.getElementById('blast_ppp_profile_id').value
                  : tipe === 'hotspot' ? document.getElementById('blast_hotspot_profile_id').value
                  : '';
    return {
        tipe: tipe,
        status_akun: document.getElementById('blast_status_akun').value,
        status_bayar: document.getElementById('blast_status_bayar').value,
        profile_id: profileId,
    };
}

function previewBlast() {
    var params = getBlastParams();
    var url = '{{ route("wa-blast.preview") }}?' + new URLSearchParams(params).toString();

    fetch(url, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        document.getElementById('preview-count').textContent = data.count;
        document.getElementById('preview-result').style.display = '';
    })
    .catch(() => alert('Gagal memuat preview.'));
}

function sendBlast() {
    if (sendInProgress) return;
    var message = document.getElementById('blast_message').value.trim();
    if (!message) { alert('Pesan tidak boleh kosong.'); return; }

    var params = getBlastParams();
    if (!confirm('Kirim pesan ke semua penerima yang sesuai filter?')) return;

    sendInProgress = true;
    var btn = document.getElementById('btn-send-blast');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengirim...';

    var resultDiv = document.getElementById('send-result');
    resultDiv.style.display = '';
    resultDiv.innerHTML = '<div class="alert alert-info"><i class="fas fa-spinner fa-spin"></i> Sedang mengirim pesan...</div>';

    var body = Object.assign(params, { message: message });

    fetch('{{ route("wa-blast.send") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
        },
        body: JSON.stringify(body),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            resultDiv.innerHTML = '<div class="alert alert-success"><i class="fas fa-check"></i> ' + data.message + '</div>';
        } else {
            resultDiv.innerHTML = '<div class="alert alert-danger"><i class="fas fa-times"></i> ' + data.message + '</div>';
        }
    })
    .catch(() => {
        resultDiv.innerHTML = '<div class="alert alert-danger"><i class="fas fa-times"></i> Terjadi kesalahan, coba lagi.</div>';
    })
    .finally(() => {
        sendInProgress = false;
        btn.disabled = false;
        btn.innerHTML = '<i class="fab fa-whatsapp"></i> Kirim Blast';
    });
}
</script>
@endpush
@endsection
