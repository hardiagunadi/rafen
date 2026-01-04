@extends('layouts.admin')

@section('title', 'Tambah User PPP')

@section('content')
    <div class="card">
        <div class="card-header">
            <h4 class="mb-0">Tambah Pelanggan</h4>
        </div>
        <form action="{{ route('ppp-users.store') }}" method="POST" id="ppp-user-form" novalidate>
            @csrf
            <div id="form-alert" class="alert alert-danger m-3 d-none"></div>
            <div class="card-body">
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
                                <select name="owner_id" class="form-control @error('owner_id') is-invalid @enderror" required>
                                    @foreach($owners as $owner)
                                        <option value="{{ $owner->id }}" @selected(old('owner_id') == $owner->id)>{{ $owner->name }}</option>
                                    @endforeach
                                </select>
                                @error('owner_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-group col-md-4">
                                <label>Paket Langganan (Profil PPP)</label>
                                <select name="ppp_profile_id" class="form-control @error('ppp_profile_id') is-invalid @enderror" required>
                                    <option value="" disabled @selected(! old('ppp_profile_id'))>- pilih paket -</option>
                                    @foreach($profiles as $profile)
                                        <option value="{{ $profile->id }}" @selected(old('ppp_profile_id') == $profile->id)>{{ $profile->name }}</option>
                                    @endforeach
                                </select>
                                @error('ppp_profile_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-group col-md-4">
                                <label>Status Registrasi</label>
                                <div>
                                    <label class="mr-3"><input type="radio" name="status_registrasi" value="aktif" @checked(old('status_registrasi', 'aktif') === 'aktif') required> AKTIF SEKARANG</label>
                                    <label><input type="radio" name="status_registrasi" value="on_process" @checked(old('status_registrasi') === 'on_process')> ON PROCESS</label>
                                </div>
                                @error('status_registrasi')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Tipe Pembayaran</label>
                                <select name="tipe_pembayaran" class="form-control @error('tipe_pembayaran') is-invalid @enderror" required>
                                    <option value="prepaid" @selected(old('tipe_pembayaran', 'prepaid') === 'prepaid')>PREPAID</option>
                                    <option value="postpaid" @selected(old('tipe_pembayaran') === 'postpaid')>POSTPAID</option>
                                </select>
                                @error('tipe_pembayaran')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-group col-md-6">
                                <label>Status Bayar</label>
                                <select name="status_bayar" class="form-control @error('status_bayar') is-invalid @enderror" required>
                                    <option value="sudah_bayar" @selected(old('status_bayar') === 'sudah_bayar')>SUDAH BAYAR</option>
                                    <option value="belum_bayar" @selected(old('status_bayar', 'belum_bayar') === 'belum_bayar')>BELUM BAYAR</option>
                                </select>
                                @error('status_bayar')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Status Akun</label>
                                <select name="status_akun" class="form-control @error('status_akun') is-invalid @enderror" required>
                                    <option value="enable" @selected(old('status_akun', 'enable') === 'enable')>ENABLE</option>
                                    <option value="disable" @selected(old('status_akun') === 'disable')>DISABLE</option>
                                </select>
                                @error('status_akun')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-group col-md-6">
                                <label>Tipe Service</label>
                                <select name="tipe_service" class="form-control @error('tipe_service') is-invalid @enderror" required>
                                    <option value="pppoe" @selected(old('tipe_service', 'pppoe') === 'pppoe')>PPPoE</option>
                                    <option value="l2tp_pptp" @selected(old('tipe_service') === 'l2tp_pptp')>L2TP/PPTP</option>
                                    <option value="openvpn_sstp" @selected(old('tipe_service') === 'openvpn_sstp')>OPENVPN/SSTP</option>
                                </select>
                                @error('tipe_service')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="tagihkan_ppn" name="tagihkan_ppn" value="1" @checked(old('tagihkan_ppn', true))>
                                    <label class="form-check-label" for="tagihkan_ppn">Tagihkan PPN (persentase dari Profil PPP)</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="prorata_otomatis" name="prorata_otomatis" value="1" @checked(old('prorata_otomatis'))>
                                    <label class="form-check-label" for="prorata_otomatis">Prorata Otomatis</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="promo_aktif" name="promo_aktif" value="1" @checked(old('promo_aktif'))>
                                    <label class="form-check-label" for="promo_aktif">Promo (Aktifkan Promo)</label>
                                </div>
                            </div>
                            <div class="form-group col-md-6">
                                <label>Durasi Promo (bulan) <span class="text-muted">(Opsional)</span></label>
                                <input type="number" name="durasi_promo_bulan" value="{{ old('durasi_promo_bulan') }}" class="form-control @error('durasi_promo_bulan') is-invalid @enderror" min="0">
                                @error('durasi_promo_bulan')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Biaya Instalasi <span class="text-muted">(Opsional)</span></label>
                                <input type="number" step="0.01" name="biaya_instalasi" value="{{ old('biaya_instalasi') }}" class="form-control @error('biaya_instalasi') is-invalid @enderror" min="0">
                                @error('biaya_instalasi')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-group col-md-6">
                                <label>Ubah Jatuh Tempo (Optional)</label>
                                <input type="date" name="jatuh_tempo" value="{{ old('jatuh_tempo') }}" class="form-control @error('jatuh_tempo') is-invalid @enderror">
                                @error('jatuh_tempo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <small class="text-muted">Jika tidak diisi, prorata diabaikan. Tidak berlaku untuk paket unlimited atau masa aktif < 3 hari.</small>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Aksi Jatuh Tempo</label>
                                <select name="aksi_jatuh_tempo" class="form-control @error('aksi_jatuh_tempo') is-invalid @enderror">
                                    <option value="isolir" @selected(old('aksi_jatuh_tempo', 'isolir') === 'isolir')>ISOLIR INTERNET</option>
                                    <option value="tetap_terhubung" @selected(old('aksi_jatuh_tempo') === 'tetap_terhubung')>TETAP TERHUBUNG</option>
                                </select>
                                @error('aksi_jatuh_tempo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-group col-md-6">
                                <label>Tipe IP Address</label>
                                <select name="tipe_ip" class="form-control @error('tipe_ip') is-invalid @enderror" id="tipe-ip-select">
                                    <option value="dhcp" @selected(old('tipe_ip', 'dhcp') === 'dhcp')>DHCP</option>
                                    <option value="static" @selected(old('tipe_ip') === 'static')>Static</option>
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
                                            <option value="{{ $group->id }}" @selected(old('profile_group_id') == $group->id)>{{ $group->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('profile_group_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="form-group col-md-6">
                                    <label>IP Address</label>
                                    <input type="text" name="ip_static" value="{{ old('ip_static') }}" class="form-control @error('ip_static') is-invalid @enderror" placeholder="xxx.xxx.xxx.xxx">
                                    @error('ip_static')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="info" role="tabpanel" aria-labelledby="info-tab">
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>ODP | POP <span class="text-muted">(Opsional)</span></label>
                                <input type="text" name="odp_pop" value="{{ old('odp_pop') }}" class="form-control @error('odp_pop') is-invalid @enderror">
                                @error('odp_pop')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-group col-md-6">
                                <label>ID Pelanggan</label>
                                <input type="text" name="customer_id" value="{{ old('customer_id') }}" class="form-control @error('customer_id') is-invalid @enderror" required>
                                @error('customer_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Nama</label>
                                <input type="text" name="customer_name" value="{{ old('customer_name') }}" class="form-control @error('customer_name') is-invalid @enderror" required>
                                @error('customer_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-group col-md-6">
                                <label>No. NIK</label>
                                <input type="text" name="nik" value="{{ old('nik') }}" class="form-control @error('nik') is-invalid @enderror" required>
                                @error('nik')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Nomor HP</label>
                                <input type="text" name="nomor_hp" value="{{ old('nomor_hp') }}" class="form-control @error('nomor_hp') is-invalid @enderror" placeholder="08xxxx (otomatis jadi 628xx)" required>
                                @error('nomor_hp')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-group col-md-6">
                                <label>Email</label>
                                <input type="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" required>
                                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Alamat</label>
                            <textarea name="alamat" class="form-control @error('alamat') is-invalid @enderror" rows="2" required>{{ old('alamat') }}</textarea>
                            @error('alamat')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Latitude <span class="text-muted">(Opsional)</span></label>
                                <input type="text" name="latitude" value="{{ old('latitude') }}" class="form-control @error('latitude') is-invalid @enderror">
                                @error('latitude')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-group col-md-6">
                                <label>Longitude <span class="text-muted">(Opsional)</span></label>
                                <input type="text" name="longitude" value="{{ old('longitude') }}" class="form-control @error('longitude') is-invalid @enderror">
                                @error('longitude')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Metode Login</label>
                                <select name="metode_login" class="form-control @error('metode_login') is-invalid @enderror" id="metode-login-select" required>
                                    <option value="username_password" @selected(old('metode_login', 'username_password') === 'username_password')>USERNAME & PASSWORD</option>
                                    <option value="username_equals_password" @selected(old('metode_login') === 'username_equals_password')>USERNAME = PASSWORD</option>
                                </select>
                                <small class="text-muted">Jika pilih USERNAME = PASSWORD, password akan disamakan dengan username.</small>
                                @error('metode_login')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-group col-md-6">
                                <label>Username</label>
                                <input type="text" name="username" value="{{ old('username') }}" class="form-control @error('username') is-invalid @enderror" required>
                                @error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="form-row" id="ppp-password-row">
                            <div class="form-group col-md-6">
                                <label>Password PPPoE/L2TP/OVPN</label>
                                <input type="text" name="ppp_password" value="{{ old('ppp_password') }}" class="form-control @error('ppp_password') is-invalid @enderror">
                                @error('ppp_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Password Clientarea</label>
                                <input type="text" name="password_clientarea" value="{{ old('password_clientarea') }}" class="form-control @error('password_clientarea') is-invalid @enderror" id="password-clientarea">
                                @error('password_clientarea')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-group col-md-6">
                                <label>Catatan (Optional)</label>
                                <textarea name="catatan" class="form-control @error('catatan') is-invalid @enderror" rows="2">{{ old('catatan') }}</textarea>
                                @error('catatan')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            <div class="card-footer d-flex justify-content-between">
                <a href="{{ route('ppp-users.index') }}" class="btn btn-link">Batal</a>
                <button type="submit" class="btn btn-primary" id="submit-btn">Simpan</button>
            </div>
        </form>
    </div>
    <script>
        const ipSelect = document.getElementById('tipe-ip-select');
        const staticSection = document.getElementById('static-ip-section');
        function toggleStatic() {
            staticSection.style.display = ipSelect.value === 'static' ? 'block' : 'none';
        }
        ipSelect.addEventListener('change', toggleStatic);
        toggleStatic();

        const metodeLoginSelect = document.getElementById('metode-login-select');
        const pppPasswordInput = document.querySelector('input[name="ppp_password"]');
        const pppPasswordRow = document.getElementById('ppp-password-row');
        function togglePasswordRequirement() {
            const isUsernamePassword = metodeLoginSelect.value === 'username_password';
            if (pppPasswordInput && pppPasswordRow) {
                pppPasswordInput.required = isUsernamePassword;
                pppPasswordRow.style.display = isUsernamePassword ? '' : 'none';
            }
        }
        metodeLoginSelect.addEventListener('change', togglePasswordRequirement);
        togglePasswordRequirement();

        document.getElementById('ppp-user-form').addEventListener('submit', function (e) {
            const paketTab = document.getElementById('paket');
            const infoTab = document.getElementById('info');
            const paketSelect = document.querySelector('select[name="ppp_profile_id"]');
            const alertBox = document.getElementById('form-alert');
            alertBox.classList.add('d-none');
            alertBox.innerHTML = '';

            const paketInvalid = Array.from(paketTab.querySelectorAll('[required]')).some(el => ! el.checkValidity());
            const infoInvalid = Array.from(infoTab.querySelectorAll('[required]')).some(el => ! el.checkValidity());
            const messages = [];

            if (! paketSelect.value) {
                messages.push('Paket Langganan belum diisi.');
            }

            if (paketInvalid || infoInvalid || ! this.checkValidity()) {
                if (paketInvalid) {
                    messages.push('Bagian Paket Langganan belum lengkap. Lengkapi field wajib.');
                } else if (infoInvalid) {
                    messages.push('Bagian Info Pelanggan belum lengkap. Lengkapi field wajib.');
                } else {
                    messages.push('Pastikan semua field wajib terisi dengan benar.');
                }
            }

            if (messages.length) {
                e.preventDefault();
                e.stopPropagation();
                alertBox.innerHTML = messages.join(' ');
                alertBox.classList.remove('d-none');
                if (paketInvalid || ! paketSelect.value) {
                    $('#paket-tab').tab('show');
                } else if (infoInvalid) {
                    $('#info-tab').tab('show');
                }
            }
        });
    </script>
@endsection
