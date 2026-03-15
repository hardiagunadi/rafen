@extends('layouts.admin')

@section('title', 'Edit User PPP')

@section('content')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
    <style>
        #ppp-location-map {
            height: 320px;
            border: 1px solid #dee2e6;
            border-radius: 6px;
        }
    </style>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Edit Pelanggan — <span class="text-primary">{{ $pppUser->customer_name }}</span></h4>
            <div class="d-flex gap-2">
                <a href="{{ route('ppp-users.nota-aktivasi', $pppUser) }}" target="_blank" class="btn btn-sm btn-outline-success"><i class="fas fa-print"></i> Nota Aktivasi</a>
                <a href="{{ route('ppp-users.index') }}" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
            </div>
        </div>

        {{-- Main tabs: form + invoice/session/dialup --}}
        <div class="card-body p-0">
            <ul class="nav nav-tabs px-3 pt-2" id="mainTab" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="edit-tab" data-toggle="tab" href="#edit-pane" role="tab">Edit Pelanggan</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="invoice-tab" data-toggle="tab" href="#invoice-pane" role="tab"><i class="fas fa-file-invoice mr-1"></i>Invoice &amp; Session</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="dialup-tab" data-toggle="tab" href="#dialup-pane" role="tab"><i class="fas fa-history mr-1"></i>Riwayat Dialup</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="cpe-tab" data-toggle="tab" href="#cpe-pane" role="tab"><i class="fas fa-router mr-1"></i>Perangkat CPE</a>
                </li>
            </ul>

            <div class="tab-content" id="mainTabContent">
                {{-- TAB: EDIT FORM --}}
                <div class="tab-pane fade show active p-3" id="edit-pane" role="tabpanel">
        <form action="{{ route('ppp-users.update', $pppUser) }}" method="POST" id="form-edit-ppp">
            @csrf
            @method('PUT')
            <div>
                <ul class="nav nav-tabs" id="pppTab" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="paket-tab" data-toggle="tab" href="#paket" role="tab">Paket Langganan</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="info-tab" data-toggle="tab" href="#info" role="tab">Info Pelanggan</a>
                    </li>
                </ul>
                <div class="tab-content pt-3" id="pppTabContent">
                    <div class="tab-pane fade show active" id="paket" role="tabpanel" aria-labelledby="paket-tab">
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label>Owner Data</label>
                                <select name="owner_id" class="form-control @error('owner_id') is-invalid @enderror">
                                    @foreach($owners as $owner)
                                        <option value="{{ $owner->id }}" @selected(old('owner_id', $pppUser->owner_id) == $owner->id)>{{ $owner->name }}</option>
                                    @endforeach
                                </select>
                                @error('owner_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-group col-md-4">
                                <label>Paket Langganan (Profil PPP)</label>
                                <select name="ppp_profile_id" class="form-control @error('ppp_profile_id') is-invalid @enderror">
                                    <option value="">- pilih paket -</option>
                                    @foreach($profiles as $profile)
                                        <option value="{{ $profile->id }}" @selected(old('ppp_profile_id', $pppUser->ppp_profile_id) == $profile->id)>
                                            {{ $profile->name }} - Rp {{ number_format((float) $profile->harga_modal, 0, ',', '.') }} - {{ (int) $profile->masa_aktif }} {{ $profile->satuan }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('ppp_profile_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-group col-md-4">
                                <label>Status Registrasi</label>
                                <div>
                                    <label class="mr-3"><input type="radio" name="status_registrasi" value="aktif" @checked(old('status_registrasi', $pppUser->status_registrasi) === 'aktif')> AKTIF SEKARANG</label>
                                    <label><input type="radio" name="status_registrasi" value="on_process" @checked(old('status_registrasi', $pppUser->status_registrasi) === 'on_process')> ON PROCESS</label>
                                </div>
                                @error('status_registrasi')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Tipe Pembayaran</label>
                                <select name="tipe_pembayaran" class="form-control @error('tipe_pembayaran') is-invalid @enderror">
                                    <option value="prepaid" @selected(old('tipe_pembayaran', $pppUser->tipe_pembayaran) === 'prepaid')>PREPAID</option>
                                    <option value="postpaid" @selected(old('tipe_pembayaran', $pppUser->tipe_pembayaran) === 'postpaid')>POSTPAID</option>
                                </select>
                                @error('tipe_pembayaran')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-group col-md-6">
                                <label>Status Bayar</label>
                                <select name="status_bayar" class="form-control @error('status_bayar') is-invalid @enderror">
                                    <option value="sudah_bayar" @selected(old('status_bayar', $pppUser->status_bayar) === 'sudah_bayar')>SUDAH BAYAR</option>
                                    <option value="belum_bayar" @selected(old('status_bayar', $pppUser->status_bayar) === 'belum_bayar')>BELUM BAYAR</option>
                                </select>
                                @error('status_bayar')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Status Akun</label>
                                <select name="status_akun" class="form-control @error('status_akun') is-invalid @enderror">
                                    <option value="enable" @selected(old('status_akun', $pppUser->status_akun) === 'enable')>ENABLE</option>
                                    <option value="disable" @selected(old('status_akun', $pppUser->status_akun) === 'disable')>DISABLE</option>
                                </select>
                                @error('status_akun')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-group col-md-6">
                                <label>Tipe Service</label>
                                <select name="tipe_service" class="form-control @error('tipe_service') is-invalid @enderror">
                                    <option value="pppoe" @selected(old('tipe_service', $pppUser->tipe_service) === 'pppoe')>PPPoE</option>
                                    <option value="l2tp_pptp" @selected(old('tipe_service', $pppUser->tipe_service) === 'l2tp_pptp')>L2TP/PPTP</option>
                                    <option value="openvpn_sstp" @selected(old('tipe_service', $pppUser->tipe_service) === 'openvpn_sstp')>OPENVPN/SSTP</option>
                                </select>
                                @error('tipe_service')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="prorata_otomatis" name="prorata_otomatis" value="1" @checked(old('prorata_otomatis', $pppUser->prorata_otomatis))>
                                    <label class="form-check-label" for="prorata_otomatis">Prorata Otomatis</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="promo_aktif" name="promo_aktif" value="1" @checked(old('promo_aktif', $pppUser->promo_aktif))>
                                    <label class="form-check-label" for="promo_aktif">Promo (Aktifkan Promo)</label>
                                </div>
                            </div>
                            <div class="form-group col-md-6">
                                <label>Durasi Promo (bulan)</label>
                                <input type="number" name="durasi_promo_bulan" value="{{ old('durasi_promo_bulan', $pppUser->durasi_promo_bulan) }}" class="form-control @error('durasi_promo_bulan') is-invalid @enderror" min="0">
                                @error('durasi_promo_bulan')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Biaya Aktivasi</label>
                                <input type="text" id="biaya_instalasi_display" value="{{ number_format((float) old('biaya_instalasi', $pppUser->biaya_instalasi), 0, ',', '.') }}" class="form-control @error('biaya_instalasi') is-invalid @enderror" autocomplete="off" inputmode="numeric" oninput="formatBiayaAktivasi(this)" onblur="formatBiayaAktivasi(this)">
                                <input type="hidden" name="biaya_instalasi" id="biaya_instalasi_value" value="{{ old('biaya_instalasi', $pppUser->biaya_instalasi) }}">
                                @error('biaya_instalasi')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-group col-md-6">
                                <label>Ubah Jatuh Tempo (Optional)</label>
                                <input type="date" name="jatuh_tempo" value="{{ old('jatuh_tempo', optional($pppUser->jatuh_tempo)->format('Y-m-d')) }}" class="form-control @error('jatuh_tempo') is-invalid @enderror">
                                @error('jatuh_tempo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <small class="text-muted">Jika tidak diisi, prorata diabaikan. Tidak berlaku untuk paket unlimited atau masa aktif < 3 hari.</small>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Aksi Jatuh Tempo</label>
                                <select name="aksi_jatuh_tempo" class="form-control @error('aksi_jatuh_tempo') is-invalid @enderror">
                                    <option value="isolir" @selected(old('aksi_jatuh_tempo', $pppUser->aksi_jatuh_tempo) === 'isolir')>ISOLIR INTERNET</option>
                                    <option value="tetap_terhubung" @selected(old('aksi_jatuh_tempo', $pppUser->aksi_jatuh_tempo) === 'tetap_terhubung')>TETAP TERHUBUNG</option>
                                </select>
                                @error('aksi_jatuh_tempo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-group col-md-6">
                                <label>Tipe IP Address</label>
                                <select name="tipe_ip" class="form-control @error('tipe_ip') is-invalid @enderror" id="tipe-ip-select">
                                    <option value="dhcp" @selected(old('tipe_ip', $pppUser->tipe_ip) === 'dhcp')>DHCP</option>
                                    <option value="static" @selected(old('tipe_ip', $pppUser->tipe_ip) === 'static')>Static</option>
                                </select>
                                @error('tipe_ip')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div id="static-ip-section" style="display: none;">
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label>Group Profil</label>
                                    <select name="profile_group_id" class="form-control @error('profile_group_id') is-invalid @enderror">
                                        <option value="">- pilih -</option>
                                        @foreach($groups as $group)
                                            <option value="{{ $group->id }}" @selected(old('profile_group_id', $pppUser->profile_group_id) == $group->id)>{{ $group->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('profile_group_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="form-group col-md-6">
                                    <label>IP Address</label>
                                    <input type="text" name="ip_static" value="{{ old('ip_static', $pppUser->ip_static) }}" class="form-control @error('ip_static') is-invalid @enderror" placeholder="xxx.xxx.xxx.xxx">
                                    @error('ip_static')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="info" role="tabpanel" aria-labelledby="info-tab">
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label>ODP Master (Optional)</label>
                                <select name="odp_id" class="form-control @error('odp_id') is-invalid @enderror">
                                    <option value="">- pilih ODP -</option>
                                    @foreach($odps as $odp)
                                        <option value="{{ $odp->id }}" @selected(old('odp_id', $pppUser->odp_id) == $odp->id)>
                                            {{ $odp->code }} - {{ $odp->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('odp_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-group col-md-4">
                                <label>ODP | POP (Optional)</label>
                                <input type="text" name="odp_pop" value="{{ old('odp_pop', $pppUser->odp_pop) }}" class="form-control @error('odp_pop') is-invalid @enderror">
                                @error('odp_pop')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-group col-md-4">
                                <label>ID Pelanggan</label>
                                <input type="text" name="customer_id" value="{{ old('customer_id', $pppUser->customer_id) }}" class="form-control @error('customer_id') is-invalid @enderror">
                                @error('customer_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Nama</label>
                                <input type="text" name="customer_name" value="{{ old('customer_name', $pppUser->customer_name) }}" class="form-control @error('customer_name') is-invalid @enderror">
                                @error('customer_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-group col-md-6">
                                <label>No. NIK</label>
                                <input type="text" name="nik" value="{{ old('nik', $pppUser->nik) }}" class="form-control @error('nik') is-invalid @enderror">
                                @error('nik')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Nomor HP</label>
                                <input type="text" name="nomor_hp" value="{{ old('nomor_hp', $pppUser->nomor_hp) }}" class="form-control @error('nomor_hp') is-invalid @enderror" placeholder="08xxxx (otomatis jadi 628xx)">
                                @error('nomor_hp')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-group col-md-6">
                                <label>Email</label>
                                <input type="email" name="email" value="{{ old('email', $pppUser->email) }}" class="form-control @error('email') is-invalid @enderror">
                                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Alamat</label>
                            <textarea name="alamat" class="form-control @error('alamat') is-invalid @enderror" rows="2">{{ old('alamat', $pppUser->alamat) }}</textarea>
                            @error('alamat')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Latitude (Optional)</label>
                                <input type="text" name="latitude" id="latitude" value="{{ old('latitude', $pppUser->latitude) }}" class="form-control @error('latitude') is-invalid @enderror">
                                @error('latitude')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-group col-md-6">
                                <label>Longitude (Optional)</label>
                                <input type="text" name="longitude" id="longitude" value="{{ old('longitude', $pppUser->longitude) }}" class="form-control @error('longitude') is-invalid @enderror">
                                @error('longitude')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <button type="button" class="btn btn-outline-primary btn-sm" id="btn-capture-gps">
                                    <i class="fas fa-location-arrow mr-1"></i>Ambil Titik GPS (3 Sampel)
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm ml-2" id="btn-toggle-map-preview">
                                    <i class="fas fa-map-marked-alt mr-1"></i>Lihat Maps
                                </button>
                            </div>
                            <div class="form-group col-md-6 text-md-right">
                                <small class="text-muted d-block" id="location-meta-info">
                                    Akurasi: {{ old('location_accuracy_m', $pppUser->location_accuracy_m) ? number_format((float) old('location_accuracy_m', $pppUser->location_accuracy_m), 1) . ' m' : '-' }}
                                </small>
                            </div>
                        </div>
                        <div id="ppp-location-map-wrapper" class="d-none">
                            <div id="ppp-location-map" class="mb-3"></div>
                            <small class="text-muted d-block mb-3" id="location-map-info">
                                Gunakan layer Earth untuk cek visual satelit. Marker bisa digeser untuk koreksi titik presisi.
                            </small>
                        </div>
                        <input type="hidden" name="location_accuracy_m" id="location_accuracy_m" value="{{ old('location_accuracy_m', $pppUser->location_accuracy_m) }}">
                        <input type="hidden" name="location_capture_method" id="location_capture_method" value="{{ old('location_capture_method', $pppUser->location_capture_method) }}">
                        <input type="hidden" name="location_captured_at" id="location_captured_at" value="{{ old('location_captured_at', optional($pppUser->location_captured_at)->toIso8601String()) }}">
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Metode Login</label>
                                <select name="metode_login" class="form-control @error('metode_login') is-invalid @enderror" id="metode-login-select">
                                    <option value="username_password" @selected(old('metode_login', $pppUser->metode_login) === 'username_password')>USERNAME & PASSWORD</option>
                                    <option value="username_equals_password" @selected(old('metode_login', $pppUser->metode_login) === 'username_equals_password')>USERNAME = PASSWORD</option>
                                </select>
                                @error('metode_login')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-group col-md-6">
                                <label>Username</label>
                                <input type="text" name="username" value="{{ old('username', $pppUser->username) }}" class="form-control @error('username') is-invalid @enderror">
                                @error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="form-row" id="ppp-password-row">
                            <div class="form-group col-md-6">
                                <label>Password PPPoE/L2TP/OVPN</label>
                                <input type="text" name="ppp_password" value="{{ old('ppp_password', $pppUser->ppp_password) }}" class="form-control @error('ppp_password') is-invalid @enderror">
                                @error('ppp_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <small class="text-muted">Jika metode login \"USERNAME = PASSWORD\" dan dikosongkan, password PPP otomatis sama dengan username.</small>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Password Clientarea</label>
                                <input type="text" name="password_clientarea" value="{{ old('password_clientarea', $pppUser->password_clientarea) }}" class="form-control @error('password_clientarea') is-invalid @enderror" id="password-clientarea">
                                @error('password_clientarea')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <small class="text-muted">Jika metode login "USERNAME = PASSWORD" dan kosong, password akan disamakan dengan username.</small>
                            </div>
                            <div class="form-group col-md-6">
                                <label>Catatan (Optional)</label>
                                <textarea name="catatan" class="form-control @error('catatan') is-invalid @enderror" rows="2">{{ old('catatan', $pppUser->catatan) }}</textarea>
                                @error('catatan')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                </div>

                @if(!auth()->user()->isTeknisi())
                <div class="card">
                    <div class="card-header"><b>Penugasan Teknisi</b></div>
                    <div class="card-body">
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Teknisi yang Ditugaskan</label>
                                <select name="assigned_teknisi_id" class="form-control @error('assigned_teknisi_id') is-invalid @enderror">
                                    <option value="">-- Tidak ada / Semua teknisi bisa akses --</option>
                                    @foreach($teknisiList as $teknisi)
                                        <option value="{{ $teknisi->id }}" {{ old('assigned_teknisi_id', $pppUser->assigned_teknisi_id) == $teknisi->id ? 'selected' : '' }}>
                                            {{ $teknisi->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('assigned_teknisi_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <small class="text-muted">Jika diisi, hanya teknisi yang dipilih yang dapat mengelola pelanggan ini. Teknisi lain hanya bisa melihat data.</small>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

            </div>
            <div class="d-flex justify-content-between px-3 pb-3">
                <a href="{{ route('ppp-users.index') }}" class="btn btn-link">Batal</a>
                <button type="submit" class="btn btn-primary">Update</button>
            </div>
        </form>
                </div>{{-- end #edit-pane --}}

                {{-- TAB: INVOICE & SESSION --}}
                <div class="tab-pane fade p-3" id="invoice-pane" role="tabpanel">
                    <p class="text-muted small"><strong>*** Penghitung trafik direset disetiap waktu jatuh tempo</strong></p>

                    {{-- Session info cards --}}
                    @php
                        $activeSession = \App\Models\RadiusAccount::where('username', $pppUser->username)
                            ->where('is_active', true)->first();
                        $formatBytes = function (int $bytes): string {
                            if ($bytes >= 1099511627776) return round($bytes / 1099511627776, 2) . ' TB';
                            if ($bytes >= 1073741824)    return round($bytes / 1073741824, 2) . ' GB';
                            if ($bytes >= 1048576)       return round($bytes / 1048576, 2) . ' MB';
                            if ($bytes >= 1024)          return round($bytes / 1024, 2) . ' KB';
                            return $bytes . ' B';
                        };
                        $bytesIn  = (int) ($activeSession?->bytes_in  ?? 0);
                        $bytesOut = (int) ($activeSession?->bytes_out ?? 0);
                        $totalBytes  = $bytesIn + $bytesOut;
                        $uploadDisplay   = $formatBytes($bytesIn);
                        $downloadDisplay = $formatBytes($bytesOut);
                        $totalDisplay    = $formatBytes($totalBytes);

                        // Parse uptime string (e.g. "2d17h23m7s", "4h33m25s", "2d17h23m") → total seconds
                        $uptimeSeconds = 0;
                        if ($activeSession?->uptime) {
                            preg_match_all('/(\d+)([wdhms])/', $activeSession->uptime, $matches, PREG_SET_ORDER);
                            foreach ($matches as $m) {
                                $uptimeSeconds += match($m[2]) {
                                    'w' => (int)$m[1] * 604800,
                                    'd' => (int)$m[1] * 86400,
                                    'h' => (int)$m[1] * 3600,
                                    'm' => (int)$m[1] * 60,
                                    's' => (int)$m[1],
                                    default => 0,
                                };
                            }
                        }
                        $baseSeconds = $uptimeSeconds;
                    @endphp
                    <div class="row mb-3">
                        <div class="col-md-3 mb-2 d-flex">
                            <div class="p-3 rounded text-white w-100 {{ $activeSession ? 'bg-info' : 'bg-secondary' }}">
                                <div class="small mb-1" style="opacity:.8">Perangkat</div>
                                <div><i class="fas fa-network-wired mr-1"></i><strong>{{ $activeSession?->caller_id ?? '-' }}</strong></div>
                                <div class="small mt-1" style="opacity:.9"><strong>{{ $activeSession ? 'connected' : 'disconnected' }}</strong></div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-2 d-flex">
                            <div class="p-3 rounded w-100 bg-warning text-dark">
                                <div class="small mb-1" style="opacity:.7">Waktu Online</div>
                                <div><i class="fas fa-clock mr-1"></i><strong id="uptime-counter">{{ $activeSession?->uptime ?? '-' }}</strong></div>
                                <div class="small mt-1" style="opacity:.75">
                                    @if($activeSession?->updated_at)
                                        sync: {{ $activeSession->updated_at->diffForHumans() }}
                                    @else
                                        &nbsp;
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-2 d-flex">
                            <div class="p-3 rounded text-white w-100 bg-success">
                                <div class="small mb-1" style="opacity:.8">Quota Terpakai</div>
                                <div><i class="fas fa-chart-area mr-1"></i><strong>{{ $totalDisplay }}</strong></div>
                                <div class="small mt-1" style="opacity:.9">
                                    <i class="fas fa-upload mr-1"></i>{{ $uploadDisplay }}
                                    &nbsp;&nbsp;
                                    <i class="fas fa-download mr-1"></i>{{ $downloadDisplay }}
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-2 d-flex">
                            <div class="p-3 rounded text-white w-100 bg-primary">
                                <div class="small mb-1" style="opacity:.8">IP Address</div>
                                <div><i class="fas fa-signal mr-1"></i><strong>{{ $activeSession?->ipv4_address ?? '-' }}</strong></div>
                                <div class="small mt-1">&nbsp;</div>
                            </div>
                        </div>
                    </div>

                    {{-- Action buttons --}}
                    <div class="mb-3">
                        <button class="btn btn-info btn-sm" data-ajax-post="{{ route('ppp-users.add-invoice', $pppUser) }}" data-confirm="Tambah tagihan baru untuk pelanggan ini?">
                            <i class="fas fa-file-invoice mr-1"></i>add invoice
                        </button>
                        <button class="btn btn-success btn-sm ml-1 btn-disconnect" data-url="{{ route('ppp-users.disconnect', $pppUser) }}" {{ $activeSession ? '' : 'disabled' }}>
                            <i class="fas fa-ban mr-1"></i>disconnect
                        </button>
                        <button class="btn btn-danger btn-sm ml-1 btn-toggle-akun" data-url="{{ route('ppp-users.toggle-status', $pppUser) }}" data-status="{{ $pppUser->status_akun }}">
                            <i class="fas fa-times mr-1"></i>{{ $pppUser->status_akun === 'disable' ? 'enable' : 'disable' }}
                        </button>
                    </div>

                    {{-- Invoice datatable --}}
                    <table id="invoice-dt" class="table table-striped table-hover table-sm" style="width:100%">
                        <thead class="thead-light">
                            <tr>
                                <th>Id</th>
                                <th>Invoice</th>
                                <th>Paket Langganan</th>
                                <th>Jumlah</th>
                                <th>Aktivasi</th>
                                <th>Deadline</th>
                                <th>Owner Data</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>{{-- end #invoice-pane --}}

                {{-- TAB: RIWAYAT DIALUP --}}
                <div class="tab-pane fade p-3" id="dialup-pane" role="tabpanel">
                    <p class="text-muted small"><strong>*** only display last 100 record</strong></p>
                    <table id="dialup-dt" class="table table-striped table-hover table-sm" style="width:100%">
                        <thead class="thead-light">
                            <tr>
                                <th>Acct ID</th>
                                <th>Uptime</th>
                                <th>Waktu Mulai</th>
                                <th>Waktu Berakhir</th>
                                <th>NAS</th>
                                <th>Upload</th>
                                <th>Download</th>
                                <th>Terminate By</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>{{-- end #dialup-pane --}}

                {{-- TAB: CPE PERANGKAT --}}
                <div class="tab-pane fade p-3" id="cpe-pane" role="tabpanel">
                    @include('cpe._panel', ['pppUser' => $pppUser])
                </div>{{-- end #cpe-pane --}}

            </div>{{-- end .tab-content mainTabContent --}}
        </div>{{-- end .card-body --}}
    </div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
function formatBiayaAktivasi(el) {
    var raw = el.value.replace(/\./g, '').replace(/[^0-9]/g, '');
    var num = parseInt(raw, 10) || 0;
    el.value = num > 0 ? num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.') : '';
    document.getElementById('biaya_instalasi_value').value = num || 0;
}
        // Live uptime counter
        (function () {
            var el = document.getElementById('uptime-counter');
            if (!el) return;
            var base = {{ $baseSeconds }};
            if (base <= 0) return;
            function fmt(s) {
                var d = Math.floor(s / 86400);
                var h = Math.floor((s % 86400) / 3600);
                var m = Math.floor((s % 3600) / 60);
                var sec = s % 60;
                if (d > 0) return d + 'd ' + h + 'h ' + m + 'm ' + sec + 's';
                if (h > 0) return h + 'h ' + m + 'm ' + sec + 's';
                return m + 'm ' + sec + 's';
            }
            var t = base;
            el.textContent = fmt(t);
            setInterval(function () { el.textContent = fmt(++t); }, 1000);
        })();

        const ipSelect = document.getElementById('tipe-ip-select');
        const staticSection = document.getElementById('static-ip-section');
        const latitudeInput = document.getElementById('latitude');
        const longitudeInput = document.getElementById('longitude');
        const locationAccuracyInput = document.getElementById('location_accuracy_m');
        const locationMethodInput = document.getElementById('location_capture_method');
        const locationCapturedAtInput = document.getElementById('location_captured_at');
        const locationMetaInfo = document.getElementById('location-meta-info');
        const captureGpsButton = document.getElementById('btn-capture-gps');
        const toggleMapButton = document.getElementById('btn-toggle-map-preview');
        const locationMapWrapper = document.getElementById('ppp-location-map-wrapper');
        const locationMapInfo = document.getElementById('location-map-info');
        const infoTabButton = document.getElementById('info-tab');
        const earthFocusZoom = 17;
        let isMapVisible = false;
        let locationMap = null;
        let locationMarker = null;

        function median(values) {
            const sorted = values.slice().sort((a, b) => a - b);
            const middle = Math.floor(sorted.length / 2);
            return sorted.length % 2 === 0
                ? (sorted[middle - 1] + sorted[middle]) / 2
                : sorted[middle];
        }

        function parseCoordinate(value) {
            const parsed = parseFloat(value);
            return Number.isFinite(parsed) ? parsed : null;
        }

        function setMapInfo(message, className) {
            if (! locationMapInfo) {
                return;
            }

            locationMapInfo.textContent = message;
            locationMapInfo.className = className;
        }

        function initLocationMap() {
            if (locationMap || typeof L === 'undefined') {
                return;
            }

            const mapContainer = document.getElementById('ppp-location-map');
            if (! mapContainer) {
                return;
            }

            const initialLat = parseCoordinate(latitudeInput?.value);
            const initialLng = parseCoordinate(longitudeInput?.value);
            const initialPoint = (initialLat !== null && initialLng !== null) ? [initialLat, initialLng] : [-7.36, 109.90];
            const initialZoom = (initialLat !== null && initialLng !== null) ? earthFocusZoom : 12;

            locationMap = L.map('ppp-location-map').setView(initialPoint, initialZoom);

            const earthLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                maxZoom: 19,
                maxNativeZoom: earthFocusZoom,
                attribution: 'Tiles &copy; Esri'
            }).addTo(locationMap);

            const streetLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors'
            });

            L.control.layers({
                Earth: earthLayer,
                Street: streetLayer
            }).addTo(locationMap);

            locationMarker = L.marker(initialPoint, { draggable: true }).addTo(locationMap);

            locationMarker.on('dragend', function () {
                const position = locationMarker.getLatLng();
                setMapPoint(position.lat, position.lng, false);
            });

            locationMap.on('click', function (event) {
                setMapPoint(event.latlng.lat, event.latlng.lng, false);
            });
        }

        function setMapVisibility(visible) {
            isMapVisible = visible;

            if (locationMapWrapper) {
                locationMapWrapper.classList.toggle('d-none', ! visible);
            }

            if (toggleMapButton) {
                toggleMapButton.innerHTML = visible
                    ? '<i class="fas fa-eye-slash mr-1"></i>Sembunyikan Maps'
                    : '<i class="fas fa-map-marked-alt mr-1"></i>Lihat Maps';
            }

            if (! visible) {
                return;
            }

            ensureLocationMapReady();
            syncMapFromInputs();
        }

        function ensureLocationMapReady() {
            initLocationMap();

            if (locationMap) {
                setTimeout(function () {
                    locationMap.invalidateSize();
                }, 0);
            }
        }

        function getFocusZoom() {
            if (! locationMap) {
                return earthFocusZoom;
            }

            const maxZoom = locationMap.getMaxZoom();

            if (typeof maxZoom === 'number' && Number.isFinite(maxZoom)) {
                return Math.min(maxZoom, earthFocusZoom);
            }

            return earthFocusZoom;
        }

        function setMapPoint(lat, lng, shouldFocusMap) {
            if (latitudeInput) {
                latitudeInput.value = lat.toFixed(7);
            }
            if (longitudeInput) {
                longitudeInput.value = lng.toFixed(7);
            }
            if (locationMarker) {
                locationMarker.setLatLng([lat, lng]);
            }
            if (shouldFocusMap && locationMap) {
                locationMap.setView([lat, lng], getFocusZoom());
            }

            setMapInfo('Titik disetel: ' + lat.toFixed(7) + ', ' + lng.toFixed(7), 'text-success d-block mb-3');
        }

        function setLocationMeta(accuracy, method) {
            if (locationAccuracyInput) {
                locationAccuracyInput.value = accuracy.toFixed(2);
            }
            if (locationMethodInput) {
                locationMethodInput.value = method;
            }
            if (locationCapturedAtInput) {
                locationCapturedAtInput.value = new Date().toISOString();
            }
            if (locationMetaInfo) {
                locationMetaInfo.textContent = 'Akurasi: ' + accuracy.toFixed(1) + ' m (' + method + ')';
                locationMetaInfo.classList.remove('text-muted');
                locationMetaInfo.classList.add('text-success');
            }
        }

        function captureGpsSamples() {
            if (!navigator.geolocation) {
                alert('Browser tidak mendukung geolocation.');
                return;
            }

            captureGpsButton.disabled = true;
            captureGpsButton.innerHTML = '<i class=\"fas fa-spinner fa-spin mr-1\"></i>Mengambil titik...';
            const samples = [];

            function takeSample() {
                navigator.geolocation.getCurrentPosition(function (position) {
                    samples.push({
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude,
                        accuracy: position.coords.accuracy
                    });

                    if (samples.length < 3) {
                        setTimeout(takeSample, 1200);
                        return;
                    }

                    const latitudes = samples.map(s => s.latitude);
                    const longitudes = samples.map(s => s.longitude);
                    const accuracies = samples.map(s => s.accuracy);
                    const latitudeMedian = median(latitudes);
                    const longitudeMedian = median(longitudes);

                    setMapVisibility(true);
                    ensureLocationMapReady();
                    setMapPoint(latitudeMedian, longitudeMedian, true);
                    setLocationMeta(median(accuracies), 'gps');

                    captureGpsButton.disabled = false;
                    captureGpsButton.innerHTML = '<i class=\"fas fa-location-arrow mr-1\"></i>Ambil Titik GPS (3 Sampel)';
                }, function (error) {
                    let message = 'Gagal mengambil lokasi.';
                    if (error.code === 1) message = 'Izin lokasi ditolak.';
                    if (error.code === 2) message = 'Lokasi tidak tersedia.';
                    if (error.code === 3) message = 'Permintaan lokasi timeout.';
                    alert(message);
                    captureGpsButton.disabled = false;
                    captureGpsButton.innerHTML = '<i class=\"fas fa-location-arrow mr-1\"></i>Ambil Titik GPS (3 Sampel)';
                }, {
                    enableHighAccuracy: true,
                    timeout: 15000,
                    maximumAge: 0,
                });
            }

            takeSample();
        }

        if (captureGpsButton) {
            captureGpsButton.addEventListener('click', captureGpsSamples);
        }

        if (toggleMapButton) {
            toggleMapButton.addEventListener('click', function () {
                setMapVisibility(! isMapVisible);
            });
        }

        function syncMapFromInputs() {
            if (! isMapVisible) {
                return;
            }

            const latitude = parseCoordinate(latitudeInput?.value);
            const longitude = parseCoordinate(longitudeInput?.value);

            if (latitude === null || longitude === null) {
                return;
            }

            ensureLocationMapReady();
            setMapPoint(latitude, longitude, true);
        }

        if (latitudeInput) {
            latitudeInput.addEventListener('change', syncMapFromInputs);
        }

        if (longitudeInput) {
            longitudeInput.addEventListener('change', syncMapFromInputs);
        }

        if (window.jQuery && infoTabButton) {
            $('#info-tab').on('shown.bs.tab', function () {
                if (! isMapVisible) {
                    return;
                }

                ensureLocationMapReady();
                syncMapFromInputs();
            });
        }

        if (document.getElementById('info')?.classList.contains('show') && isMapVisible) {
            ensureLocationMapReady();
            syncMapFromInputs();
        }

        setMapVisibility(false);

        function toggleStatic() {
            staticSection.style.display = ipSelect.value === 'static' ? 'block' : 'none';
        }
        ipSelect.addEventListener('change', toggleStatic);
        toggleStatic();

        // Invoice datatable (lazy init on tab show)
        var invoiceDtInitialized = false;
        var dialupDtInitialized  = false;

        $('#mainTab a[href="#invoice-pane"]').on('shown.bs.tab', function () {
            if (invoiceDtInitialized) return;
            invoiceDtInitialized = true;
            $('#invoice-dt').DataTable({
                processing: true,
                serverSide: true,
                responsive: true,
                ajax: '{{ route('ppp-users.invoice-datatable', $pppUser) }}',
                columns: [
                    { data: 'id' },
                    { data: 'invoice_number' },
                    { data: 'paket_langganan' },
                    { data: 'total' },
                    { data: 'created_at' },
                    { data: 'due_date' },
                    { data: 'owner' },
                    { data: 'aksi', orderable: false, searchable: false },
                ],
                language: {
                    search: 'Cari:', lengthMenu: 'Tampilkan _MENU_ data',
                    info: 'Menampilkan _START_ - _END_ dari _TOTAL_ data',
                    infoEmpty: 'Tidak ada data', zeroRecords: 'Tidak ada data.',
                    emptyTable: 'Belum ada invoice.', processing: 'Memuat...',
                    paginate: { first: 'Pertama', last: 'Terakhir', next: 'Selanjutnya', previous: 'Sebelumnya' },
                },
                order: [[0, 'desc']],
                pageLength: 10,
            });
        });

        $('#mainTab a[href="#dialup-pane"]').on('shown.bs.tab', function () {
            if (dialupDtInitialized) return;
            dialupDtInitialized = true;
            $('#dialup-dt').DataTable({
                processing: true,
                serverSide: true,
                responsive: true,
                ajax: '{{ route('ppp-users.dialup-datatable', $pppUser) }}',
                columns: [
                    { data: 'radacctid' },
                    { data: 'uptime' },
                    { data: 'start' },
                    { data: 'stop' },
                    { data: 'nas' },
                    { data: 'upload' },
                    { data: 'download' },
                    { data: 'terminate' },
                ],
                language: {
                    search: 'Cari:', lengthMenu: 'Tampilkan _MENU_ data',
                    info: 'Menampilkan _START_ - _END_ dari _TOTAL_ data',
                    infoEmpty: 'Tidak ada data', zeroRecords: 'Tidak ada data.',
                    emptyTable: 'Belum ada riwayat dialup.', processing: 'Memuat...',
                    paginate: { first: 'Pertama', last: 'Terakhir', next: 'Selanjutnya', previous: 'Sebelumnya' },
                },
                order: [[0, 'desc']],
                pageLength: 10,
            });
        });

        // Disconnect button
        $(document).on('click', '.btn-disconnect', function () {
            var url = $(this).data('url');
            if (!confirm('Putuskan koneksi aktif pelanggan ini?')) return;
            var btn = $(this);
            btn.prop('disabled', true);
            $.post(url, { _token: '{{ csrf_token() }}' })
                .done(function (res) {
                    toastr.success(res.status || 'Koneksi diputus.');
                    setTimeout(function () { location.reload(); }, 1500);
                })
                .fail(function () { toastr.error('Gagal memutus koneksi.'); btn.prop('disabled', false); });
        });

        // Toggle akun (enable/disable)
        $(document).on('click', '.btn-toggle-akun', function () {
            var btn = $(this);
            var url = btn.data('url');
            var current = btn.data('status');
            var action = current === 'disable' ? 'enable' : 'disable';
            if (!confirm('Ubah status akun menjadi ' + action + '?')) return;
            $.post(url, { _token: '{{ csrf_token() }}' })
                .done(function (res) {
                    toastr.success('Status akun: ' + res.status);
                    btn.data('status', res.status);
                    btn.html('<i class="fas fa-times mr-1"></i>' + (res.status === 'disable' ? 'enable' : 'disable'));
                })
                .fail(function () { toastr.error('Gagal mengubah status.'); });
        });
    </script>

    {{-- CPE Management Scripts --}}
    <script>
    (function () {
        var csrfToken = '{{ csrf_token() }}';

        function cpeAjax(method, url, data, onSuccess, onComplete) {
            $.ajax({
                method: method,
                url: url,
                data: Object.assign({ _token: csrfToken }, data || {}),
                dataType: 'json',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                success: function (res) {
                    if (res.message) toastr.success(res.message);
                    if (onSuccess) onSuccess(res);
                },
                error: function (xhr) {
                    var msg = (xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error))
                        || 'Terjadi kesalahan. Status: ' + xhr.status;
                    toastr.error(msg);
                },
                complete: function () {
                    if (onComplete) onComplete();
                },
            });
        }

        function updateCpePanel(device) {
            var timeStr = device.last_seen_at || '';
            var statusHtml = {
                online:  '<span class="badge badge-success">Online</span>' + (timeStr ? ' <span class="text-muted small">· ' + timeStr + '</span>' : ''),
                offline: '<span class="badge badge-danger">Offline</span>' + (timeStr ? ' <span class="text-muted small">· ' + timeStr + '</span>' : ''),
                unknown: '<span class="badge badge-secondary">Tidak Diketahui</span>',
            };
            $('#cpe-status').html(statusHtml[device.status] || statusHtml.unknown);

            // PPPoE status
            var pppoeHtml = device.pppoe_online
                ? '<span class="badge badge-success">Online</span>' + (device.pppoe_ip ? ' <span class="text-muted small">· ' + device.pppoe_ip + '</span>' : '')
                : '<span class="badge badge-danger">Offline</span>';
            $('#cpe-pppoe-status').html(pppoeHtml);
            $('#cpe-manufacturer').text(device.manufacturer || '-');
            $('#cpe-model').text(device.model || '-');
            $('#cpe-firmware').text(device.firmware_version || '-');
            $('#cpe-serial').text(device.serial_number || '-');
            $('#cpe-device-id').text(device.genieacs_device_id || '-');

            // Update multi-SSID accordion
            var params = device.cached_params || {};
            if (params.wifi_networks && params.wifi_networks.length > 0) {
                $('#cpe-wifi-empty').hide();
                $('#cpe-wifi-count').text(params.wifi_networks.length + ' jaringan terdeteksi');
                $.each(params.wifi_networks, function (i, wn) {
                    var $card = $('#cpe-wifi-card-' + wn.index);
                    if ($card.length) {
                        // Update values in existing card
                        $card.find('.cpe-wifi-ssid-val').val(wn.ssid || '');
                        $card.find('.cpe-wifi-pass-val').val(wn.password || '');
                        $card.find('.cpe-wifi-enable-val').prop('checked', !!wn.enabled);
                        $card.find('.cpe-wifi-status-' + wn.index)
                            .removeClass('badge-success badge-danger')
                            .addClass(wn.enabled ? 'badge-success' : 'badge-danger')
                            .text(wn.enabled ? 'Aktif' : 'Nonaktif');
                    } else {
                        // New card — reload page to render Blade
                        location.reload();
                    }
                });
            }

            // Update WAN accordion count
            if (params.wan_connections && params.wan_connections.length > 0) {
                $('#cpe-wan-empty').hide();
                $('#cpe-wan-count').text(params.wan_connections.length + ' koneksi terdeteksi');
                $.each(params.wan_connections, function (i, wc) {
                    var safeKey = wc.key.replace(/\./g, '_');
                    var $card   = $('#cpe-wan-card-' + safeKey);
                    if ($card.length) {
                        $card.find('.cpe-wan-conn-type').val(wc.connection_type || '');
                        $card.find('.cpe-wan-vlan').val(wc.vlan_id || '');
                        $card.find('.cpe-wan-vlan-prio').val(wc.vlan_prio || 0);
                        $card.find('.cpe-wan-dns').val(wc.dns_servers || '');
                        $card.find('.cpe-wan-user').val(wc.username || '');
                        $card.find('.cpe-wan-enable').prop('checked', !!wc.enabled);
                    }
                });
            }
        }

        // Sync
        $(document).on('click', '#btn-cpe-sync', function () {
            var $btn = $(this);
            var id   = $btn.data('ppp-user-id');
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Mencari...');
            $.ajax({
                method: 'POST',
                url: '/ppp-users/' + id + '/cpe/sync',
                data: { _token: csrfToken },
                success: function (res) {
                    if (res.success) {
                        toastr.success(res.message || 'Perangkat ditemukan!');
                        setTimeout(function () { location.reload(); }, 1000);
                    } else {
                        toastr.warning(res.message || 'Perangkat tidak ditemukan.');
                        $btn.prop('disabled', false).html('<i class="fas fa-search mr-1"></i> Cari / Sinkronisasi GenieACS');
                    }
                },
                error: function (xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Gagal menghubungi server. Status: ' + xhr.status);
                    $btn.prop('disabled', false).html('<i class="fas fa-search mr-1"></i> Cari / Sinkronisasi GenieACS');
                },
            });
        });

        // Refresh
        $(document).on('click', '#btn-cpe-refresh', function () {
            var $btn = $(this);
            var id   = $btn.data('ppp-user-id');
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Memuat...');
            $.ajax({
                method: 'POST',
                url: '/ppp-users/' + id + '/cpe/refresh',
                data: { _token: csrfToken },
                success: function (res) {
                    if (res.message) toastr.success(res.message);
                    if (res.device) updateCpePanel(res.device);
                },
                error: function (xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Gagal refresh. Status: ' + xhr.status);
                },
                complete: function () {
                    $btn.prop('disabled', false).html('<i class="fas fa-sync-alt mr-1"></i> Refresh Info');
                },
            });
        });

        // Auto-refresh saat tab CPE dibuka (hanya jika device sudah linked)
        $(document).on('shown.bs.tab', 'a[href="#cpe-pane"]', function () {
            var $btn = $('#btn-cpe-refresh');
            if ($btn.length) $btn.trigger('click');
        });

        // Reboot
        $(document).on('click', '#btn-cpe-reboot', function () {
            if (!confirm('Yakin ingin mereboot perangkat ini?')) return;
            var $btn = $(this);
            var id = $btn.data('ppp-user-id');
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Mereboot...');
            cpeAjax('POST', '/ppp-users/' + id + '/cpe/reboot', {}, null, function () {
                $btn.prop('disabled', false).html('<i class="fas fa-power-off mr-1"></i> Reboot Perangkat');
            });
        });

        // WiFi per-index (multi-SSID)
        $(document).on('click', '.btn-cpe-wifi-save', function () {
            var $btn    = $(this);
            var id      = $btn.data('ppp-user-id');
            var wlanIdx = $btn.data('wlan-idx');
            var $card   = $('#cpe-wifi-card-' + wlanIdx);
            var ssid    = $card.find('.cpe-wifi-ssid-val').val().trim();
            var pass    = $card.find('.cpe-wifi-pass-val').val().trim();
            var enabled = $card.find('.cpe-wifi-enable-val').is(':checked') ? 1 : 0;
            if (pass && pass.length < 8) { toastr.warning('Password WiFi minimal 8 karakter.'); return; }
            var payload = { enabled: enabled };
            if (ssid)  payload.ssid     = ssid;
            if (pass)  payload.password = pass;
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>');
            cpeAjax('POST', '/ppp-users/' + id + '/cpe/wifi/' + wlanIdx, payload, null, function () {
                $btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Simpan');
            });
        });

        // PPPoE
        $(document).on('click', '#btn-cpe-pppoe', function () {
            var id   = $(this).data('ppp-user-id');
            var user = $('#cpe-pppoe-user-input').val().trim();
            var pass = $('#cpe-pppoe-pass-input').val().trim();
            if (!user || !pass) { toastr.warning('Isi username dan password PPPoE.'); return; }
            if (!confirm('Terapkan kredensial PPPoE ini ke modem pelanggan?')) return;
            cpeAjax('POST', '/ppp-users/' + id + '/cpe/pppoe', { username: user, password: pass });
        });

        // WAN save
        $(document).on('click', '.btn-cpe-wan-save', function () {
            var $btn   = $(this);
            var id     = $btn.data('ppp-user-id');
            var wanKey = $btn.data('wan-key');
            var parts  = wanKey.split('.');  // [wanIdx, cdIdx, connIdx]
            var $card  = $('#cpe-wan-card-' + wanKey.replace(/\./g, '_'));

            // Collect port binding checkboxes
            var ifaces = [];
            $card.find('.cpe-wan-iface-cb:checked').each(function () {
                ifaces.push($(this).data('iface'));
            });

            var payload = {
                enabled:         $card.find('.cpe-wan-enable').is(':checked') ? 1 : 0,
                connection_type: $card.find('.cpe-wan-conn-type').val(),
                vlan_id:         $card.find('.cpe-wan-vlan').val() || null,
                vlan_prio:       $card.find('.cpe-wan-vlan-prio').val() || 0,
                dns_servers:     $card.find('.cpe-wan-dns').val().trim() || null,
                lan_interface:   ifaces.join(','),
            };
            var user = $card.find('.cpe-wan-user').val().trim();
            var pass = $card.find('.cpe-wan-pass').val().trim();
            if (user) payload.username = user;
            if (pass) payload.password = pass;

            if (!confirm('Terapkan perubahan konfigurasi WAN ke modem?')) return;
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>');

            var url = '/ppp-users/' + id + '/cpe/wan/' + parts[0] + '/' + parts[1] + '/' + parts[2];
            $.ajax({
                method: 'PUT',
                url: url,
                data: Object.assign({ _token: csrfToken }, payload),
                success: function (res) {
                    if (res.message) toastr.success(res.message);
                },
                error: function (xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Gagal menyimpan. Status: ' + xhr.status);
                },
                complete: function () {
                    $btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Simpan');
                },
            });
        });

        // Unlink
        $(document).on('click', '#btn-cpe-unlink', function () {
            if (!confirm('Lepaskan tautan perangkat? Data lokal akan dihapus.')) return;
            var id = $(this).data('ppp-user-id');
            cpeAjax('DELETE', '/ppp-users/' + id + '/cpe', {}, function (res) {
                if (res.success) location.reload();
            });
        });
    }());
    </script>
@endpush
