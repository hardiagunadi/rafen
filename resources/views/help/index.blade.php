@extends('layouts.admin')

@section('title', 'Pusat Bantuan')

@section('content')
@php
    $currentUser = auth()->user();
    $normalizedRole = $currentUser?->isSuperAdmin() ? 'super_admin' : (string) ($currentUser?->role ?? 'guest');

    $roleLabels = [
        'super_admin' => 'SUPER ADMIN',
        'administrator' => 'ADMIN',
        'it_support' => 'IT SUPPORT',
        'noc' => 'NOC',
        'keuangan' => 'KEUANGAN',
        'teknisi' => 'TEKNISI',
    ];

    $quickAccessByRole = [
        'super_admin' => ['Dashboard Tenant', 'Kelola Tenant', 'Paket Langganan', 'Laporan Pendapatan', 'Semua Modul Tenant'],
        'administrator' => ['Dashboard', 'List Pelanggan', 'Router (NAS)', 'Tagihan', 'Data Keuangan', 'Pengaturan Tenant'],
        'it_support' => ['Dashboard', 'Session User', 'List Pelanggan', 'Router (NAS)', 'Profil Paket', 'Log Aplikasi'],
        'noc' => ['Dashboard', 'Session User', 'Router (NAS)', 'Monitoring OLT', 'Log Aplikasi', 'Troubleshooting'],
        'keuangan' => ['Data Tagihan', 'Konfirmasi Transfer', 'Data Keuangan', 'Rekonsiliasi Nota', 'Laporan Pendapatan Tenant'],
        'teknisi' => ['Session User', 'Monitoring OLT (Polling Sekarang)', 'Rekonsiliasi Nota', 'Penagihan Lapangan'],
    ];

    $currentRoleLabel = $roleLabels[$normalizedRole] ?? strtoupper(str_replace('_', ' ', $normalizedRole));
    $currentQuickAccess = $quickAccessByRole[$normalizedRole] ?? [];
@endphp

<div class="row">
    <div class="col-12 mb-3">
        <div class="card card-primary card-outline">
            <div class="card-body py-4">
                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between">
                    <div class="mb-3 mb-md-0">
                        <h3 class="mb-1"><i class="fas fa-life-ring text-primary mr-2"></i>Pusat Bantuan RAFEN</h3>
                        <p class="text-muted mb-0">Panduan rinci per menu, alur kerja operasional, dan ringkasan akses per role.</p>
                    </div>
                    <div class="w-100" style="max-width: 360px;">
                        <label for="help-topic-search" class="small text-muted mb-1 d-block">Cari topik bantuan</label>
                        <input type="search" id="help-topic-search" class="form-control" placeholder="Contoh: OLT, invoice, WA, keuangan">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 mb-3">
        <div class="card border-info">
            <div class="card-header bg-info text-white py-2">
                <strong><i class="fas fa-user-shield mr-1"></i>Ringkasan akses untuk role Anda: {{ $currentRoleLabel }}</strong>
            </div>
            <div class="card-body py-3">
                @if($currentQuickAccess === [])
                    <p class="mb-0 text-muted">Role ini belum memiliki ringkasan khusus. Buka menu <strong>Panduan Per Role</strong> untuk detail akses.</p>
                @else
                    <div class="d-flex flex-wrap" style="gap: .45rem;">
                        @foreach($currentQuickAccess as $access)
                            <span class="badge badge-pill badge-light border px-3 py-2">{{ $access }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-4 col-sm-6 mb-4 help-topic-card" data-help-topic data-help-keywords="panduan role akses super admin admin noc keuangan teknisi it support izin menu">
        <a href="{{ route('help.topic', 'panduan-role') }}" class="text-decoration-none">
            <div class="card card-outline card-primary h-100 help-card">
                <div class="card-body text-center py-4">
                    <i class="fas fa-user-tag fa-2x text-primary mb-3"></i>
                    <h5 class="card-title mb-1">Panduan Per Role</h5>
                    <p class="text-muted small mb-0">Penjelasan hak akses dan alur kerja untuk setiap role pengguna di RAFEN.</p>
                </div>
                <div class="card-footer text-right py-2">
                    <small class="text-muted">Baca panduan <i class="fas fa-arrow-right"></i></small>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-4 col-sm-6 mb-4 help-topic-card" data-help-topic data-help-keywords="fitur operasional menu dashboard pelanggan router olt invoice keuangan pengaturan">
        <a href="{{ route('help.topic', 'fitur-operasional') }}" class="text-decoration-none">
            <div class="card card-outline card-success h-100 help-card">
                <div class="card-body text-center py-4">
                    <i class="fas fa-project-diagram fa-2x text-success mb-3"></i>
                    <h5 class="card-title mb-1">Peta Fitur Operasional</h5>
                    <p class="text-muted small mb-0">Daftar semua fitur utama RAFEN, fungsi bisnis, dan langkah penggunaan praktis.</p>
                </div>
                <div class="card-footer text-right py-2">
                    <small class="text-muted">Baca panduan <i class="fas fa-arrow-right"></i></small>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-4 col-sm-6 mb-4 help-topic-card" data-help-topic data-help-keywords="faq pertanyaan umum wa whatsapp wizard onboarding qr device blast multi device timeout snmp isolir invoice pembayaran session">
        <a href="{{ route('help.topic', 'faq') }}" class="text-decoration-none">
            <div class="card card-outline card-warning h-100 help-card">
                <div class="card-body text-center py-4">
                    <i class="fas fa-question-circle fa-2x text-warning mb-3"></i>
                    <h5 class="card-title mb-1">FAQ Operasional</h5>
                    <p class="text-muted small mb-0">Jawaban cepat untuk pertanyaan yang paling sering ditanyakan tim operasional.</p>
                </div>
                <div class="card-footer text-right py-2">
                    <small class="text-muted">Baca FAQ <i class="fas fa-arrow-right"></i></small>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-4 col-sm-6 mb-4 help-topic-card" data-help-topic data-help-keywords="whatsapp gateway wa wizard onboarding device scan qr blast multi device template anti spam">
        <a href="{{ route('help.topic', 'whatsapp-gateway') }}" class="text-decoration-none">
            <div class="card card-outline card-success h-100 help-card">
                <div class="card-body text-center py-4">
                    <i class="fab fa-whatsapp fa-2x text-success mb-3"></i>
                    <h5 class="card-title mb-1">WhatsApp Gateway</h5>
                    <p class="text-muted small mb-0">Panduan khusus setup wizard, manajemen device, scan QR, dan optimasi WA Blast.</p>
                </div>
                <div class="card-footer text-right py-2">
                    <small class="text-muted">Baca panduan <i class="fas fa-arrow-right"></i></small>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-4 col-sm-6 mb-4 help-topic-card" data-help-topic data-help-keywords="freeradius radius sql clients nas radcheck radreply simultaneous use">
        <a href="{{ route('help.topic', 'freeradius') }}" class="text-decoration-none">
            <div class="card card-outline card-danger h-100 help-card">
                <div class="card-body text-center py-4">
                    <i class="fas fa-broadcast-tower fa-2x text-danger mb-3"></i>
                    <h5 class="card-title mb-1">FreeRADIUS</h5>
                    <p class="text-muted small mb-0">Konfigurasi SQL module, sinkronisasi klien, atribut radcheck &amp; radreply.</p>
                </div>
                <div class="card-footer text-right py-2">
                    <small class="text-muted">Baca panduan <i class="fas fa-arrow-right"></i></small>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-4 col-sm-6 mb-4 help-topic-card" data-help-topic data-help-keywords="hotspot profil user voucher shared users multi device radius">
        <a href="{{ route('help.topic', 'hotspot') }}" class="text-decoration-none">
            <div class="card card-outline card-success h-100 help-card">
                <div class="card-body text-center py-4">
                    <i class="fas fa-broadcast-tower fa-2x text-success mb-3"></i>
                    <h5 class="card-title mb-1">Hotspot</h5>
                    <p class="text-muted small mb-0">Profil hotspot, shared users (multi-device), voucher, dan sinkronisasi RADIUS.</p>
                </div>
                <div class="card-footer text-right py-2">
                    <small class="text-muted">Baca panduan <i class="fas fa-arrow-right"></i></small>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-4 col-sm-6 mb-4 help-topic-card" data-help-topic data-help-keywords="pppoe ppp user profil paket rate limit session">
        <a href="{{ route('help.topic', 'pppoe') }}" class="text-decoration-none">
            <div class="card card-outline card-info h-100 help-card">
                <div class="card-body text-center py-4">
                    <i class="fas fa-wifi fa-2x text-info mb-3"></i>
                    <h5 class="card-title mb-1">PPPoE</h5>
                    <p class="text-muted small mb-0">User PPP, sinkronisasi radcheck, rate limit, dan session aktif.</p>
                </div>
                <div class="card-footer text-right py-2">
                    <small class="text-muted">Baca panduan <i class="fas fa-arrow-right"></i></small>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-4 col-sm-6 mb-4 help-topic-card" data-help-topic data-help-keywords="wireguard vpn peer tunnel mikrotik nas">
        <a href="{{ route('help.topic', 'wireguard') }}" class="text-decoration-none">
            <div class="card card-outline card-warning h-100 help-card">
                <div class="card-body text-center py-4">
                    <i class="fas fa-shield-alt fa-2x text-warning mb-3"></i>
                    <h5 class="card-title mb-1">WireGuard VPN</h5>
                    <p class="text-muted small mb-0">Konfigurasi VPN server, peer MikroTik, dan tunneling ke router.</p>
                </div>
                <div class="card-footer text-right py-2">
                    <small class="text-muted">Baca panduan <i class="fas fa-arrow-right"></i></small>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-4 col-sm-6 mb-4 help-topic-card" data-help-topic data-help-keywords="voucher batch print status expired sinkronisasi radius">
        <a href="{{ route('help.topic', 'voucher') }}" class="text-decoration-none">
            <div class="card card-outline card-secondary h-100 help-card">
                <div class="card-body text-center py-4">
                    <i class="fas fa-ticket-alt fa-2x text-secondary mb-3"></i>
                    <h5 class="card-title mb-1">Voucher</h5>
                    <p class="text-muted small mb-0">Pembuatan batch voucher, cetak, penggunaan, dan masa berlaku.</p>
                </div>
                <div class="card-footer text-right py-2">
                    <small class="text-muted">Baca panduan <i class="fas fa-arrow-right"></i></small>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-4 col-sm-6 mb-4 help-topic-card" data-help-topic data-help-keywords="profil paket bandwidth profile group hotspot ppp pool">
        <a href="{{ route('help.topic', 'profil-paket') }}" class="text-decoration-none">
            <div class="card card-outline card-primary h-100 help-card">
                <div class="card-body text-center py-4">
                    <i class="fas fa-box fa-2x text-primary mb-3"></i>
                    <h5 class="card-title mb-1">Profil Paket</h5>
                    <p class="text-muted small mb-0">Bandwidth, profil group (IP pool), profil hotspot &amp; PPP.</p>
                </div>
                <div class="card-footer text-right py-2">
                    <small class="text-muted">Baca panduan <i class="fas fa-arrow-right"></i></small>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-4 col-sm-6 mb-4 help-topic-card" data-help-topic data-help-keywords="invoice tagihan due date pembayaran wa jatuh tempo isolir">
        <a href="{{ route('help.topic', 'invoice') }}" class="text-decoration-none">
            <div class="card card-outline card-warning h-100 help-card">
                <div class="card-body text-center py-4">
                    <i class="fas fa-file-invoice-dollar fa-2x text-warning mb-3"></i>
                    <h5 class="card-title mb-1">Tagihan (Invoice)</h5>
                    <p class="text-muted small mb-0">Generate invoice otomatis, alur pembayaran, dan mekanisme jatuh tempo.</p>
                </div>
                <div class="card-footer text-right py-2">
                    <small class="text-muted">Baca panduan <i class="fas fa-arrow-right"></i></small>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-4 col-sm-6 mb-4 help-topic-card" data-help-topic data-help-keywords="session pppoe hotspot monitoring refresh sinkronisasi router">
        <a href="{{ route('help.topic', 'session') }}" class="text-decoration-none">
            <div class="card card-outline card-info h-100 help-card">
                <div class="card-body text-center py-4">
                    <i class="fas fa-signal fa-2x text-info mb-3"></i>
                    <h5 class="card-title mb-1">Session User</h5>
                    <p class="text-muted small mb-0">Monitoring session aktif PPPoE &amp; Hotspot, auto-sync MikroTik.</p>
                </div>
                <div class="card-footer text-right py-2">
                    <small class="text-muted">Baca panduan <i class="fas fa-arrow-right"></i></small>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-4 col-sm-6 mb-4 help-topic-card" data-help-topic data-help-keywords="troubleshoot error gagal timeout radius sync export session log">
        <a href="{{ route('help.topic', 'troubleshoot') }}" class="text-decoration-none">
            <div class="card card-outline card-danger h-100 help-card">
                <div class="card-body text-center py-4">
                    <i class="fas fa-bug fa-2x text-danger mb-3"></i>
                    <h5 class="card-title mb-1">Troubleshooting</h5>
                    <p class="text-muted small mb-0">Masalah umum: login gagal, session kosong, RADIUS tidak merespon, dll.</p>
                </div>
                <div class="card-footer text-right py-2">
                    <small class="text-muted">Baca panduan <i class="fas fa-arrow-right"></i></small>
                </div>
            </div>
        </a>
    </div>

    <div class="col-12 d-none" id="help-empty-state">
        <div class="alert alert-warning">
            <i class="fas fa-search mr-1"></i>Tidak ada topik yang cocok. Coba kata kunci lain, misalnya <code>invoice</code>, <code>OLT</code>, <code>WA</code>, atau <code>keuangan</code>.
        </div>
    </div>
</div>

<style>
.help-card { transition: transform .15s, box-shadow .15s; }
.help-card:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0,0,0,.12); }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('help-topic-search');
    const topicCards = Array.from(document.querySelectorAll('.help-topic-card[data-help-topic]'));
    const emptyState = document.getElementById('help-empty-state');

    if (!searchInput || topicCards.length === 0) {
        return;
    }

    const normalize = (value) => (value || '').toString().toLowerCase();

    const applyFilter = () => {
        const keyword = normalize(searchInput.value.trim());
        let visibleCount = 0;

        topicCards.forEach((card) => {
            const haystack = normalize(card.dataset.helpKeywords);
            const visible = keyword === '' || haystack.includes(keyword);
            card.style.display = visible ? '' : 'none';
            if (visible) {
                visibleCount += 1;
            }
        });

        if (emptyState) {
            emptyState.classList.toggle('d-none', visibleCount !== 0);
        }
    };

    searchInput.addEventListener('input', applyFilter);
    applyFilter();
});
</script>
@endsection
