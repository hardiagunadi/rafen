@extends('layouts.admin')

@section('title', 'Pengaturan Bisnis')

@section('content')
<div class="row">
    <div class="col-md-6">
        <!-- Business Settings -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Informasi Bisnis</h3>
            </div>
            <form action="{{ route('tenant-settings.update-business') }}" method="POST">
                @csrf
                @method('PUT')
                <div class="card-body">
                    <div class="form-group">
                        <label>Nama Bisnis</label>
                        <input type="text" name="business_name" class="form-control" value="{{ old('business_name', $settings->business_name) }}">
                    </div>
                    <div class="form-group">
                        <label>Telepon Bisnis</label>
                        <input type="text" name="business_phone" class="form-control" value="{{ old('business_phone', $settings->business_phone) }}">
                    </div>
                    <div class="form-group">
                        <label>Email Bisnis</label>
                        <input type="email" name="business_email" class="form-control" value="{{ old('business_email', $settings->business_email) }}">
                    </div>
                    <div class="form-group">
                        <label>Alamat</label>
                        <textarea name="business_address" class="form-control" rows="3">{{ old('business_address', $settings->business_address) }}</textarea>
                    </div>
                    <div class="form-group">
                        <label>NPWP</label>
                        <input type="text" name="npwp" class="form-control" value="{{ old('npwp', $settings->npwp) }}" placeholder="xx.xxx.xxx.x-xxx.xxx">
                    </div>
                    <div class="form-group">
                        <label>Website</label>
                        <input type="url" name="website" class="form-control" value="{{ old('website', $settings->website) }}" placeholder="https://www.isp-anda.com">
                    </div>
                    <div class="form-group">
                        <label>Prefix Invoice</label>
                        <input type="text" name="invoice_prefix" class="form-control" value="{{ old('invoice_prefix', $settings->invoice_prefix) }}" maxlength="10">
                        <small class="text-muted">Contoh: INV, BILL, dll</small>
                    </div>
                    <div class="form-group">
                        <label>Catatan Invoice</label>
                        <textarea name="invoice_notes" class="form-control" rows="2">{{ old('invoice_notes', $settings->invoice_notes) }}</textarea>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan
                    </button>
                </div>
            </form>
        </div>

        <!-- Logo Upload -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Logo Bisnis</h3>
            </div>
            <div class="card-body">
                @if($settings->business_logo)
                    <div class="mb-3">
                        <img src="{{ Storage::url($settings->business_logo) }}" alt="Logo Bisnis" style="max-height: 80px; max-width: 200px;">
                        <p class="text-muted small mt-1">Logo saat ini</p>
                    </div>
                @else
                    <p class="text-muted small">Belum ada logo. Upload logo untuk ditampilkan di invoice cetak.</p>
                @endif
                <form action="{{ route('tenant-settings.upload-logo') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="input-group">
                        <div class="custom-file">
                            <input type="file" class="custom-file-input" id="business_logo" name="business_logo" accept="image/*">
                            <label class="custom-file-label" for="business_logo">Pilih gambar...</label>
                        </div>
                        <div class="input-group-append">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-upload"></i> Upload
                            </button>
                        </div>
                    </div>
                    <small class="text-muted">Format: JPG, PNG, GIF. Maks: 2MB.</small>
                </form>
            </div>
        </div>

        <!-- Bank Accounts -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Rekening Bank</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addBankModal">
                        <i class="fas fa-plus"></i> Tambah
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Bank</th>
                            <th>Nomor Rekening</th>
                            <th>Atas Nama</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($bankAccounts as $bank)
                        <tr>
                            <td>
                                {{ $bank->bank_name }}
                                @if($bank->is_primary)
                                    <span class="badge badge-primary">Utama</span>
                                @endif
                            </td>
                            <td>{{ $bank->account_number }}</td>
                            <td>{{ $bank->account_name }}</td>
                            <td class="text-right">
                                @if(!$bank->is_primary)
                                <form action="{{ route('tenant-settings.bank-accounts.set-primary', $bank) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-xs btn-outline-primary">Set Utama</button>
                                </form>
                                @endif
                                <form action="{{ route('tenant-settings.bank-accounts.destroy', $bank) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus rekening ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-xs btn-outline-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted">Belum ada rekening bank</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <!-- Payment Settings -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Pengaturan Pembayaran</h3>
            </div>
            <form action="{{ route('tenant-settings.update-payment') }}" method="POST">
                @csrf
                @method('PUT')
                <div class="card-body">
                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="enable_manual_payment" name="enable_manual_payment" value="1" {{ $settings->enable_manual_payment ? 'checked' : '' }}>
                            <label class="custom-control-label" for="enable_manual_payment">Aktifkan Pembayaran Manual (Transfer Bank)</label>
                        </div>
                    </div>

                    <hr>
                    <h5>Integrasi Tripay</h5>
                    <p class="text-muted small">Untuk pembayaran otomatis via QRIS dan Virtual Account</p>

                    <div class="form-group">
                        <label>API Key</label>
                        <input type="password" name="tripay_api_key" class="form-control" value="{{ old('tripay_api_key', $settings->tripay_api_key) }}" placeholder="Masukkan API Key Tripay">
                    </div>
                    <div class="form-group">
                        <label>Private Key</label>
                        <input type="password" name="tripay_private_key" class="form-control" value="{{ old('tripay_private_key', $settings->tripay_private_key) }}" placeholder="Masukkan Private Key Tripay">
                    </div>
                    <div class="form-group">
                        <label>Merchant Code</label>
                        <input type="text" name="tripay_merchant_code" class="form-control" value="{{ old('tripay_merchant_code', $settings->tripay_merchant_code) }}" placeholder="Masukkan Merchant Code">
                    </div>
                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="tripay_sandbox" name="tripay_sandbox" value="1" {{ $settings->tripay_sandbox ? 'checked' : '' }}>
                            <label class="custom-control-label" for="tripay_sandbox">Mode Sandbox (Testing)</label>
                        </div>
                    </div>

                    <button type="button" class="btn btn-info btn-sm mb-3" onclick="testTripay()">
                        <i class="fas fa-plug"></i> Test Koneksi
                    </button>
                    <div id="tripay-test-result"></div>

                    <hr>

                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="enable_qris_payment" name="enable_qris_payment" value="1" {{ $settings->enable_qris_payment ? 'checked' : '' }}>
                            <label class="custom-control-label" for="enable_qris_payment">Aktifkan Pembayaran QRIS</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="enable_va_payment" name="enable_va_payment" value="1" {{ $settings->enable_va_payment ? 'checked' : '' }}>
                            <label class="custom-control-label" for="enable_va_payment">Aktifkan Virtual Account</label>
                        </div>
                    </div>

                    <hr>

                    <div class="form-group">
                        <label>Waktu Kedaluwarsa Pembayaran (Jam)</label>
                        <input type="number" name="payment_expiry_hours" class="form-control" value="{{ old('payment_expiry_hours', $settings->payment_expiry_hours) }}" min="1" max="168">
                    </div>

                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="auto_isolate_unpaid" name="auto_isolate_unpaid" value="1" {{ $settings->auto_isolate_unpaid ? 'checked' : '' }}>
                            <label class="custom-control-label" for="auto_isolate_unpaid">Isolir Otomatis Pelanggan yang Belum Bayar</label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Grace Period (Hari)</label>
                        <input type="number" name="grace_period_days" class="form-control" value="{{ old('grace_period_days', $settings->grace_period_days) }}" min="0" max="30">
                        <small class="text-muted">Toleransi keterlambatan sebelum isolir</small>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan Pengaturan
                    </button>
                </div>
            </form>
        </div>
        <!-- WhatsApp Settings -->
        <div class="card" id="whatsapp">
            <div class="card-header">
                <h3 class="card-title"><i class="fab fa-whatsapp text-success"></i> Integrasi WhatsApp Gateway</h3>
            </div>
            <form action="{{ route('tenant-settings.update-wa') }}" method="POST">
                @csrf
                @method('PUT')
                <div class="card-body">
                    <div class="form-group">
                        <label>URL Gateway <span class="text-muted small">(contoh: https://gateway.watumalang.online)</span></label>
                        <input type="url" name="wa_gateway_url" class="form-control" value="{{ old('wa_gateway_url', $settings->wa_gateway_url) }}" placeholder="https://gateway.watumalang.online">
                    </div>
                    <div class="form-group">
                        <label>Token <span class="text-muted small">(header: Authorization)</span></label>
                        <input type="password" name="wa_gateway_token" class="form-control" value="{{ old('wa_gateway_token', $settings->wa_gateway_token) }}" placeholder="Masukkan token perangkat">
                    </div>
                    <div class="form-group">
                        <label>Key <span class="text-muted small">(header: KEY — master key, opsional)</span></label>
                        <input type="password" name="wa_gateway_key" class="form-control" value="{{ old('wa_gateway_key', $settings->wa_gateway_key) }}" placeholder="Masukkan master key (jika ada)">
                        <small class="text-muted">Isi Token <strong>atau</strong> Key, atau keduanya sesuai konfigurasi gateway Anda.</small>
                    </div>

                    <button type="button" class="btn btn-info btn-sm mb-3" onclick="testWaGateway()">
                        <i class="fas fa-plug"></i> Test Koneksi
                    </button>
                    <div id="wa-test-result"></div>

                    <hr>
                    <h6>Webhook <span class="text-muted small">(untuk menerima pesan masuk dari pelanggan)</span></h6>
                    <div class="alert alert-light border">
                        <p class="mb-1 small"><strong>Konfigurasi di WA Gateway:</strong> set <code>webhookBaseUrl</code> pada <code>session-config.json</code> gateway Anda:</p>
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
                        <p class="text-muted small mt-2 mb-0">Log pesan masuk tersedia di menu <strong>Log → Log WA Blast</strong>.</p>
                    </div>

                    <hr>
                    <h6>Notifikasi Otomatis</h6>

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
                    <h6>Anti-Spam <span class="text-muted small">(mencegah akun WA diblokir saat blast)</span></h6>
                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="wa_antispam_enabled" name="wa_antispam_enabled" value="1" {{ ($settings->wa_antispam_enabled ?? true) ? 'checked' : '' }}>
                            <label class="custom-control-label" for="wa_antispam_enabled">Aktifkan Anti-Spam</label>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Delay Antar Pesan (ms) <span class="text-muted small">min 500ms</span></label>
                            <input type="number" name="wa_antispam_delay_ms" class="form-control" value="{{ old('wa_antispam_delay_ms', $settings->wa_antispam_delay_ms ?? 2000) }}" min="500" max="10000" step="100">
                            <small class="text-muted">Jeda antar pengiriman pesan. Rekomendasi: 2000ms (2 detik).</small>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Maks Pesan per Menit <span class="text-muted small">max 20</span></label>
                            <input type="number" name="wa_antispam_max_per_minute" class="form-control" value="{{ old('wa_antispam_max_per_minute', $settings->wa_antispam_max_per_minute ?? 10) }}" min="1" max="20">
                            <small class="text-muted">Batas pengiriman per menit. Rekomendasi: 10.</small>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="wa_msg_randomize" name="wa_msg_randomize" value="1" {{ ($settings->wa_msg_randomize ?? true) ? 'checked' : '' }}>
                            <label class="custom-control-label" for="wa_msg_randomize">Randomisasi Ref Pesan</label>
                        </div>
                        <small class="text-muted">Menambahkan karakter acak tak terlihat di akhir setiap pesan agar konten tidak identik antar penerima, membantu menghindari deteksi pengiriman massal oleh WhatsApp.</small>
                    </div>

                    <hr>
                    <h6>Template Pesan <span class="text-muted small">— placeholder: <code>{name}</code>, <code>{username}</code>, <code>{service}</code>, <code>{profile}</code>, <code>{due_date}</code>, <code>{invoice_no}</code>, <code>{total}</code>, <code>{paid_at}</code></span></h6>

                    <div class="form-group">
                        <label>Template Registrasi</label>
                        <textarea name="wa_template_registration" class="form-control" rows="4" placeholder="{{ $settings->getDefaultTemplate('registration') }}">{{ old('wa_template_registration', $settings->wa_template_registration) }}</textarea>
                        <small class="text-muted">Kosongkan untuk menggunakan template default</small>
                    </div>
                    <div class="form-group">
                        <label>Template Tagihan</label>
                        <textarea name="wa_template_invoice" class="form-control" rows="4" placeholder="{{ $settings->getDefaultTemplate('invoice') }}">{{ old('wa_template_invoice', $settings->wa_template_invoice) }}</textarea>
                        <small class="text-muted">Kosongkan untuk menggunakan template default</small>
                    </div>
                    <div class="form-group">
                        <label>Template Pembayaran</label>
                        <textarea name="wa_template_payment" class="form-control" rows="4" placeholder="{{ $settings->getDefaultTemplate('payment') }}">{{ old('wa_template_payment', $settings->wa_template_payment) }}</textarea>
                        <small class="text-muted">Kosongkan untuk menggunakan template default</small>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan Pengaturan WhatsApp
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Bank Modal -->
<div class="modal fade" id="addBankModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('tenant-settings.bank-accounts.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Rekening Bank</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nama Bank</label>
                        <select name="bank_name" class="form-control" required>
                            <option value="">Pilih Bank</option>
                            <option value="BCA">BCA</option>
                            <option value="BNI">BNI</option>
                            <option value="BRI">BRI</option>
                            <option value="Mandiri">Mandiri</option>
                            <option value="BSI">BSI</option>
                            <option value="CIMB Niaga">CIMB Niaga</option>
                            <option value="Danamon">Danamon</option>
                            <option value="Permata">Permata</option>
                            <option value="OCBC NISP">OCBC NISP</option>
                            <option value="Lainnya">Lainnya</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Nomor Rekening</label>
                        <input type="text" name="account_number" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Atas Nama</label>
                        <input type="text" name="account_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Cabang (Opsional)</label>
                        <input type="text" name="branch" class="form-control">
                    </div>
                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="is_primary" name="is_primary" value="1">
                            <label class="custom-control-label" for="is_primary">Jadikan Rekening Utama</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function copyText(elementId) {
    var el = document.getElementById(elementId);
    el.select();
    el.setSelectionRange(0, 99999);
    document.execCommand('copy');
    window.getSelection && window.getSelection().removeAllRanges();
}

function testWaGateway() {
    var resultDiv = document.getElementById('wa-test-result');
    resultDiv.innerHTML = '<div class="alert alert-info"><i class="fas fa-spinner fa-spin"></i> Menguji koneksi...</div>';

    fetch('{{ route("tenant-settings.test-wa") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            resultDiv.innerHTML = '<div class="alert alert-success"><i class="fas fa-check"></i> ' + data.message + '</div>';
        } else {
            resultDiv.innerHTML = '<div class="alert alert-danger"><i class="fas fa-times"></i> ' + data.message + '</div>';
        }
    })
    .catch(error => {
        resultDiv.innerHTML = '<div class="alert alert-danger"><i class="fas fa-times"></i> Gagal menguji koneksi</div>';
    });
}

function testTripay() {
    var resultDiv = document.getElementById('tripay-test-result');
    resultDiv.innerHTML = '<div class="alert alert-info"><i class="fas fa-spinner fa-spin"></i> Menguji koneksi...</div>';

    fetch('{{ route("tenant-settings.test-tripay") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            resultDiv.innerHTML = '<div class="alert alert-success"><i class="fas fa-check"></i> ' + data.message + '</div>';
        } else {
            resultDiv.innerHTML = '<div class="alert alert-danger"><i class="fas fa-times"></i> ' + data.message + '</div>';
        }
    })
    .catch(error => {
        resultDiv.innerHTML = '<div class="alert alert-danger"><i class="fas fa-times"></i> Gagal menguji koneksi</div>';
    });
}
</script>
@endpush
@endsection
