@extends('layouts.admin')

@section('title', 'Pusat Bantuan')

@section('content')
<div class="row">
    <div class="col-12 mb-3">
        <div class="card card-primary card-outline">
            <div class="card-body text-center py-4">
                <i class="fas fa-life-ring fa-3x text-primary mb-3"></i>
                <h3 class="mb-1">Pusat Bantuan Rafen</h3>
                <p class="text-muted mb-0">Panduan lengkap penggunaan dan konfigurasi sistem manajemen ISP.</p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    {{-- FreeRADIUS --}}
    <div class="col-md-4 col-sm-6 mb-4">
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

    {{-- Hotspot --}}
    <div class="col-md-4 col-sm-6 mb-4">
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

    {{-- PPPoE --}}
    <div class="col-md-4 col-sm-6 mb-4">
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

    {{-- WireGuard --}}
    <div class="col-md-4 col-sm-6 mb-4">
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

    {{-- Voucher --}}
    <div class="col-md-4 col-sm-6 mb-4">
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

    {{-- Profil Paket --}}
    <div class="col-md-4 col-sm-6 mb-4">
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

    {{-- Invoice --}}
    <div class="col-md-4 col-sm-6 mb-4">
        <a href="{{ route('help.topic', 'invoice') }}" class="text-decoration-none">
            <div class="card card-outline card-warning h-100 help-card">
                <div class="card-body text-center py-4">
                    <i class="fas fa-file-invoice-dollar fa-2x text-warning mb-3"></i>
                    <h5 class="card-title mb-1">Tagihan (Invoice)</h5>
                    <p class="text-muted small mb-0">Generate invoice otomatis 14 hari, scheduler harian, perintah artisan, dan alur pembayaran.</p>
                </div>
                <div class="card-footer text-right py-2">
                    <small class="text-muted">Baca panduan <i class="fas fa-arrow-right"></i></small>
                </div>
            </div>
        </a>
    </div>

    {{-- Session --}}
    <div class="col-md-4 col-sm-6 mb-4">
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

    {{-- Troubleshoot --}}
    <div class="col-md-4 col-sm-6 mb-4">
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
</div>

<style>
.help-card { transition: transform .15s, box-shadow .15s; }
.help-card:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0,0,0,.12); }
</style>
@endsection
