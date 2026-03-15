@extends('portal.layout')

@section('title', 'Dashboard')

@php $tenantSettings = $pppUser->owner?->tenantSettings; @endphp

@section('content')
<h5 class="mb-3">Selamat datang, <strong>{{ $pppUser->customer_name ?? $pppUser->username }}</strong> 👋</h5>

<div class="row">
    {{-- Info Akun --}}
    <div class="col-md-6 mb-3">
        <div class="card h-100">
            <div class="card-header bg-dark text-white"><i class="fas fa-user"></i> Informasi Akun</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tr>
                        <th class="pl-3" style="width:130px">Status</th>
                        <td>
                            @php
                                $statusBadge = match($pppUser->status_akun) {
                                    'enable' => 'badge-success',
                                    'disable' => 'badge-secondary',
                                    'isolir' => 'badge-danger',
                                    default => 'badge-light',
                                };
                                $statusLabel = match($pppUser->status_akun) {
                                    'enable' => 'Aktif',
                                    'disable' => 'Nonaktif',
                                    'isolir' => 'Diblokir',
                                    default => $pppUser->status_akun,
                                };
                            @endphp
                            <span class="badge {{ $statusBadge }}">{{ $statusLabel }}</span>
                        </td>
                    </tr>
                    <tr><th class="pl-3">ID Pelanggan</th><td>{{ $pppUser->customer_id ?? '-' }}</td></tr>
                    <tr><th class="pl-3">Username</th><td>{{ $pppUser->username }}</td></tr>
                    <tr><th class="pl-3">Paket</th><td>{{ $pppUser->profile?->name ?? '-' }}</td></tr>
                    <tr><th class="pl-3">Nomor HP</th><td>{{ $pppUser->nomor_hp ?? '-' }}</td></tr>
                    <tr><th class="pl-3">Jatuh Tempo</th><td>{{ $pppUser->jatuh_tempo ? \Carbon\Carbon::parse($pppUser->jatuh_tempo)->format('d') : '-' }} setiap bulan</td></tr>
                </table>
            </div>
        </div>
    </div>

    {{-- Tagihan Terakhir --}}
    <div class="col-md-6 mb-3">
        <div class="card h-100">
            <div class="card-header bg-dark text-white"><i class="fas fa-file-invoice-dollar"></i> Tagihan Terakhir</div>
            <div class="card-body">
                @if($latestInvoice)
                <table class="table table-sm mb-3">
                    <tr><th style="width:130px">No. Invoice</th><td>{{ $latestInvoice->invoice_number }}</td></tr>
                    <tr><th>Total</th><td><strong>Rp {{ number_format($latestInvoice->total, 0, ',', '.') }}</strong></td></tr>
                    <tr><th>Jatuh Tempo</th><td>{{ $latestInvoice->due_date?->format('d/m/Y') }}</td></tr>
                    <tr><th>Status</th><td>
                        @php
                            $isPaid = $latestInvoice->status === 'lunas' || $latestInvoice->status === 'sudah_bayar' || $latestInvoice->paid_at;
                        @endphp
                        @if($isPaid)
                        <span class="badge badge-success">LUNAS</span>
                        @else
                        <span class="badge badge-danger">BELUM BAYAR</span>
                        @endif
                    </td></tr>
                </table>
                @if(!$isPaid && $latestInvoice->payment_token)
                <a href="{{ route('customer.invoice', $latestInvoice->payment_token) }}"
                   class="btn btn-primary btn-block" target="_blank">
                    <i class="fas fa-credit-card"></i> Bayar Sekarang
                </a>
                @endif
                @else
                <p class="text-muted text-center py-3">Belum ada tagihan.</p>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Ganti WiFi --}}
@if($pppUser->cpeDevice)
<div class="card mb-3">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center" style="cursor:pointer;" data-toggle="collapse" data-target="#wifiForm">
        <span><i class="fas fa-wifi"></i> Ganti Nama & Password WiFi</span>
        <i class="fas fa-chevron-down"></i>
    </div>
    <div id="wifiForm" class="collapse">
        <div class="card-body">
            <div id="wifiAlert"></div>
            @php
                $cachedSsid = $pppUser->cpeDevice->cached_params['wifi_ssid'] ?? null;
            @endphp
            <div class="form-group">
                <label>Nama WiFi (SSID)</label>
                <input type="text" id="wifiSsid" class="form-control" maxlength="32"
                       placeholder="Nama WiFi baru"
                       value="{{ $cachedSsid ?? '' }}">
                <small class="text-muted">Maksimal 32 karakter</small>
            </div>
            <div class="form-group">
                <label>Password WiFi</label>
                <div class="input-group">
                    <input type="password" id="wifiPassword" class="form-control" maxlength="63"
                           placeholder="Minimal 8 karakter">
                    <div class="input-group-append">
                        <button type="button" class="btn btn-outline-secondary btn-toggle-pw" tabindex="-1">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                <small class="text-muted">Minimal 8, maksimal 63 karakter</small>
            </div>
            <button id="btnSaveWifi" class="btn btn-primary">
                <i class="fas fa-save"></i> Simpan Pengaturan WiFi
            </button>
        </div>
    </div>
</div>
@endif

{{-- Buat Tiket Pengaduan --}}
<div class="card mb-3">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center" style="cursor:pointer;" data-toggle="collapse" data-target="#ticketForm">
        <span><i class="fas fa-headset"></i> Pengaduan / Bantuan</span>
        <i class="fas fa-chevron-down"></i>
    </div>
    <div id="ticketForm" class="collapse">
        <div class="card-body">
            <div id="ticketAlert"></div>
            <div class="form-group">
                <label>Tipe Pengaduan</label>
                <select id="ticketType" class="form-control">
                    <option value="complaint">Komplain</option>
                    <option value="troubleshoot">Internet Bermasalah</option>
                    <option value="installation">Instalasi</option>
                    <option value="other">Lainnya</option>
                </select>
            </div>
            <div class="form-group">
                <label>Subjek</label>
                <input type="text" id="ticketSubject" class="form-control" placeholder="Masalah yang dihadapi">
            </div>
            <div class="form-group">
                <label>Detail Keluhan</label>
                <textarea id="ticketMessage" class="form-control" rows="3" placeholder="Ceritakan masalah Anda..."></textarea>
            </div>
            <button id="btnSubmitTicket" class="btn btn-warning">
                <i class="fas fa-paper-plane"></i> Kirim Pengaduan
            </button>
        </div>
    </div>
</div>
@endsection

@push('js')
<script>
// Toggle password visibility
$(document).on('click', '.btn-toggle-pw', function() {
    const inp = $(this).closest('.input-group').find('input');
    const isPass = inp.attr('type') === 'password';
    inp.attr('type', isPass ? 'text' : 'password');
    $(this).find('i').toggleClass('fa-eye fa-eye-slash');
});

$('#btnSaveWifi').on('click', function() {
    const btn = $(this);
    const ssid = $('#wifiSsid').val().trim();
    const password = $('#wifiPassword').val();

    if (!ssid) {
        $('#wifiAlert').html('<div class="alert alert-warning">Nama WiFi tidak boleh kosong.</div>');
        return;
    }
    if (password.length < 8) {
        $('#wifiAlert').html('<div class="alert alert-warning">Password WiFi minimal 8 karakter.</div>');
        return;
    }

    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Menyimpan...');
    $('#wifiAlert').html('');

    $.post('{{ route("portal.wifi.update", $portalSlug) }}', {
        ssid: ssid,
        password: password,
        _token: '{{ csrf_token() }}'
    }, function(res) {
        if (res.success) {
            $('#wifiAlert').html('<div class="alert alert-success"><i class="fas fa-check-circle"></i> ' + res.message + '</div>');
            $('#wifiPassword').val('');
        }
    }).fail(function(xhr) {
        const res = xhr.responseJSON || {};
        let msg = res.message || 'Gagal mengubah WiFi.';
        let extra = '';
        if (res.no_genieacs || res.no_device) {
            extra = ' <a href="#ticketForm" data-toggle="collapse" data-target="#ticketForm" class="alert-link">Buat Tiket Bantuan</a>';
        }
        $('#wifiAlert').html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> ' + msg + extra + '</div>');
    }).always(function() {
        btn.prop('disabled', false).html('<i class="fas fa-save"></i> Simpan Pengaturan WiFi');
    });
});

$('#btnSubmitTicket').on('click', function() {
    const btn = $(this);
    btn.prop('disabled', true);
    $.post('{{ route("portal.tickets.store", $portalSlug) }}', {
        type: $('#ticketType').val(),
        subject: $('#ticketSubject').val(),
        message: $('#ticketMessage').val(),
        _token: '{{ csrf_token() }}'
    }, function(res) {
        if (res.success) {
            $('#ticketAlert').html('<div class="alert alert-success">Pengaduan berhasil dikirim. Tim kami akan segera menangani.</div>');
            $('#ticketSubject, #ticketMessage').val('');
        }
    }).fail(function(xhr) {
        const errors = xhr.responseJSON?.errors;
        let msg = xhr.responseJSON?.message || 'Gagal mengirim pengaduan.';
        if (errors) msg = Object.values(errors).flat().join('<br>');
        $('#ticketAlert').html(`<div class="alert alert-danger">${msg}</div>`);
    }).always(function() { btn.prop('disabled', false); });
});
</script>
@endpush
