@extends('layouts.admin')

@section('title', 'Bantuan: Panduan Per Role')

@section('content')
@php
    $currentUser = auth()->user();
    $currentRole = $currentUser?->isSuperAdmin() ? 'super_admin' : (string) ($currentUser?->role ?? 'guest');
    $roleLabels = [
        'super_admin' => 'Super Admin',
        'administrator' => 'Admin',
        'it_support' => 'IT Support',
        'noc' => 'NOC',
        'keuangan' => 'Keuangan',
        'teknisi' => 'Teknisi',
    ];
@endphp

<div class="mb-3">
    <a href="{{ route('help.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left"></i> Pusat Bantuan
    </a>
</div>

<div class="card">
    <div class="card-header bg-primary text-white">
        <h4 class="card-title mb-0"><i class="fas fa-user-tag mr-2"></i>Panduan Per Role</h4>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <strong>Role Anda saat ini:</strong>
            <span class="badge badge-light border ml-1">{{ $roleLabels[$currentRole] ?? strtoupper(str_replace('_', ' ', $currentRole)) }}</span>
            <span class="ml-2 text-muted">Gunakan panduan ini sebagai standar operasional tiap role.</span>
        </div>

        <h5 class="border-bottom pb-2"><i class="fas fa-table mr-1"></i>Matriks Akses Fitur</h5>
        <div class="table-responsive mb-4">
            <table class="table table-sm table-bordered">
                <thead class="thead-light">
                    <tr>
                        <th>Menu / Fitur</th>
                        <th>Super Admin</th>
                        <th>Admin</th>
                        <th>IT Support</th>
                        <th>NOC</th>
                        <th>Keuangan</th>
                        <th>Teknisi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Dashboard</td>
                        <td>Semua tenant</td>
                        <td>Penuh</td>
                        <td>Penuh</td>
                        <td>Penuh</td>
                        <td>Terbatas</td>
                        <td>Terbatas</td>
                    </tr>
                    <tr>
                        <td>Session User (PPPoE/Hotspot)</td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                    </tr>
                    <tr>
                        <td>List Pelanggan (PPPoE/Hotspot/Voucher)</td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td>Baca + tagihan</td>
                        <td>Operasional lapangan</td>
                    </tr>
                    <tr>
                        <td>Router (NAS)</td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td>Lihat status</td>
                        <td>Lihat status</td>
                    </tr>
                    <tr>
                        <td>Monitoring OLT</td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-times text-danger"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-times text-danger"></i></td>
                        <td>Polling Sekarang</td>
                    </tr>
                    <tr>
                        <td>Profil Paket</td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-times text-danger"></i></td>
                        <td>Terbatas (tanpa kelola)</td>
                    </tr>
                    <tr>
                        <td>Data Tagihan / Invoice</td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td>Tanpa hapus + tanpa nota</td>
                    </tr>
                    <tr>
                        <td>Rekonsiliasi Nota</td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-times text-danger"></i></td>
                        <td><i class="fas fa-times text-danger"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                    </tr>
                    <tr>
                        <td>Data Keuangan</td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-times text-danger"></i></td>
                        <td><i class="fas fa-times text-danger"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-times text-danger"></i></td>
                    </tr>
                    <tr>
                        <td>Tool Sistem</td>
                        <td>Penuh (termasuk reset)</td>
                        <td>Import/Export/Usage</td>
                        <td><i class="fas fa-times text-danger"></i></td>
                        <td><i class="fas fa-times text-danger"></i></td>
                        <td><i class="fas fa-times text-danger"></i></td>
                        <td><i class="fas fa-times text-danger"></i></td>
                    </tr>
                    <tr>
                        <td>Log Aplikasi</td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-times text-danger"></i></td>
                    </tr>
                    <tr>
                        <td>Pengaturan Tenant / WA Gateway / FreeRADIUS / WG</td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td>Lihat sesuai kebutuhan</td>
                        <td><i class="fas fa-times text-danger"></i></td>
                    </tr>
                    <tr>
                        <td>Super Admin Dashboard + Kelola Tenant</td>
                        <td><i class="fas fa-check text-success"></i></td>
                        <td><i class="fas fa-times text-danger"></i></td>
                        <td><i class="fas fa-times text-danger"></i></td>
                        <td><i class="fas fa-times text-danger"></i></td>
                        <td><i class="fas fa-times text-danger"></i></td>
                        <td><i class="fas fa-times text-danger"></i></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <h5 class="border-bottom pb-2"><i class="fas fa-clipboard-check mr-1"></i>Standar Kerja Tiap Role</h5>
        <div class="row">
            <div class="col-lg-6 mb-3">
                <div class="card border-warning h-100">
                    <div class="card-header bg-warning py-2"><strong>Super Admin</strong></div>
                    <div class="card-body">
                        <ol class="mb-0">
                            <li>Kelola tenant baru, pilih metode langganan bulanan atau lisensi tahunan.</li>
                            <li>Pastikan tenant kategori adalah akun <code>role = administrator</code>.</li>
                            <li>Atur limit lisensi tenant (Mikrotik/PPP user) sesuai kontrak.</li>
                            <li>Pantau pendapatan global dan status pembayaran langganan tenant.</li>
                            <li>Gunakan tool reset/backup hanya untuk kondisi insiden terkontrol.</li>
                        </ol>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 mb-3">
                <div class="card border-primary h-100">
                    <div class="card-header bg-primary text-white py-2"><strong>Admin Tenant</strong></div>
                    <div class="card-body">
                        <ol class="mb-0">
                            <li>Setup Router (NAS), profil paket, dan data pelanggan.</li>
                            <li>Pantau dashboard, session user, dan Monitoring OLT setiap hari.</li>
                            <li>Kelola invoice, konfirmasi pembayaran, dan isolir jatuh tempo.</li>
                            <li>Atur WA Gateway lewat wizard onboarding (koneksi, device, scan QR), template notifikasi, dan pengaturan tenant.</li>
                            <li>Review laporan keuangan harian/periode sebagai kontrol bisnis.</li>
                        </ol>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 mb-3">
                <div class="card border-info h-100">
                    <div class="card-header bg-info text-white py-2"><strong>NOC &amp; IT Support</strong></div>
                    <div class="card-body">
                        <ol class="mb-0">
                            <li>Fokus pada stabilitas jaringan, autentikasi, session, dan log.</li>
                            <li>NOC memantau OLT dan kualitas optik; lakukan polling saat alarm muncul.</li>
                            <li>IT Support menangani sinkronisasi RADIUS, router API, dan issue user massal.</li>
                            <li>Gunakan halaman Troubleshooting sebagai alur diagnosis berurutan.</li>
                        </ol>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 mb-3">
                <div class="card border-success h-100">
                    <div class="card-header bg-success text-white py-2"><strong>Keuangan &amp; Teknisi</strong></div>
                    <div class="card-body">
                        <ol class="mb-0">
                            <li>Keuangan fokus ke invoice, konfirmasi transfer, pengeluaran, laba rugi, BHP/USO.</li>
                            <li>Teknisi fokus ke eksekusi lapangan: monitoring OLT, penagihan, rekonsiliasi nota.</li>
                            <li>Pada role teknisi, aksi sensitif seperti hapus invoice/nota tidak ditampilkan.</li>
                            <li>Validasi setoran tunai harian dan cocokkan dengan Rekonsiliasi Nota.</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <div class="alert alert-light border mb-0">
            <strong>Catatan:</strong> Jika ada menu yang tidak muncul, cek role akun dan status modul tenant terlebih dahulu (misalnya modul hotspot atau hak akses role).
        </div>
    </div>
</div>
@endsection
