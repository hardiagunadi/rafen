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
                        <label>Tanggal Penagihan Bulanan</label>
                        <input type="number" name="billing_date" class="form-control" style="max-width:120px;" value="{{ old('billing_date', $settings->billing_date) }}" min="1" max="28" placeholder="–">
                        <small class="text-muted">
                            Isi tanggal 1–28. Jika diisi, jatuh tempo pelanggan baru otomatis ditetapkan ke tanggal ini dan prorata dihitung berdasarkan sisa hari.
                            Kosongkan jika jatuh tempo diatur manual per pelanggan.
                        </small>
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
                    <h5>Payment Gateway</h5>
                    <p class="text-muted small">Pilih gateway untuk pembayaran otomatis via QRIS dan Virtual Account</p>

                    <div class="form-group">
                        <label>Gateway Aktif</label>
                        <select name="active_gateway" id="active_gateway" class="form-control" onchange="showGatewayForm(this.value)">
                            <option value="tripay" {{ ($settings->active_gateway ?? 'tripay') === 'tripay' ? 'selected' : '' }}>Tripay</option>
                            <option value="midtrans" {{ ($settings->active_gateway ?? '') === 'midtrans' ? 'selected' : '' }}>Midtrans</option>
                            <option value="duitku" {{ ($settings->active_gateway ?? '') === 'duitku' ? 'selected' : '' }}>Duitku</option>
                            <option value="ipaymu" {{ ($settings->active_gateway ?? '') === 'ipaymu' ? 'selected' : '' }}>iPaymu</option>
                            <option value="xendit" {{ ($settings->active_gateway ?? '') === 'xendit' ? 'selected' : '' }}>Xendit</option>
                        </select>
                    </div>

                    {{-- Tripay --}}
                    <div id="gateway-tripay" class="gateway-form">
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
                        <p class="text-muted small">
                            Callback URL: <code>{{ url('/payment/callback') }}</code>
                        </p>
                        <button type="button" class="btn btn-info btn-sm mb-3" onclick="testTripay()">
                            <i class="fas fa-plug"></i> Test Koneksi
                        </button>
                        <div id="tripay-test-result"></div>
                    </div>

                    {{-- Midtrans --}}
                    <div id="gateway-midtrans" class="gateway-form" style="display:none;">
                        <div class="form-group">
                            <label>Server Key</label>
                            <input type="password" name="midtrans_server_key" class="form-control" value="{{ old('midtrans_server_key', $settings->midtrans_server_key) }}" placeholder="SB-Mid-server-...">
                        </div>
                        <div class="form-group">
                            <label>Client Key</label>
                            <input type="text" name="midtrans_client_key" class="form-control" value="{{ old('midtrans_client_key', $settings->midtrans_client_key) }}" placeholder="SB-Mid-client-...">
                        </div>
                        <div class="form-group">
                            <label>Merchant ID <span class="text-muted small">(opsional)</span></label>
                            <input type="text" name="midtrans_merchant_id" class="form-control" value="{{ old('midtrans_merchant_id', $settings->midtrans_merchant_id) }}" placeholder="G12345678">
                        </div>
                        <div class="form-group">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="midtrans_sandbox" name="midtrans_sandbox" value="1" {{ ($settings->midtrans_sandbox ?? true) ? 'checked' : '' }}>
                                <label class="custom-control-label" for="midtrans_sandbox">Mode Sandbox (Testing)</label>
                            </div>
                        </div>
                        <p class="text-muted small">
                            Notification URL di Midtrans Dashboard: <code>{{ url('/payment/callback/midtrans') }}</code>
                        </p>
                        <button type="button" class="btn btn-info btn-sm mb-3" onclick="testGateway('midtrans')">
                            <i class="fas fa-plug"></i> Test Koneksi
                        </button>
                        <div id="midtrans-test-result"></div>
                    </div>

                    {{-- Duitku --}}
                    <div id="gateway-duitku" class="gateway-form" style="display:none;">
                        <div class="form-group">
                            <label>Merchant Code</label>
                            <input type="text" name="duitku_merchant_code" class="form-control" value="{{ old('duitku_merchant_code', $settings->duitku_merchant_code) }}" placeholder="DSxxxxx">
                        </div>
                        <div class="form-group">
                            <label>API Key</label>
                            <input type="password" name="duitku_api_key" class="form-control" value="{{ old('duitku_api_key', $settings->duitku_api_key) }}" placeholder="Masukkan API Key Duitku">
                        </div>
                        <div class="form-group">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="duitku_sandbox" name="duitku_sandbox" value="1" {{ ($settings->duitku_sandbox ?? true) ? 'checked' : '' }}>
                                <label class="custom-control-label" for="duitku_sandbox">Mode Sandbox (Testing)</label>
                            </div>
                        </div>
                        <p class="text-muted small">
                            Callback URL di Duitku Dashboard: <code>{{ url('/payment/callback/duitku') }}</code>
                        </p>
                        <button type="button" class="btn btn-info btn-sm mb-3" onclick="testGateway('duitku')">
                            <i class="fas fa-plug"></i> Test Koneksi
                        </button>
                        <div id="duitku-test-result"></div>
                    </div>

                    {{-- iPaymu --}}
                    <div id="gateway-ipaymu" class="gateway-form" style="display:none;">
                        <div class="form-group">
                            <label>Virtual Account (VA)</label>
                            <input type="text" name="ipaymu_va" class="form-control" value="{{ old('ipaymu_va', $settings->ipaymu_va) }}" placeholder="0000000000000000">
                        </div>
                        <div class="form-group">
                            <label>API Key</label>
                            <input type="password" name="ipaymu_api_key" class="form-control" value="{{ old('ipaymu_api_key', $settings->ipaymu_api_key) }}" placeholder="Masukkan API Key iPaymu">
                        </div>
                        <div class="form-group">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="ipaymu_sandbox" name="ipaymu_sandbox" value="1" {{ ($settings->ipaymu_sandbox ?? true) ? 'checked' : '' }}>
                                <label class="custom-control-label" for="ipaymu_sandbox">Mode Sandbox (Testing)</label>
                            </div>
                        </div>
                        <p class="text-muted small text-warning">
                            <i class="fas fa-info-circle"></i> iPaymu belum terintegrasi penuh. Gunakan Midtrans atau Duitku untuk hasil terbaik.
                        </p>
                    </div>

                    {{-- Xendit --}}
                    <div id="gateway-xendit" class="gateway-form" style="display:none;">
                        <div class="form-group">
                            <label>Secret Key</label>
                            <input type="password" name="xendit_secret_key" class="form-control" value="{{ old('xendit_secret_key', $settings->xendit_secret_key) }}" placeholder="xnd_development_...">
                        </div>
                        <div class="form-group">
                            <label>Webhook Token <span class="text-muted small">(dari Xendit Dashboard)</span></label>
                            <input type="text" name="xendit_webhook_token" class="form-control" value="{{ old('xendit_webhook_token', $settings->xendit_webhook_token) }}" placeholder="Webhook verification token">
                        </div>
                        <div class="form-group">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="xendit_sandbox" name="xendit_sandbox" value="1" {{ ($settings->xendit_sandbox ?? true) ? 'checked' : '' }}>
                                <label class="custom-control-label" for="xendit_sandbox">Mode Sandbox (Testing)</label>
                            </div>
                        </div>
                        <p class="text-muted small text-warning">
                            <i class="fas fa-info-circle"></i> Xendit belum terintegrasi penuh. Gunakan Midtrans atau Duitku untuk hasil terbaik.
                        </p>
                    </div>

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
        <!-- WhatsApp Settings (halaman tersendiri) -->
        <div class="card" id="whatsapp">
            <div class="card-header">
                <h3 class="card-title"><i class="fab fa-whatsapp text-success mr-1"></i> Integrasi WhatsApp Gateway</h3>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">Pengaturan WhatsApp Gateway tersedia di halaman khusus.</p>
                <a href="{{ route('wa-gateway.index') }}" class="btn btn-success">
                    <i class="fab fa-whatsapp mr-1"></i> Buka Pengaturan WA Gateway
                </a>
            </div>
        </div>
        <!-- Halaman Isolir -->
        <div class="card" id="isolir-page">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-ban text-danger mr-1"></i> Halaman Info Isolir</h3>
            </div>
            <form action="{{ route('tenant-settings.update-isolir') }}" method="POST">
                @csrf
                @method('PUT')
                <div class="card-body">
                    <p class="text-muted mb-3">
                        Kustomisasi tampilan halaman yang dilihat pelanggan saat layanan diisolir.
                        Halaman ini ditampilkan otomatis saat pelanggan membuka browser.
                    </p>
                    <div class="form-group">
                        <label>Judul Halaman</label>
                        <input type="text" name="isolir_page_title" class="form-control"
                            value="{{ old('isolir_page_title', $settings->isolir_page_title) }}"
                            placeholder="{{ $settings->getIsolirPageTitle() }}">
                        <small class="text-muted">Kosongkan untuk menggunakan default: "Layanan [Nama Bisnis] Dinonaktifkan"</small>
                    </div>
                    <div class="form-group">
                        <label>Pesan Utama</label>
                        <textarea name="isolir_page_body" class="form-control" rows="4"
                            placeholder="{{ $settings->getDefaultTemplate('isolir_body') }}">{{ old('isolir_page_body', $settings->isolir_page_body) }}</textarea>
                        <small class="text-muted">Pesan yang ditampilkan ke pelanggan. Kosongkan untuk teks default.</small>
                    </div>
                    <div class="form-group">
                        <label>Info Kontak</label>
                        <input type="text" name="isolir_page_contact" class="form-control"
                            value="{{ old('isolir_page_contact', $settings->isolir_page_contact) }}"
                            placeholder="{{ $settings->business_phone ?? $settings->business_email ?? 'No. HP / Email CS' }}">
                        <small class="text-muted">Nomor HP atau email CS yang ditampilkan. Kosongkan untuk menggunakan data bisnis.</small>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Warna Background</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text p-1">
                                            <input type="color" id="bg_color_picker" value="{{ $settings->isolir_page_bg_color ?: '#1a1a2e' }}"
                                                style="width:28px;height:28px;border:none;cursor:pointer;padding:0;" oninput="document.getElementById('isolir_page_bg_color').value=this.value">
                                        </span>
                                    </div>
                                    <input type="text" name="isolir_page_bg_color" id="isolir_page_bg_color" class="form-control"
                                        value="{{ old('isolir_page_bg_color', $settings->isolir_page_bg_color ?: '#1a1a2e') }}"
                                        placeholder="#1a1a2e" maxlength="20"
                                        oninput="if(this.value.length===7||this.value.length===4) document.getElementById('bg_color_picker').value=this.value">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Warna Aksen</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text p-1">
                                            <input type="color" id="accent_color_picker" value="{{ $settings->isolir_page_accent_color ?: '#e94560' }}"
                                                style="width:28px;height:28px;border:none;cursor:pointer;padding:0;" oninput="document.getElementById('isolir_page_accent_color').value=this.value">
                                        </span>
                                    </div>
                                    <input type="text" name="isolir_page_accent_color" id="isolir_page_accent_color" class="form-control"
                                        value="{{ old('isolir_page_accent_color', $settings->isolir_page_accent_color ?: '#e94560') }}"
                                        placeholder="#e94560" maxlength="20"
                                        oninput="if(this.value.length===7||this.value.length===4) document.getElementById('accent_color_picker').value=this.value">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-2">
                        <a href="{{ route('tenant-settings.isolir-preview') }}" target="_blank" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-eye mr-1"></i> Preview Halaman Isolir
                        </a>
                        <small class="text-muted ml-2">
                            URL pelanggan: <code>{{ url('isolir/'.$settings->user_id) }}</code>
                        </small>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-save mr-1"></i> Simpan Pengaturan Isolir
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

function escapeHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

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

    var startTime = Date.now();

    fetch('{{ route("tenant-settings.test-wa") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ wa_gateway_url: url, wa_gateway_token: token, wa_gateway_key: key })
    })
    .then(response => {
        if (response.status === 419) {
            throw new Error('Sesi habis (CSRF token kadaluarsa). Silakan refresh halaman lalu coba lagi.');
        }
        var contentType = response.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            throw new Error('Server mengembalikan respons bukan JSON (HTTP ' + response.status + '). Silakan refresh halaman.');
        }
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
                    '<span class="small" style="opacity:0.8">Waktu respons: ' + elapsed + 's &nbsp;|&nbsp; URL: ' + escapeHtml(url) + '</span>' +
                '</div>';
        } else {
            var hint = '';
            if (data.http_status === 401) {
                hint = '<li>Token atau Key yang diisi tidak dikenali oleh gateway.</li>' +
                       '<li>Coba isi hanya <strong>Token</strong>, atau hanya <strong>Key</strong> — tergantung jenis gateway.</li>' +
                       '<li>Jika gateway memerlukan format <code>Bearer &lt;token&gt;</code>, tambahkan <code>Bearer </code> di depan nilai Token.</li>';
            } else if (data.http_status === 403) {
                hint = '<li>Gateway menolak akses. Pastikan token memiliki izin yang cukup.</li>';
            } else if (data.network_error) {
                hint = '<li>Pastikan URL gateway benar dan dapat diakses dari server ini.</li>' +
                       '<li>Cek apakah gateway sedang berjalan.</li>';
            } else {
                hint = '<li>Periksa kembali URL Gateway dan Token/Key.</li>' +
                       '<li>Pastikan gateway sedang aktif.</li>';
            }

            resultDiv.innerHTML =
                '<div class="alert alert-danger mb-0">' +
                    '<strong><i class="fas fa-times-circle mr-1"></i> Koneksi Gagal</strong><br>' +
                    '<span class="small">' + escapeHtml(data.message) + '</span>' +
                    '<ul class="small mt-2 mb-1 pl-3">' + hint + '</ul>' +
                    '<span class="small" style="opacity:0.8">Waktu: ' + elapsed + 's &nbsp;|&nbsp; URL: ' + escapeHtml(url) + '</span>' +
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
        resultDiv.innerHTML =
            '<div class="alert alert-danger mb-0">' +
                '<strong><i class="fas fa-times-circle mr-1"></i> Permintaan Gagal</strong><br>' +
                '<span class="small">' + escapeHtml(error.message) + '</span>' +
            '</div>';
    });
}

function showGatewayForm(gateway) {
    document.querySelectorAll('.gateway-form').forEach(function(el) {
        el.style.display = 'none';
    });
    var form = document.getElementById('gateway-' + gateway);
    if (form) form.style.display = 'block';
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    var activeGateway = document.getElementById('active_gateway');
    if (activeGateway) showGatewayForm(activeGateway.value);
});

var gatewayTestUrls = {
    midtrans: '{{ route("tenant-settings.test-midtrans") }}',
    duitku:   '{{ route("tenant-settings.test-duitku") }}'
};

function testGateway(gateway) {
    var resultDiv = document.getElementById(gateway + '-test-result');
    resultDiv.innerHTML = '<div class="alert alert-info"><i class="fas fa-spinner fa-spin"></i> Menguji koneksi...</div>';

    fetch(gatewayTestUrls[gateway], {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'}
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            resultDiv.innerHTML = '<div class="alert alert-success"><i class="fas fa-check"></i> ' + data.message + '</div>';
        } else {
            resultDiv.innerHTML = '<div class="alert alert-danger"><i class="fas fa-times"></i> ' + data.message + '</div>';
        }
    })
    .catch(function() {
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
