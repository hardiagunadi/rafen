@extends('layouts.admin')

@section('title', 'WA Gateway')

@section('content')

@if(auth()->user()->isSuperAdmin())
<div class="row mb-3">
    <div class="col-md-8">
        <div class="card card-outline card-primary mb-0">
            <div class="card-body py-2">
                <form method="GET" action="{{ route('wa-gateway.index') }}" class="form-inline">
                    <label class="mr-2 mb-0 font-weight-bold"><i class="fas fa-crown text-warning mr-1"></i> Pilih Tenant:</label>
                    <select name="tenant_id" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
                        <option value="">-- Pilih Tenant --</option>
                        @foreach($tenants as $t)
                            <option value="{{ $t->id }}" {{ $selectedTenant?->id == $t->id ? 'selected' : '' }}>
                                {{ $t->name }} ({{ $t->email }})
                            </option>
                        @endforeach
                    </select>
                </form>
            </div>
        </div>
    </div>
</div>
@endif

@if(auth()->user()->isSuperAdmin() && !$selectedTenant)
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-body text-center text-muted py-5">
                <i class="fab fa-whatsapp fa-3x mb-3 text-success"></i>
                <p>Pilih tenant di atas untuk mengatur WA Gateway mereka.</p>
            </div>
        </div>
    </div>
</div>
@else

<div class="row">
    <div class="col-md-8">

        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fab fa-whatsapp text-success mr-1"></i> Integrasi WhatsApp Gateway
                @if(auth()->user()->isSuperAdmin() && $selectedTenant)
                    <span class="badge badge-primary ml-2">{{ $selectedTenant->name }}</span>
                @endif
                </h3>
            </div>
            <form action="{{ route('tenant-settings.update-wa') }}" method="POST">
                @csrf
                @method('PUT')
                @if(auth()->user()->isSuperAdmin() && $selectedTenant)
                <input type="hidden" name="tenant_id" value="{{ $selectedTenant->id }}">
                @endif
                <div class="card-body">

                    {{-- Koneksi --}}
                    <h6 class="text-muted text-uppercase mb-3" style="font-size:0.75rem;letter-spacing:.08em">Koneksi Gateway</h6>

                    <div class="form-group">
                        <label>URL Gateway</label>
                        <input type="url" name="wa_gateway_url" class="form-control"
                            value="{{ old('wa_gateway_url', $settings->wa_gateway_url) }}"
                            placeholder="https://gateway.watumalang.online">
                        <small class="text-muted">Alamat server WA Gateway Anda.</small>
                    </div>
                    <div class="form-group">
                        <label>Token <span class="text-muted small">(header: Authorization)</span></label>
                        <input type="password" name="wa_gateway_token" class="form-control"
                            value="{{ old('wa_gateway_token', $settings->wa_gateway_token) }}"
                            placeholder="Masukkan token perangkat" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label>Key <span class="text-muted small">(header: key — master key, opsional)</span></label>
                        <input type="password" name="wa_gateway_key" class="form-control"
                            value="{{ old('wa_gateway_key', $settings->wa_gateway_key) }}"
                            placeholder="Masukkan master key (jika ada)" autocomplete="off">
                        <small class="text-muted">Token perangkat wajib diisi. Key hanya opsional jika gateway Anda mengaktifkan master key.</small>
                    </div>

                    <button type="button" class="btn btn-info btn-sm mb-2" id="btn-test-wa" onclick="testWaGateway()">
                        <i class="fas fa-plug"></i> Test Koneksi
                    </button>
                    <div id="wa-test-result" class="mb-2"></div>
                    <div id="wa-test-detail" class="mt-1" style="display:none;">
                        <small class="text-muted d-block mb-1">Detail respons gateway:</small>
                        <pre id="wa-test-detail-body" class="bg-light border rounded p-2" style="font-size:11px;max-height:200px;overflow:auto;white-space:pre-wrap;"></pre>
                    </div>

                    <hr>

                    {{-- Webhook --}}
                    <h6 class="text-muted text-uppercase mb-3" style="font-size:0.75rem;letter-spacing:.08em">Webhook</h6>
                    <div class="alert alert-light border">
                        <p class="mb-2 small"><strong>Konfigurasi di WA Gateway:</strong> set <code>webhookBaseUrl</code> ke nilai berikut:</p>
                        <div class="input-group input-group-sm mb-1">
                            <div class="input-group-prepend"><span class="input-group-text">Base URL</span></div>
                            <input type="text" class="form-control form-control-sm font-monospace" id="webhook_url_base" value="{{ url('/webhook/wa') }}" readonly>
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="copyText('webhook_url_base')"><i class="fas fa-copy"></i></button>
                            </div>
                        </div>
                        <p class="mb-2 small">Endpoint event yang dipanggil gateway:</p>
                        <div class="input-group input-group-sm mb-1">
                            <div class="input-group-prepend"><span class="input-group-text">Session</span></div>
                            <input type="text" class="form-control form-control-sm font-monospace" id="webhook_url_session" value="{{ url('/webhook/wa/session') }}" readonly>
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="copyText('webhook_url_session')"><i class="fas fa-copy"></i></button>
                            </div>
                        </div>
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend"><span class="input-group-text">Message</span></div>
                            <input type="text" class="form-control form-control-sm font-monospace" id="webhook_url_message" value="{{ url('/webhook/wa/message') }}" readonly>
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="copyText('webhook_url_message')"><i class="fas fa-copy"></i></button>
                            </div>
                        </div>
                        <p class="text-muted small mt-2 mb-0">Kompatibel juga dengan endpoint standar docs: <code>{{ url('/webhook/session') }}</code> dan <code>{{ url('/webhook/message') }}</code>.</p>
                        <p class="text-muted small mt-1 mb-0">Log pesan masuk tersedia di menu <strong>Log → Log WA Blast</strong>.</p>
                    </div>

                    <hr>

                    {{-- Notifikasi Otomatis --}}
                    <h6 class="text-muted text-uppercase mb-3" style="font-size:0.75rem;letter-spacing:.08em">Notifikasi Otomatis</h6>

                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="wa_notify_registration" name="wa_notify_registration" value="1" {{ $settings->wa_notify_registration ? 'checked' : '' }}>
                            <label class="custom-control-label" for="wa_notify_registration">Notifikasi Registrasi Pelanggan Baru</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="wa_notify_invoice" name="wa_notify_invoice" value="1" {{ $settings->wa_notify_invoice ? 'checked' : '' }}>
                            <label class="custom-control-label" for="wa_notify_invoice">Notifikasi Tagihan Baru</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="wa_notify_payment" name="wa_notify_payment" value="1" {{ $settings->wa_notify_payment ? 'checked' : '' }}>
                            <label class="custom-control-label" for="wa_notify_payment">Notifikasi Konfirmasi Pembayaran</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="wa_broadcast_enabled" name="wa_broadcast_enabled" value="1" {{ $settings->wa_broadcast_enabled ? 'checked' : '' }}>
                            <label class="custom-control-label" for="wa_broadcast_enabled">Aktifkan Fitur WA Blast</label>
                        </div>
                    </div>

                    <hr>

                    {{-- Anti-Spam --}}
                    <h6 class="text-muted text-uppercase mb-3" style="font-size:0.75rem;letter-spacing:.08em">Anti-Spam</h6>
                    <p class="text-muted small">Mencegah akun WA diblokir saat melakukan pengiriman pesan massal.</p>

                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="wa_antispam_enabled" name="wa_antispam_enabled" value="1" {{ ($settings->wa_antispam_enabled ?? true) ? 'checked' : '' }}>
                            <label class="custom-control-label" for="wa_antispam_enabled">Aktifkan Anti-Spam</label>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Delay Antar Pesan (ms)</label>
                            <input type="number" name="wa_antispam_delay_ms" class="form-control"
                                value="{{ old('wa_antispam_delay_ms', $settings->wa_antispam_delay_ms ?? 2000) }}"
                                min="500" max="10000" step="100">
                            <small class="text-muted">Rekomendasi: 2000ms (2 detik).</small>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Maks Pesan per Menit</label>
                            <input type="number" name="wa_antispam_max_per_minute" class="form-control"
                                value="{{ old('wa_antispam_max_per_minute', $settings->wa_antispam_max_per_minute ?? 10) }}"
                                min="1" max="20">
                            <small class="text-muted">Rekomendasi: 10.</small>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="wa_msg_randomize" name="wa_msg_randomize" value="1" {{ ($settings->wa_msg_randomize ?? true) ? 'checked' : '' }}>
                            <label class="custom-control-label" for="wa_msg_randomize">Randomisasi Ref Pesan</label>
                        </div>
                        <small class="text-muted">Menambahkan karakter acak tak terlihat di akhir pesan agar konten tidak identik antar penerima.</small>
                    </div>

                    <hr>

                    {{-- Template Pesan --}}
                    <h6 class="text-muted text-uppercase mb-1" style="font-size:0.75rem;letter-spacing:.08em">Template Pesan</h6>
                    <p class="text-muted small mb-1">Placeholder yang tersedia:</p>
                    <div class="mb-3" style="font-size:12px;line-height:2;">
                        <code>{name}</code> Nama pelanggan &nbsp;
                        <code>{customer_id}</code> ID pelanggan &nbsp;
                        <code>{profile}</code> Nama paket &nbsp;
                        <code>{service}</code> Tipe (PPPoE/Hotspot) &nbsp;
                        <code>{total}</code> Harga/Tagihan &nbsp;
                        <code>{due_date}</code> Jatuh tempo &nbsp;
                        <code>{invoice_no}</code> No. invoice &nbsp;
                        <code>{paid_at}</code> Waktu bayar &nbsp;
                        <code>{cs_number}</code> Nomor CS (dari Pengaturan) &nbsp;
                        <code>{bank_account}</code> Info rekening bank &nbsp;
                        <code>{username}</code> Username PPP
                    </div>
                    <p class="text-muted small mb-3">Tombol <strong>Test Kirim</strong> mengirim pesan ke nomor HP bisnis (CS) dengan data dummy. Simpan dulu sebelum test agar template terbaru digunakan.</p>

                    <div class="form-group">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="mb-0">Template Registrasi</label>
                            <button type="button" class="btn btn-outline-success btn-sm btn-test-template" data-type="registration">
                                <i class="fas fa-paper-plane mr-1"></i>Test Kirim ke CS
                            </button>
                        </div>
                        <textarea name="wa_template_registration" class="form-control" rows="6"
                            placeholder="{{ $settings->getDefaultTemplate('registration') }}">{{ old('wa_template_registration', $settings->wa_template_registration) }}</textarea>
                        <small class="text-muted">Kosongkan untuk menggunakan template default.</small>
                        <div class="test-template-result mt-1" data-for="registration"></div>
                    </div>
                    <div class="form-group">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="mb-0">Template Tagihan (Invoice)</label>
                            <button type="button" class="btn btn-outline-success btn-sm btn-test-template" data-type="invoice">
                                <i class="fas fa-paper-plane mr-1"></i>Test Kirim ke CS
                            </button>
                        </div>
                        <textarea name="wa_template_invoice" class="form-control" rows="6"
                            placeholder="{{ $settings->getDefaultTemplate('invoice') }}">{{ old('wa_template_invoice', $settings->wa_template_invoice) }}</textarea>
                        <small class="text-muted">Kosongkan untuk menggunakan template default.</small>
                        <div class="test-template-result mt-1" data-for="invoice"></div>
                    </div>
                    <div class="form-group">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="mb-0">Template Pembayaran</label>
                            <button type="button" class="btn btn-outline-success btn-sm btn-test-template" data-type="payment">
                                <i class="fas fa-paper-plane mr-1"></i>Test Kirim ke CS
                            </button>
                        </div>
                        <textarea name="wa_template_payment" class="form-control" rows="6"
                            placeholder="{{ $settings->getDefaultTemplate('payment') }}">{{ old('wa_template_payment', $settings->wa_template_payment) }}</textarea>
                        <small class="text-muted">Kosongkan untuk menggunakan template default.</small>
                        <div class="test-template-result mt-1" data-for="payment"></div>
                    </div>

                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save mr-1"></i> Simpan Pengaturan WhatsApp
                    </button>
                </div>
            </form>
        </div>

    </div>

    {{-- Sidebar info --}}
    <div class="col-md-4">
        <div class="card card-outline card-success">
            <div class="card-header">
                <h3 class="card-title"><i class="fab fa-whatsapp mr-1"></i> Status Gateway</h3>
            </div>
            <div class="card-body">
                @if($settings->hasWaConfigured())
                    <div class="d-flex align-items-center mb-2">
                        <span class="badge badge-success mr-2">Terkonfigurasi</span>
                        <small class="text-muted text-truncate">{{ $settings->wa_gateway_url }}</small>
                    </div>
                    <a href="{{ route('wa-blast.index') }}" class="btn btn-outline-success btn-sm btn-block">
                        <i class="fas fa-paper-plane mr-1"></i> Buka WA Blast
                    </a>
                @else
                    <div class="text-center text-muted py-2">
                        <i class="fab fa-whatsapp fa-2x mb-2 d-block"></i>
                        <small>Gateway belum dikonfigurasi.<br>Isi URL dan Token lalu simpan.</small>
                    </div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Pintasan</h3>
            </div>
            <div class="card-body p-0">
                <a href="{{ route('wa-blast.index') }}" class="d-flex align-items-center p-3 border-bottom text-dark text-decoration-none">
                    <i class="fas fa-paper-plane fa-fw mr-2 text-success"></i>
                    <span>WA Blast</span>
                    <i class="fas fa-chevron-right ml-auto text-muted small"></i>
                </a>
                <a href="{{ route('tenant-settings.index') }}" class="d-flex align-items-center p-3 text-dark text-decoration-none">
                    <i class="fas fa-cog fa-fw mr-2 text-secondary"></i>
                    <span>Pengaturan</span>
                    <i class="fas fa-chevron-right ml-auto text-muted small"></i>
                </a>
            </div>
        </div>
    </div>
</div>
@endif {{-- end super admin tenant check --}}
@endsection

@push('scripts')
<script>
function copyText(elementId) {
    var el = document.getElementById(elementId);
    el.select();
    el.setSelectionRange(0, 99999);
    document.execCommand('copy');
    window.getSelection && window.getSelection().removeAllRanges();
}

function escapeHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

document.querySelectorAll('.btn-test-template').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var type      = btn.dataset.type;
        var resultDiv = document.querySelector('.test-template-result[data-for="' + type + '"]');
        btn.disabled  = true;
        resultDiv.innerHTML = '<div class="alert alert-info alert-sm py-1 px-2 mb-0"><i class="fas fa-spinner fa-spin mr-1"></i> Mengirim...</div>';

        fetch('{{ route("tenant-settings.test-template") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ type: type })
        })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            if (data.success) {
                resultDiv.innerHTML = '<div class="alert alert-success alert-sm py-1 px-2 mb-0"><i class="fas fa-check-circle mr-1"></i>' + escapeHtml(data.message) + '</div>';
            } else {
                resultDiv.innerHTML = '<div class="alert alert-danger alert-sm py-1 px-2 mb-0"><i class="fas fa-times-circle mr-1"></i>' + escapeHtml(data.message) + '</div>';
            }
            setTimeout(function () { resultDiv.innerHTML = ''; }, 6000);
        })
        .catch(function (e) {
            btn.disabled = false;
            resultDiv.innerHTML = '<div class="alert alert-danger alert-sm py-1 px-2 mb-0">' + escapeHtml(e.message) + '</div>';
        });
    });
});

function testWaGateway() {
    var resultDiv  = document.getElementById('wa-test-result');
    var detailDiv  = document.getElementById('wa-test-detail');
    var detailBody = document.getElementById('wa-test-detail-body');
    var btn        = document.getElementById('btn-test-wa');

    resultDiv.innerHTML     = '<div class="alert alert-info mb-0"><i class="fas fa-spinner fa-spin mr-1"></i> Menguji koneksi, harap tunggu...</div>';
    detailDiv.style.display = 'none';
    detailBody.textContent  = '';
    btn.disabled            = true;

    var url   = document.querySelector('input[name="wa_gateway_url"]').value.trim();
    var token = document.querySelector('input[name="wa_gateway_token"]').value.trim();
    var key   = document.querySelector('input[name="wa_gateway_key"]').value.trim();

    if (!url) {
        resultDiv.innerHTML = '<div class="alert alert-warning mb-0"><i class="fas fa-exclamation-triangle mr-1"></i> URL Gateway belum diisi.</div>';
        btn.disabled = false;
        return;
    }

    if (!token) {
        resultDiv.innerHTML = '<div class="alert alert-warning mb-0"><i class="fas fa-exclamation-triangle mr-1"></i> Token perangkat WA belum diisi. Tanpa token, nomor pengirim akan kosong.</div>';
        btn.disabled = false;
        return;
    }

    var startTime = Date.now();

    fetch('{{ route("tenant-settings.test-wa") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({ wa_gateway_url: url, wa_gateway_token: token, wa_gateway_key: key })
    })
    .then(response => {
        if (response.status === 419) throw new Error('Sesi habis (CSRF). Silakan refresh halaman.');
        var ct = response.headers.get('content-type') || '';
        if (!ct.includes('application/json')) throw new Error('Server mengembalikan respons bukan JSON (HTTP ' + response.status + '). Silakan refresh.');
        return response.json();
    })
    .then(data => {
        var elapsed = ((Date.now() - startTime) / 1000).toFixed(2);
        btn.disabled = false;

        if (data.success) {
            resultDiv.innerHTML =
                '<div class="alert alert-success mb-0">' +
                    '<strong><i class="fas fa-check-circle mr-1"></i> Koneksi Berhasil!</strong><br>' +
                    '<span class="small">' + escapeHtml(data.message) + '</span><br>' +
                    '<span class="small" style="opacity:.8">Waktu: ' + elapsed + 's &nbsp;|&nbsp; ' + escapeHtml(url) + '</span>' +
                '</div>';
        } else {
            var hint = '';
            if (data.http_status === 401) {
                hint = '<li>Token perangkat tidak dikenali gateway.</li>' +
                       '<li>Pastikan token berasal dari device yang aktif di dashboard gateway.</li>' +
                       '<li>Jika perlu format <code>Bearer &lt;token&gt;</code>, tambahkan <code>Bearer </code> di depan nilai Token.</li>';
            } else if (data.http_status === 403) {
                hint = '<li>Gateway menolak akses. Pastikan token memiliki izin yang cukup.</li>';
            } else if (data.network_error) {
                hint = '<li>Pastikan URL benar dan gateway sedang berjalan.</li>';
            } else {
                hint = '<li>Periksa kembali URL Gateway, Token perangkat, dan Key (jika digunakan).</li>';
            }
            resultDiv.innerHTML =
                '<div class="alert alert-danger mb-0">' +
                    '<strong><i class="fas fa-times-circle mr-1"></i> Koneksi Gagal</strong><br>' +
                    '<span class="small">' + escapeHtml(data.message) + '</span>' +
                    '<ul class="small mt-2 mb-1 pl-3">' + hint + '</ul>' +
                    '<span class="small" style="opacity:.8">Waktu: ' + elapsed + 's &nbsp;|&nbsp; ' + escapeHtml(url) + '</span>' +
                '</div>';
        }
        if (data.gateway_response) {
            detailBody.textContent = typeof data.gateway_response === 'string'
                ? data.gateway_response
                : JSON.stringify(data.gateway_response, null, 2);
            detailDiv.style.display = 'block';
        }
    })
    .catch(error => {
        btn.disabled = false;
        resultDiv.innerHTML = '<div class="alert alert-danger mb-0"><strong><i class="fas fa-times-circle mr-1"></i> Permintaan Gagal</strong><br><span class="small">' + escapeHtml(error.message) + '</span></div>';
    });
}
</script>
@endpush
