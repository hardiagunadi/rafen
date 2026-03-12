@extends('layouts.admin')

@section('title', 'Bantuan: WhatsApp Gateway')

@section('content')
<div class="mb-3">
    <a href="{{ route('help.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left"></i> Pusat Bantuan
    </a>
</div>

<div class="card">
    <div class="card-header bg-success">
        <h4 class="card-title mb-0"><i class="fab fa-whatsapp mr-2"></i>WhatsApp Gateway — Panduan Praktis</h4>
    </div>
    <div class="card-body">
        <div class="alert alert-light border mb-4">
            <strong><i class="fas fa-list mr-1"></i>Daftar Isi</strong>
            <ol class="mb-0 mt-2">
                <li><a href="#menu-baru">Struktur Menu WhatsApp Terbaru</a></li>
                <li><a href="#wizard-onboarding">Wizard Onboarding (5 Langkah)</a></li>
                <li><a href="#manajemen-device">Manajemen Device &amp; Status Koneksi</a></li>
                <li><a href="#scan-qr">Scan QR via Modal Popup</a></li>
                <li><a href="#optimasi-blast">Optimasi WA Blast Multi Device</a></li>
                <li><a href="#troubleshoot-ringkas">Troubleshoot Ringkas</a></li>
            </ol>
        </div>

        <h5 id="menu-baru" class="border-bottom pb-2"><i class="fas fa-sitemap mr-1"></i>1. Struktur Menu WhatsApp Terbaru</h5>
        <p>Masuk ke <strong>Pengaturan → WhatsApp</strong>, lalu pilih tab:</p>
        <ul>
            <li><strong>Gateway &amp; Template</strong>: koneksi gateway, toggle notifikasi otomatis, anti-spam, dan template pesan.</li>
            <li><strong>Manajemen Device</strong>: tambah device, scan QR, set default, cek/restart sesi, hapus device.</li>
        </ul>

        <h5 id="wizard-onboarding" class="border-bottom pb-2 mt-4"><i class="fas fa-route mr-1"></i>2. Wizard Onboarding (5 Langkah)</h5>
        <p>Di tab <strong>Gateway &amp; Template</strong>, ikuti wizard agar setup tenant tidak lompat-lompat:</p>
        <ol>
            <li>Validasi koneksi gateway.</li>
            <li>Tambah device WA tenant.</li>
            <li>Scan QR pada device.</li>
            <li>Aktifkan otomasi notifikasi &amp; blast.</li>
            <li>Uji template dan simpan.</li>
        </ol>

        <h5 id="manajemen-device" class="border-bottom pb-2 mt-4"><i class="fas fa-mobile-alt mr-1"></i>3. Manajemen Device &amp; Status Koneksi</h5>
        <p>Setiap baris device menampilkan status berikut:</p>
        <table class="table table-sm table-bordered mb-3">
            <thead class="thead-light">
                <tr>
                    <th>Status</th>
                    <th>Arti</th>
                </tr>
            </thead>
            <tbody>
                <tr><td><span class="badge badge-success">Connected</span></td><td>Device sudah terhubung dan siap kirim.</td></tr>
                <tr><td><span class="badge badge-warning">Belum Scan</span></td><td>Device belum pernah scan / sesi belum aktif.</td></tr>
                <tr><td><span class="badge badge-info">Proses Login</span></td><td>QR sudah dipindai, sistem sedang sinkronisasi ke WhatsApp.</td></tr>
                <tr><td><span class="badge badge-danger">Disconnected</span></td><td>Sesi putus, perlu scan ulang atau restart sesi.</td></tr>
            </tbody>
        </table>
        <p class="text-muted small">Status koneksi auto-refresh berkala saat tab device dibuka.</p>

        <h5 id="scan-qr" class="border-bottom pb-2 mt-4"><i class="fas fa-qrcode mr-1"></i>4. Scan QR via Modal Popup</h5>
        <ol>
            <li>Klik <strong>Scan QR</strong> pada device.</li>
            <li>Pindai QR dari WhatsApp (Perangkat Tertaut).</li>
            <li>Tunggu status berubah ke <strong>Connected</strong>; modal akan menutup otomatis.</li>
        </ol>
        <p class="text-muted small mb-0">Jika QR habis waktu, sistem akan generate ulang otomatis.</p>

        <h5 id="optimasi-blast" class="border-bottom pb-2 mt-4"><i class="fas fa-paper-plane mr-1"></i>5. Optimasi WA Blast Multi Device</h5>
        <ul>
            <li><strong>Distribusi Multi Device Aktif</strong>: kirim bergiliran (round-robin) ke device yang terhubung.</li>
            <li><strong>Failover</strong>: jika satu sesi gagal, sistem mencoba sesi lain.</li>
            <li><strong>Variasi Pesan Natural Profesional</strong>: sapaan/penutup ringan agar tidak terlalu seragam.</li>
            <li><strong>Delay Blast Min/Max</strong>: jeda acak antar pesan untuk mengurangi risiko blokir.</li>
        </ul>

        <h5 id="troubleshoot-ringkas" class="border-bottom pb-2 mt-4"><i class="fas fa-tools mr-1"></i>6. Troubleshoot Ringkas</h5>
        <ul class="mb-0">
            <li>Jika stuck saat scan: restart sesi device lalu scan ulang.</li>
            <li>Jika status sering disconnected: cek stabilitas internet HP yang menautkan akun WA.</li>
            <li>Jika blast lambat: ini normal saat delay anti-spam/optimasi blast aktif.</li>
            <li>Jika menu terasa lambat: lakukan hard refresh browser dan pastikan build aset frontend terbaru.</li>
        </ul>
    </div>
</div>
@endsection
