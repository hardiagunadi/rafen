@extends('layouts.admin')

@section('title', 'WA Gateway')

@section('content')
@php
    $activeTab = request()->query('tab', 'overview');
    if (! in_array($activeTab, ['overview', 'devices'], true)) {
        $activeTab = 'overview';
    }
    $canAccessWaBlast = auth()->user()->isSuperAdmin() || in_array(auth()->user()->role, ['administrator', 'noc', 'it_support'], true);
    $waDevicesTabUrl = route('wa-gateway.index', array_filter([
        'tab' => 'devices',
        'tenant_id' => auth()->user()->isSuperAdmin() ? ($selectedTenant?->id ?? null) : null,
    ]));
@endphp

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
        <input type="hidden" id="wa_tenant_id_js" value="{{ auth()->user()->isSuperAdmin() ? ($selectedTenant?->id ?? '') : '' }}">
        <div class="card">
            <div class="card-body py-2">
                <ul class="nav nav-pills">
                    <li class="nav-item">
                        <a href="{{ route('wa-gateway.index', auth()->user()->isSuperAdmin() && $selectedTenant ? ['tenant_id' => $selectedTenant->id] : []) }}" class="nav-link {{ $activeTab === 'overview' ? 'active' : '' }}">
                            <i class="fas fa-sliders-h mr-1"></i>Gateway & Template
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('wa-gateway.index', array_filter([
                            'tab' => 'devices',
                            'tenant_id' => auth()->user()->isSuperAdmin() ? ($selectedTenant?->id ?? null) : null,
                        ])) }}" class="nav-link {{ $activeTab === 'devices' ? 'active' : '' }}">
                            <i class="fas fa-mobile-alt mr-1"></i>Manajemen Device
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        @if($activeTab === 'overview')
        <div class="card card-outline card-success" id="wa-onboarding-wizard">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-route mr-1"></i> Wizard Onboarding WhatsApp</h3>
            </div>
            <div class="card-body pb-2">
                <p class="text-muted small mb-3">Ikuti 5 langkah ini untuk menyiapkan WhatsApp tenant dari nol sampai siap kirim.</p>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="border rounded p-3 h-100">
                            <div class="d-flex align-items-center mb-2">
                                <span class="badge badge-secondary mr-2" data-wizard-badge="1">1</span>
                                <strong>Validasi Koneksi Gateway</strong>
                            </div>
                            <p class="text-muted small mb-2">Cek gateway internal dapat diakses sebelum setup device.</p>
                            <button type="button" class="btn btn-sm btn-info" onclick="scrollToWaSection('wa-section-connection')">Buka Koneksi</button>
                            <button type="button" class="btn btn-sm btn-outline-success ml-1" data-wizard-complete="1">Tandai Selesai</button>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="border rounded p-3 h-100">
                            <div class="d-flex align-items-center mb-2">
                                <span class="badge badge-secondary mr-2" data-wizard-badge="2">2</span>
                                <strong>Tambahkan Device</strong>
                            </div>
                            <p class="text-muted small mb-2">Masuk ke tab Device untuk membuat sesi perangkat WA tenant.</p>
                            <a href="{{ $waDevicesTabUrl }}" class="btn btn-sm btn-primary">Buka Manajemen Device</a>
                            <button type="button" class="btn btn-sm btn-outline-success ml-1" data-wizard-complete="2">Tandai Selesai</button>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="border rounded p-3 h-100">
                            <div class="d-flex align-items-center mb-2">
                                <span class="badge badge-secondary mr-2" data-wizard-badge="3">3</span>
                                <strong>Scan QR Device</strong>
                            </div>
                            <p class="text-muted small mb-2">Di tab Device, klik <strong>Scan QR</strong>, lalu tautkan WhatsApp.</p>
                            <a href="{{ $waDevicesTabUrl }}" class="btn btn-sm btn-primary">Lanjut Scan QR</a>
                            <button type="button" class="btn btn-sm btn-outline-success ml-1" data-wizard-complete="3">Tandai Selesai</button>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="border rounded p-3 h-100">
                            <div class="d-flex align-items-center mb-2">
                                <span class="badge badge-secondary mr-2" data-wizard-badge="4">4</span>
                                <strong>Aktifkan Otomasi & Blast</strong>
                            </div>
                            <p class="text-muted small mb-2">Nyalakan notifikasi otomatis, optimasi blast, dan anti-spam sesuai kebutuhan.</p>
                            <button type="button" class="btn btn-sm btn-info" onclick="scrollToWaSection('wa-section-notification')">Buka Pengaturan</button>
                            <button type="button" class="btn btn-sm btn-outline-success ml-1" data-wizard-complete="4">Tandai Selesai</button>
                        </div>
                    </div>
                    <div class="col-md-12 mb-1">
                        <div class="border rounded p-3">
                            <div class="d-flex align-items-center mb-2">
                                <span class="badge badge-secondary mr-2" data-wizard-badge="5">5</span>
                                <strong>Uji Template & Simpan</strong>
                            </div>
                            <p class="text-muted small mb-2">Review template, test kirim ke CS, lalu simpan konfigurasi WhatsApp.</p>
                            <button type="button" class="btn btn-sm btn-info" onclick="scrollToWaSection('wa-section-template')">Buka Template</button>
                            <button type="button" class="btn btn-sm btn-outline-success ml-1" data-wizard-complete="5">Tandai Selesai</button>
                            <button type="button" class="btn btn-sm btn-outline-dark ml-1" id="wa-wizard-reset">Reset Wizard</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

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
                    <div class="row">
                        <div class="col-lg-4 col-xl-3 mb-3">
                            <div class="list-group sticky-top" style="top: 85px;">
                                <a href="#wa-section-connection" class="list-group-item list-group-item-action">1. Koneksi Gateway</a>
                                <a href="#wa-section-notification" class="list-group-item list-group-item-action">2. Notifikasi Otomatis</a>
                                <a href="#wa-section-blast" class="list-group-item list-group-item-action">3. Pengiriman WA Blast</a>
                                <a href="#wa-section-antispam" class="list-group-item list-group-item-action">4. Anti-Spam</a>
                                <a href="#wa-section-template" class="list-group-item list-group-item-action">5. Template Pesan</a>
                            </div>
                        </div>
                        <div class="col-lg-8 col-xl-9">
                            <div class="card card-outline card-info mb-3" id="wa-section-connection">
                                <div class="card-header py-2"><strong>Koneksi Gateway</strong></div>
                                <div class="card-body">
                                    <div class="form-group">
                                        <label>URL Gateway</label>
                                        <input type="url" name="wa_gateway_url" class="form-control"
                                            value="{{ old('wa_gateway_url', config('wa.multi_session.public_url')) }}"
                                            readonly>
                                        <small class="text-muted">Terkunci ke gateway internal rafen.</small>
                                    </div>
                                    <div class="alert alert-light border mb-3">
                                        <div class="small text-muted mb-1"><strong>Kredensial gateway dikelola internal.</strong></div>
                                        <div class="small text-muted mb-0">Header <code>key</code> (master key) terisi otomatis dari environment server. Header <code>Authorization</code> ditangani internal per device, sehingga tidak ditampilkan di halaman ini.</div>
                                    </div>
                                    <button type="button" class="btn btn-info btn-sm mb-2" id="btn-test-wa" onclick="testWaGateway()">
                                        <i class="fas fa-plug"></i> Test Koneksi
                                    </button>
                                    <div id="wa-test-result" class="mb-2"></div>
                                    <div id="wa-test-detail" class="mt-1" style="display:none;">
                                        <small class="text-muted d-block mb-1">Detail respons gateway:</small>
                                        <pre id="wa-test-detail-body" class="bg-light border rounded p-2" style="font-size:11px;max-height:200px;overflow:auto;white-space:pre-wrap;"></pre>
                                    </div>
                                </div>
                            </div>

                            <div class="card card-outline card-primary mb-3" id="wa-section-notification">
                                <div class="card-header py-2"><strong>Notifikasi Otomatis</strong></div>
                                <div class="card-body">
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
                                    <div class="form-group mb-0">
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" class="custom-control-input" id="wa_notify_payment" name="wa_notify_payment" value="1" {{ $settings->wa_notify_payment ? 'checked' : '' }}>
                                            <label class="custom-control-label" for="wa_notify_payment">Notifikasi Konfirmasi Pembayaran</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card card-outline card-success mb-3" id="wa-section-blast">
                                <div class="card-header py-2"><strong>Pengiriman WA Blast</strong></div>
                                <div class="card-body">
                                    <div class="form-group">
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" class="custom-control-input" id="wa_broadcast_enabled" name="wa_broadcast_enabled" value="1" {{ $settings->wa_broadcast_enabled ? 'checked' : '' }}>
                                            <label class="custom-control-label" for="wa_broadcast_enabled">Aktifkan Fitur WA Blast</label>
                                        </div>
                                    </div>
                                    <div class="form-group mb-2">
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" class="custom-control-input" id="wa_blast_multi_device" name="wa_blast_multi_device" value="1" {{ ($settings->wa_blast_multi_device ?? true) ? 'checked' : '' }}>
                                            <label class="custom-control-label" for="wa_blast_multi_device">Distribusi ke Multi Device Aktif (Round Robin + Failover)</label>
                                        </div>
                                    </div>
                                    <div class="form-group mb-2">
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" class="custom-control-input" id="wa_blast_message_variation" name="wa_blast_message_variation" value="1" {{ ($settings->wa_blast_message_variation ?? true) ? 'checked' : '' }}>
                                            <label class="custom-control-label" for="wa_blast_message_variation">Variasi Pesan Natural Profesional</label>
                                        </div>
                                        <small class="text-muted">Menambahkan pembuka/penutup profesional yang bervariasi saat WA Blast agar tidak terlalu seragam.</small>
                                    </div>
                                    <div class="form-row mb-0">
                                        <div class="form-group col-md-6 mb-0">
                                            <label class="mb-1">Delay Blast Minimal (ms)</label>
                                            <input type="number" name="wa_blast_delay_min_ms" class="form-control"
                                                value="{{ old('wa_blast_delay_min_ms', $settings->wa_blast_delay_min_ms ?? 1200) }}"
                                                min="300" max="15000" step="100">
                                        </div>
                                        <div class="form-group col-md-6 mb-0">
                                            <label class="mb-1">Delay Blast Maksimal (ms)</label>
                                            <input type="number" name="wa_blast_delay_max_ms" class="form-control"
                                                value="{{ old('wa_blast_delay_max_ms', $settings->wa_blast_delay_max_ms ?? 2600) }}"
                                                min="300" max="20000" step="100">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card card-outline card-warning mb-3" id="wa-section-antispam">
                                <div class="card-header py-2"><strong>Anti-Spam</strong></div>
                                <div class="card-body">
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
                                    <div class="form-group mb-0">
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" class="custom-control-input" id="wa_msg_randomize" name="wa_msg_randomize" value="1" {{ ($settings->wa_msg_randomize ?? true) ? 'checked' : '' }}>
                                            <label class="custom-control-label" for="wa_msg_randomize">Randomisasi Ref Pesan</label>
                                        </div>
                                        <small class="text-muted">Menambahkan karakter acak tak terlihat di akhir pesan agar konten tidak identik antar penerima.</small>
                                    </div>
                                </div>
                            </div>

                            <div class="card card-outline card-secondary mb-0" id="wa-section-template">
                                <div class="card-header py-2"><strong>Template Pesan</strong></div>
                                <div class="card-body">
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
                                        <code>{payment_link}</code> Link bayar pelanggan &nbsp;
                                        <code>{username}</code> Username PPP
                                    </div>
                                    <p class="text-muted small mb-2">Rotasi template aktif otomatis. Jika ingin custom beberapa versi pesan, pisahkan setiap versi dengan baris <code>---</code> dalam kolom template yang sama.</p>
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
                                        <small class="text-muted">Kosongkan untuk template default humanis + rotasi otomatis.</small>
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
                                        <small class="text-muted">Kosongkan untuk template default humanis + rotasi otomatis.</small>
                                        <div class="test-template-result mt-1" data-for="invoice"></div>
                                    </div>
                                    <div class="form-group mb-0">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="mb-0">Template Pembayaran</label>
                                            <button type="button" class="btn btn-outline-success btn-sm btn-test-template" data-type="payment">
                                                <i class="fas fa-paper-plane mr-1"></i>Test Kirim ke CS
                                            </button>
                                        </div>
                                        <textarea name="wa_template_payment" class="form-control" rows="6"
                                            placeholder="{{ $settings->getDefaultTemplate('payment') }}">{{ old('wa_template_payment', $settings->wa_template_payment) }}</textarea>
                                        <small class="text-muted">Kosongkan untuk template default humanis + rotasi otomatis.</small>
                                        <div class="test-template-result mt-1" data-for="payment"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save mr-1"></i> Simpan Pengaturan WhatsApp
                    </button>
                </div>
            </form>
        </div>
        @endif

        @if($activeTab === 'devices')
        <div class="card" id="wa-device-management-card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-mobile-alt mr-1"></i> Manajemen Device WA</h3>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">Tambahkan beberapa device untuk operasional CS/kasir, lalu tentukan device default untuk pengiriman otomatis.</p>

                <form id="wa-device-form">
                    <div class="form-row">
                        <div class="form-group col-md-5">
                            <label for="wa_device_name">Nama Device</label>
                            <input type="text" id="wa_device_name" name="device_name" class="form-control" placeholder="Contoh: CS Utama" maxlength="120" required>
                        </div>
                        <div class="form-group col-md-5">
                            <label for="wa_session_id">Session ID (opsional)</label>
                            <input type="text" id="wa_session_id" name="session_id" class="form-control" placeholder="Otomatis jika dikosongkan" maxlength="150">
                            <small class="text-muted">Karakter: huruf, angka, titik, underscore, dash.</small>
                        </div>
                        <div class="form-group col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-success btn-block" id="wa-device-add-button">
                                <i class="fas fa-plus mr-1"></i>Tambah
                            </button>
                        </div>
                    </div>
                </form>

                <div id="wa-device-result" class="mb-3"></div>

                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Device</th>
                                <th>Session ID</th>
                                <th>Default</th>
                                <th>Koneksi</th>
                                <th class="text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="wa-device-table-body">
                            <tr>
                                <td colspan="5" class="text-center text-muted py-3">Memuat data device...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif

        <div class="modal fade" id="wa-qr-modal" tabindex="-1" role="dialog" aria-labelledby="wa-qr-modal-label" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="wa-qr-modal-label"><i class="fas fa-qrcode mr-1"></i> Scan QR WhatsApp</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="small text-muted mb-2">Device: <strong id="wa-qr-device-name">-</strong></div>
                        <div id="wa-qr-alert" class="mb-2"></div>
                        <div id="wa-qr-canvas-wrap" class="text-center d-none">
                            <div id="wa-qr-canvas" class="d-inline-block p-2 bg-white border rounded"></div>
                        </div>
                        <div class="small text-primary mt-2 mb-0" id="wa-qr-countdown"></div>
                        <div class="small text-muted mt-2 mb-0" id="wa-qr-meta"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="refreshDeviceQrStatus('status')">Cek Status</button>
                        <button type="button" class="btn btn-warning btn-sm" onclick="refreshDeviceQrStatus('restart')">Generate QR Baru</button>
                        <button type="button" class="btn btn-light btn-sm" data-dismiss="modal">Tutup</button>
                    </div>
                </div>
            </div>
        </div>

    </div>

    {{-- Sidebar info --}}
    <div class="col-md-4">
        @if(auth()->user()->isSuperAdmin())
        <div class="card card-outline card-info">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-server mr-1"></i> Service wa-multi-session</h3>
            </div>
            <div class="card-body">
                <div id="wa-service-status" class="small text-muted mb-2">
                    @if(($waServiceStatus['data']['running'] ?? false) === true)
                        Status: <strong>RUNNING</strong>
                        @if(!empty($waServiceStatus['data']['pm2_pid']))
                            | PID: {{ $waServiceStatus['data']['pm2_pid'] }}
                        @endif
                    @else
                        Belum dicek.
                    @endif
                </div>
                <div class="btn-group btn-group-sm d-flex mb-2" role="group">
                    <button type="button" class="btn btn-outline-warning w-100" onclick="restartWaService()">Restart PM2</button>
                </div>
                <div id="wa-service-result"></div>
            </div>
        </div>
        @endif

        <div class="card card-outline card-success">
            <div class="card-header">
                <h3 class="card-title"><i class="fab fa-whatsapp mr-1"></i> Status Gateway</h3>
            </div>
            <div class="card-body">
                @if($settings->hasWaConfigured())
                    <div class="d-flex align-items-center mb-2">
                        <span class="badge badge-success mr-2">Terkonfigurasi</span>
                        <small class="text-muted text-truncate">{{ config('wa.multi_session.public_url') }}</small>
                    </div>
                    @if($canAccessWaBlast)
                    <a href="{{ route('wa-blast.index') }}" class="btn btn-outline-success btn-sm btn-block">
                        <i class="fas fa-paper-plane mr-1"></i> Buka WA Blast
                    </a>
                    @endif
                @else
                    <div class="text-center text-muted py-2">
                        <i class="fab fa-whatsapp fa-2x mb-2 d-block"></i>
                        <small>Gateway internal belum siap.<br>Periksa variabel WA_MULTI_SESSION_*.</small>
                    </div>
                @endif

                @if($settings->hasWaConfigured())
                    <hr>
                    <div class="small text-muted mb-2">
                        Session ID Tenant: <code>{{ 'tenant-' . ($settings->user_id ?? auth()->user()->effectiveOwnerId()) }}</code>
                    </div>
                    <div class="small text-muted mb-0">Session dikelola otomatis oleh gateway lokal.</div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Pintasan</h3>
            </div>
            <div class="card-body p-0">
                <a href="{{ route('wa-gateway.index', array_filter([
                    'tab' => 'devices',
                    'tenant_id' => auth()->user()->isSuperAdmin() ? ($selectedTenant?->id ?? null) : null,
                ])) }}" class="d-flex align-items-center p-3 border-bottom text-dark text-decoration-none">
                    <i class="fas fa-mobile-alt fa-fw mr-2 text-primary"></i>
                    <span>Manajemen Device</span>
                    <i class="fas fa-chevron-right ml-auto text-muted small"></i>
                </a>
                @if($canAccessWaBlast)
                <a href="{{ route('wa-blast.index') }}" class="d-flex align-items-center p-3 border-bottom text-dark text-decoration-none">
                    <i class="fas fa-paper-plane fa-fw mr-2 text-success"></i>
                    <span>WA Blast</span>
                    <i class="fas fa-chevron-right ml-auto text-muted small"></i>
                </a>
                @endif
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
<script src="{{ asset('vendor/qrcodejs/qrcode.min.js') }}"></script>
<script>
function escapeHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

var waWizardStorageKey = 'wa-onboarding-{{ (int) ($settings->user_id ?? auth()->user()->effectiveOwnerId()) }}';

function scrollToWaSection(sectionId) {
    var section = document.getElementById(sectionId);
    if (!section) {
        return;
    }

    section.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function readWaWizardState() {
    try {
        var raw = localStorage.getItem(waWizardStorageKey);
        if (!raw) {
            return {};
        }

        var parsed = JSON.parse(raw);
        return typeof parsed === 'object' && parsed !== null ? parsed : {};
    } catch (error) {
        return {};
    }
}

function writeWaWizardState(state) {
    try {
        localStorage.setItem(waWizardStorageKey, JSON.stringify(state));
    } catch (error) {
        // no-op
    }
}

function renderWaWizard() {
    var state = readWaWizardState();
    document.querySelectorAll('[data-wizard-badge]').forEach(function (badge) {
        var step = String(badge.getAttribute('data-wizard-badge') || '');
        var completed = !!state[step];
        badge.className = 'badge mr-2 ' + (completed ? 'badge-success' : 'badge-secondary');
        badge.textContent = completed ? '✓' : step;
    });
}

function setupWaWizard() {
    var wizard = document.getElementById('wa-onboarding-wizard');
    if (!wizard) {
        return;
    }

    document.querySelectorAll('[data-wizard-complete]').forEach(function (button) {
        button.addEventListener('click', function () {
            var step = String(button.getAttribute('data-wizard-complete') || '');
            var state = readWaWizardState();
            state[step] = true;
            writeWaWizardState(state);
            renderWaWizard();
        });
    });

    var resetButton = document.getElementById('wa-wizard-reset');
    if (resetButton) {
        resetButton.addEventListener('click', function () {
            writeWaWizardState({});
            renderWaWizard();
        });
    }

    renderWaWizard();
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
            body: JSON.stringify(Object.assign(getTenantPayload(), { type: type }))
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

    var startTime = Date.now();

    fetch('{{ route("tenant-settings.test-wa") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify(getTenantPayload())
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
                    '<span class="small" style="opacity:.8">Waktu: ' + elapsed + 's</span>' +
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
                hint = '<li>Pastikan wa-multi-session aktif di PM2 dan route /wa-multi-session bisa diakses.</li>';
            } else {
                hint = '<li>Periksa konfigurasi WA_MULTI_SESSION_* pada environment server.</li>';
            }
            resultDiv.innerHTML =
                '<div class="alert alert-danger mb-0">' +
                    '<strong><i class="fas fa-times-circle mr-1"></i> Koneksi Gagal</strong><br>' +
                    '<span class="small">' + escapeHtml(data.message) + '</span>' +
                    '<ul class="small mt-2 mb-1 pl-3">' + hint + '</ul>' +
                    '<span class="small" style="opacity:.8">Waktu: ' + elapsed + 's</span>' +
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

function getTenantPayload() {
    var tenantId = document.getElementById('wa_tenant_id_js')?.value || '';
    if (!tenantId) {
        return {};
    }

    return { tenant_id: Number(tenantId) };
}

function renderWaDeviceBadge(isDefault) {
    if (isDefault) {
        return '<span class="badge badge-success">Default</span>';
    }

    return '<span class="badge badge-secondary">Cadangan</span>';
}

var waDeviceState = {
    devices: [],
    statusTimer: null,
};

function renderWaDeviceTable(devices) {
    var body = document.getElementById('wa-device-table-body');
    if (!body) {
        return;
    }

    if (!Array.isArray(devices) || devices.length === 0) {
        body.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Belum ada device. Tambahkan device pertama Anda.</td></tr>';

        return;
    }

    body.innerHTML = devices.map(function (device) {
        var deviceName = escapeHtml(device.device_name || '-');
        var sessionId = escapeHtml(device.session_id || '-');
        var statusBadge = renderWaDeviceBadge(!!device.is_default);
        var actions = '';

        if (!device.is_default) {
            actions += '<button type="button" class="btn btn-outline-primary btn-sm mr-1 mb-1" onclick="setDefaultWaDevice(' + Number(device.id) + ')">Jadikan Default</button>';
        }

        actions += '<button type="button" class="btn btn-outline-success btn-sm mr-1 mb-1" onclick=\'openQrModal(' + Number(device.id) + ', ' + JSON.stringify(String(device.device_name || 'Device')) + ')\'>Scan QR</button>';
        actions += '<button type="button" class="btn btn-outline-info btn-sm mr-1 mb-1" onclick="controlDeviceSession(' + Number(device.id) + ', \'status\')">Cek Sesi</button>';
        actions += '<button type="button" class="btn btn-outline-warning btn-sm mr-1 mb-1" onclick="controlDeviceSession(' + Number(device.id) + ', \'restart\')">Restart Sesi</button>';
        actions += '<button type="button" class="btn btn-outline-danger btn-sm mb-1" onclick="deleteWaDevice(' + Number(device.id) + ')">Hapus</button>';

        return '' +
            '<tr>' +
                '<td>' + deviceName + '</td>' +
                '<td><code>' + sessionId + '</code></td>' +
                '<td>' + statusBadge + '</td>' +
                '<td><span class="badge badge-light" id="wa-device-conn-' + Number(device.id) + '">Mengecek...</span></td>' +
                '<td class="text-right">' + actions + '</td>' +
            '</tr>';
    }).join('');
}

function resolveConnectionBadge(status) {
    var normalized = String(status || '').toLowerCase();
    if (normalized === 'loading') {
        return { cls: 'light', text: 'Mengecek...' };
    }
    if (normalized === 'connected') {
        return { cls: 'success', text: 'Connected' };
    }
    if (normalized === 'connecting') {
        return { cls: 'info', text: 'Proses Login' };
    }
    if (normalized === 'awaiting_qr' || normalized === 'idle' || normalized === 'stopped') {
        return { cls: 'warning', text: 'Belum Scan' };
    }
    if (normalized === 'disconnected' || normalized === 'error') {
        return { cls: 'danger', text: 'Disconnected' };
    }

    return { cls: 'secondary', text: 'Tidak diketahui' };
}

function updateDeviceConnectionBadge(deviceId, status) {
    var badge = document.getElementById('wa-device-conn-' + Number(deviceId));
    if (!badge) {
        return;
    }

    var resolved = resolveConnectionBadge(status);
    badge.className = 'badge badge-' + resolved.cls;
    badge.textContent = resolved.text;
}

function hydrateDeviceConnectionStatuses(devices) {
    if (!Array.isArray(devices) || devices.length === 0) {
        return;
    }

    devices.forEach(function (device) {
        updateDeviceConnectionBadge(device.id, 'loading');
        fetch('{{ route("tenant-settings.wa-session-control", ["action" => "status"]) }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify(Object.assign(getTenantPayload(), { device_id: Number(device.id) })),
        })
        .then(function (response) {
            return response.json();
        })
        .then(function (data) {
            if (!data.success) {
                updateDeviceConnectionBadge(device.id, 'disconnected');

                return;
            }

            updateDeviceConnectionBadge(device.id, data?.data?.status || 'unknown');
        })
        .catch(function () {
            updateDeviceConnectionBadge(device.id, 'disconnected');
        });
    });
}

function stopDeviceStatusAutoRefresh() {
    if (waDeviceState.statusTimer) {
        clearInterval(waDeviceState.statusTimer);
        waDeviceState.statusTimer = null;
    }
}

function startDeviceStatusAutoRefresh() {
    stopDeviceStatusAutoRefresh();
    waDeviceState.statusTimer = setInterval(function () {
        hydrateDeviceConnectionStatuses(waDeviceState.devices || []);
    }, 10000);
}

function setWaDeviceResult(message, type) {
    var result = document.getElementById('wa-device-result');
    if (!result) {
        return;
    }

    result.innerHTML = '<div class="alert alert-' + type + ' py-2 px-3 mb-0">' + escapeHtml(message) + '</div>';
}

function loadWaDevices() {
    var body = document.getElementById('wa-device-table-body');
    if (!body) {
        return Promise.resolve();
    }

    body.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Memuat data device...</td></tr>';

    return fetch('{{ route("tenant-settings.wa-devices.index") }}?' + new URLSearchParams(getTenantPayload()), {
        method: 'GET',
        headers: { 'Accept': 'application/json' },
    })
    .then(function (response) {
        return response.json();
    })
    .then(function (data) {
        if (!data.success) {
            throw new Error(data.message || 'Gagal memuat device.');
        }

        waDeviceState.devices = data.data || [];
        renderWaDeviceTable(data.data || []);
        hydrateDeviceConnectionStatuses(data.data || []);
    })
    .catch(function (error) {
        waDeviceState.devices = [];
        body.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-3">' + escapeHtml(error.message) + '</td></tr>';
    });
}

function submitWaDeviceForm(event) {
    event.preventDefault();

    var form = document.getElementById('wa-device-form');
    var button = document.getElementById('wa-device-add-button');
    if (!form || !button) {
        return;
    }

    var deviceName = (document.getElementById('wa_device_name')?.value || '').trim();
    var sessionId = (document.getElementById('wa_session_id')?.value || '').trim();

    if (!deviceName) {
        setWaDeviceResult('Nama device wajib diisi.', 'danger');

        return;
    }

    var payload = Object.assign(getTenantPayload(), {
        device_name: deviceName,
        session_id: sessionId || null,
    });

    button.disabled = true;
    setWaDeviceResult('Menambahkan device...', 'info');

    fetch('{{ route("tenant-settings.wa-devices.store") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify(payload),
    })
    .then(function (response) {
        return response.json();
    })
    .then(function (data) {
        if (!data.success) {
            throw new Error(data.message || 'Gagal menambahkan device.');
        }

        form.reset();
        setWaDeviceResult(data.message || 'Device berhasil ditambahkan.', 'success');

        return loadWaDevices();
    })
    .catch(function (error) {
        setWaDeviceResult(error.message, 'danger');
    })
    .finally(function () {
        button.disabled = false;
    });
}

function setDefaultWaDevice(deviceId) {
    setWaDeviceResult('Mengubah device default...', 'info');

    fetch('{{ route("tenant-settings.wa-devices.default", ["device" => "__DEVICE__"]) }}'.replace('__DEVICE__', String(deviceId)), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify(getTenantPayload()),
    })
    .then(function (response) {
        return response.json();
    })
    .then(function (data) {
        if (!data.success) {
            throw new Error(data.message || 'Gagal mengubah default device.');
        }

        setWaDeviceResult(data.message || 'Default device berhasil diperbarui.', 'success');

        return loadWaDevices();
    })
    .catch(function (error) {
        setWaDeviceResult(error.message, 'danger');
    });
}

function deleteWaDevice(deviceId) {
    if (!window.confirm('Hapus device ini?')) {
        return;
    }

    setWaDeviceResult('Menghapus device...', 'info');

    fetch('{{ route("tenant-settings.wa-devices.destroy", ["device" => "__DEVICE__"]) }}'.replace('__DEVICE__', String(deviceId)), {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify(getTenantPayload()),
    })
    .then(function (response) {
        return response.json();
    })
    .then(function (data) {
        if (!data.success) {
            throw new Error(data.message || 'Gagal menghapus device.');
        }

        setWaDeviceResult(data.message || 'Device berhasil dihapus.', 'success');

        return loadWaDevices();
    })
    .catch(function (error) {
        setWaDeviceResult(error.message, 'danger');
    });
}

function controlDeviceSession(deviceId, action) {
    var actionLabel = action === 'restart' ? 'Merestart sesi device...' : 'Mengecek sesi device...';
    setWaDeviceResult(actionLabel, 'info');

    fetch('{{ route("tenant-settings.wa-session-control", ["action" => "__ACTION__"]) }}'.replace('__ACTION__', action), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify(Object.assign(getTenantPayload(), { device_id: Number(deviceId) })),
    })
    .then(function (response) {
        return response.json();
    })
    .then(function (data) {
        if (!data.success) {
            throw new Error(data.message || 'Aksi sesi device gagal.');
        }

        setWaDeviceResult(data.message || 'Aksi sesi berhasil.', 'success');
    })
    .catch(function (error) {
        setWaDeviceResult(error.message, 'danger');
    });
}

var waQrState = {
    deviceId: null,
    deviceName: '',
    pollTimer: null,
    countdownTimer: null,
    currentQr: null,
    qrDeadlineMs: 0,
    regenerating: false,
    connectingDeadlineMs: 0,
};

function showQrAlert(message, type) {
    var alertEl = document.getElementById('wa-qr-alert');
    if (!alertEl) {
        return;
    }

    alertEl.innerHTML = '<div class="alert alert-' + type + ' py-2 px-3 mb-0">' + escapeHtml(message) + '</div>';
}

function clearQrRenderer() {
    var wrap = document.getElementById('wa-qr-canvas-wrap');
    var canvas = document.getElementById('wa-qr-canvas');
    var meta = document.getElementById('wa-qr-meta');
    var countdown = document.getElementById('wa-qr-countdown');
    if (!wrap || !canvas || !meta || !countdown) {
        return;
    }

    wrap.classList.add('d-none');
    canvas.innerHTML = '';
    meta.textContent = '';
    countdown.textContent = '';
}

function stopQrPolling() {
    if (waQrState.pollTimer) {
        clearInterval(waQrState.pollTimer);
        waQrState.pollTimer = null;
    }
}

function startQrPolling() {
    stopQrPolling();
    waQrState.pollTimer = setInterval(function () {
        refreshDeviceQrStatus('status', true);
    }, 3500);
}

function stopQrCountdown() {
    if (waQrState.countdownTimer) {
        clearInterval(waQrState.countdownTimer);
        waQrState.countdownTimer = null;
    }
}

function updateQrCountdownText() {
    var countdown = document.getElementById('wa-qr-countdown');
    if (!countdown) {
        return;
    }

    if (!waQrState.currentQr || waQrState.qrDeadlineMs <= 0) {
        countdown.textContent = '';

        return;
    }

    var remainMs = waQrState.qrDeadlineMs - Date.now();
    var remainSec = Math.max(0, Math.ceil(remainMs / 1000));
    countdown.textContent = 'Timer scan: ' + remainSec + ' detik';
}

function autoRegenerateQrIfExpired() {
    if (!waQrState.currentQr || waQrState.qrDeadlineMs <= 0 || waQrState.regenerating) {
        return;
    }

    if (Date.now() < waQrState.qrDeadlineMs) {
        return;
    }

    waQrState.regenerating = true;
    showQrAlert('Waktu scan habis. Generate QR baru...', 'warning');
    waQrState.currentQr = null;
    waQrState.qrDeadlineMs = 0;
    clearQrRenderer();

    refreshDeviceQrStatus('restart', true)
        .finally(function () {
            waQrState.regenerating = false;
        });
}

function startQrCountdown() {
    stopQrCountdown();
    updateQrCountdownText();

    waQrState.countdownTimer = setInterval(function () {
        updateQrCountdownText();
        autoRegenerateQrIfExpired();
    }, 1000);
}

function setQrLockWindow() {
    waQrState.qrDeadlineMs = Date.now() + 15000;
    startQrCountdown();
}

function setConnectingWindow() {
    waQrState.connectingDeadlineMs = Date.now() + 45000;
}

function renderQrCode(qrText) {
    var wrap = document.getElementById('wa-qr-canvas-wrap');
    var canvas = document.getElementById('wa-qr-canvas');
    if (!wrap || !canvas) {
        return;
    }

    canvas.innerHTML = '';

    if (window.QRCode) {
        new QRCode(canvas, {
            text: qrText,
            width: 260,
            height: 260,
            correctLevel: QRCode.CorrectLevel.M,
        });
        wrap.classList.remove('d-none');
    } else {
        wrap.classList.add('d-none');
        showQrAlert('Library QR belum termuat. Silakan refresh halaman.', 'danger');
    }
}

function openQrModal(deviceId, deviceName) {
    waQrState.deviceId = Number(deviceId);
    waQrState.deviceName = String(deviceName || 'Device');
    waQrState.currentQr = null;
    waQrState.qrDeadlineMs = 0;
    waQrState.regenerating = false;
    waQrState.connectingDeadlineMs = 0;

    var deviceNameEl = document.getElementById('wa-qr-device-name');
    if (deviceNameEl) {
        deviceNameEl.textContent = waQrState.deviceName;
    }

    clearQrRenderer();
    showQrAlert('Mengecek status sesi device...', 'info');

    if (window.jQuery && window.jQuery('#wa-qr-modal').modal) {
        window.jQuery('#wa-qr-modal').modal('show');
    }

    refreshDeviceQrStatus('status');
}

function refreshDeviceQrStatus(action, silent) {
    if (!waQrState.deviceId) {
        return Promise.resolve();
    }

    var currentAction = action || 'status';
    if (!silent) {
        showQrAlert(currentAction === 'restart' ? 'Meminta QR baru...' : 'Memuat status sesi...', 'info');
    }

    return fetch('{{ route("tenant-settings.wa-session-control", ["action" => "__ACTION__"]) }}'.replace('__ACTION__', currentAction), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify(Object.assign(getTenantPayload(), { device_id: waQrState.deviceId })),
    })
    .then(function (response) {
        return response.json();
    })
    .then(function (data) {
        if (!data.success) {
            throw new Error(data.message || 'Gagal memuat status QR device.');
        }

        var payload = data.data || {};
        var status = String(payload.status || '').toLowerCase();
        var qr = payload.qr || null;
        var updatedAt = payload.updated_at || null;
        var meta = document.getElementById('wa-qr-meta');

        if (meta) {
            var statusLabel = status !== '' ? ('Status: ' + status) : 'Status: -';
            meta.textContent = updatedAt ? (statusLabel + ' | Update: ' + updatedAt) : statusLabel;
        }

        if (status === 'connected') {
            stopQrPolling();
            stopQrCountdown();
            showQrAlert('Device berhasil terhubung. Menutup popup...', 'success');
            setTimeout(function () {
                if (window.jQuery && window.jQuery('#wa-qr-modal').modal) {
                    window.jQuery('#wa-qr-modal').modal('hide');
                }
                setWaDeviceResult('Device "' + waQrState.deviceName + '" berhasil terhubung.', 'success');
                loadWaDevices();
            }, 600);

            return;
        }

        if (qr) {
            var incomingQr = String(qr);
            var isFirstQr = !waQrState.currentQr;
            var isDifferentQr = waQrState.currentQr && waQrState.currentQr !== incomingQr;
            var lockActive = Date.now() < waQrState.qrDeadlineMs;

            if (isFirstQr || (!lockActive && isDifferentQr)) {
                waQrState.currentQr = incomingQr;
                renderQrCode(incomingQr);
                setQrLockWindow();
                waQrState.connectingDeadlineMs = 0;
            }

            if (isDifferentQr && lockActive) {
                showQrAlert('QR sedang dikunci 15 detik untuk proses scan. QR baru akan dibuat otomatis jika waktu habis.', 'info');
            } else {
                showQrAlert('QR siap dipindai. Buka WhatsApp > Perangkat Tertaut > Tautkan perangkat.', 'success');
            }

            startQrPolling();

            return;
        }

        if (status === 'connecting') {
            stopQrCountdown();
            waQrState.qrDeadlineMs = 0;
            if (waQrState.connectingDeadlineMs <= 0) {
                setConnectingWindow();
            }

            showQrAlert('QR sudah terbaca. Menunggu proses login WhatsApp selesai...', 'info');

            if (Date.now() > waQrState.connectingDeadlineMs && !waQrState.regenerating) {
                waQrState.regenerating = true;
                showQrAlert('Proses login terlalu lama. Sistem mencoba generate QR baru...', 'warning');
                refreshDeviceQrStatus('restart', true).finally(function () {
                    waQrState.regenerating = false;
                    waQrState.connectingDeadlineMs = 0;
                });
            }

            startQrPolling();

            return;
        }

        if (waQrState.currentQr && Date.now() < waQrState.qrDeadlineMs) {
            showQrAlert('Menunggu hasil scan dari QR aktif...', 'info');

            return;
        }

        if (currentAction === 'restart') {
            showQrAlert('Sesi direstart. Menunggu QR muncul...', 'info');
            startQrPolling();

            return;
        }

        clearQrRenderer();
        showQrAlert('QR belum tersedia. Klik "Generate QR Baru" untuk memunculkan QR.', 'warning');
    })
    .catch(function (error) {
        showQrAlert(error.message, 'danger');
    });
}

function controlWaService(action, silent) {
    var statusEl = document.getElementById('wa-service-status');
    var resultEl = document.getElementById('wa-service-result');

    if (!statusEl || !resultEl) {
        return;
    }

    if (!silent) {
        resultEl.innerHTML = '<div class="alert alert-info py-1 px-2 mb-0"><i class="fas fa-spinner fa-spin mr-1"></i>Memproses...</div>';
    }

    fetch('{{ route("tenant-settings.wa-service-control", ["action" => "__ACTION__"]) }}'.replace('__ACTION__', action), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify(getTenantPayload())
    })
    .then(r => r.json())
    .then(data => {
        var ok = !!data.success;
        var cls = ok ? 'success' : 'danger';
        resultEl.innerHTML = '<div class="alert alert-' + cls + ' py-1 px-2 mb-0">' + escapeHtml(data.message || (ok ? 'Berhasil' : 'Gagal')) + '</div>';
        if (data.data) {
            var running = data.data.running ? 'RUNNING' : 'STOPPED';
            statusEl.innerHTML = 'Status: <strong>' + running + '</strong>' +
                (data.data.pm2_pid ? ' | PID: ' + escapeHtml(String(data.data.pm2_pid)) : '') +
                (data.data.url ? ' | URL: ' + escapeHtml(String(data.data.url)) : '');
        }
    })
    .catch(error => {
        resultEl.innerHTML = '<div class="alert alert-danger py-1 px-2 mb-0">' + escapeHtml(error.message) + '</div>';
    });
}

function restartWaService() {
    controlWaService('restart', false);
}

document.addEventListener('DOMContentLoaded', function () {
    setupWaWizard();

    var deviceForm = document.getElementById('wa-device-form');
    if (deviceForm) {
        deviceForm.addEventListener('submit', submitWaDeviceForm);
        loadWaDevices().then(function () {
            startDeviceStatusAutoRefresh();
        });
    }

    if ('{{ $activeTab }}' === 'devices') {
        var deviceCard = document.getElementById('wa-device-management-card');
        if (deviceCard) {
            deviceCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    if (window.jQuery) {
        window.jQuery('#wa-qr-modal').on('hidden.bs.modal', function () {
            stopQrPolling();
            stopQrCountdown();
            waQrState.deviceId = null;
            waQrState.deviceName = '';
            waQrState.currentQr = null;
            waQrState.qrDeadlineMs = 0;
            waQrState.regenerating = false;
            waQrState.connectingDeadlineMs = 0;
            clearQrRenderer();
            var qrAlert = document.getElementById('wa-qr-alert');
            if (qrAlert) {
                qrAlert.innerHTML = '';
            }
        });
    }

    window.addEventListener('beforeunload', function () {
        stopDeviceStatusAutoRefresh();
    });

    controlWaService('status', true);
});
</script>
@endpush
