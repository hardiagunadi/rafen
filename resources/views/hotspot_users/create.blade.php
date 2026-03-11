@extends('layouts.admin')

@section('title', 'Tambah User Hotspot')

@section('content')
    <div class="card">
        <div class="card-header">
            <h4 class="mb-0">Tambah User Hotspot</h4>
        </div>
        <form action="{{ route('hotspot-users.store') }}" method="POST" novalidate>
            @csrf
            <div class="card-body">
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
                        <label>Profil Hotspot</label>
                        <select name="hotspot_profile_id" class="form-control @error('hotspot_profile_id') is-invalid @enderror" required>
                            <option value="" disabled @selected(! old('hotspot_profile_id'))>- pilih profil -</option>
                            @foreach($profiles as $profile)
                                <option value="{{ $profile->id }}" @selected(old('hotspot_profile_id') == $profile->id)>
                                    {{ $profile->name }} - Rp {{ number_format((float) $profile->harga_jual, 0, ',', '.') }} - {{ $profile->masa_aktif_value ? ((int) $profile->masa_aktif_value.' '.$profile->masa_aktif_unit) : '-' }}
                                </option>
                            @endforeach
                        </select>
                        @error('hotspot_profile_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group col-md-4">
                        <label>Status Registrasi</label>
                        <div>
                            <label class="mr-3"><input type="radio" name="status_registrasi" value="aktif" @checked(old('status_registrasi', 'aktif') === 'aktif') required> AKTIF</label>
                            <label><input type="radio" name="status_registrasi" value="on_process" @checked(old('status_registrasi') === 'on_process')> ON PROCESS</label>
                        </div>
                        @error('status_registrasi')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>Tipe Pembayaran</label>
                        <select name="tipe_pembayaran" class="form-control @error('tipe_pembayaran') is-invalid @enderror" required>
                            <option value="prepaid" @selected(old('tipe_pembayaran', 'prepaid') === 'prepaid')>PREPAID</option>
                            <option value="postpaid" @selected(old('tipe_pembayaran') === 'postpaid')>POSTPAID</option>
                        </select>
                        @error('tipe_pembayaran')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group col-md-4">
                        <label>Status Bayar</label>
                        <select name="status_bayar" class="form-control @error('status_bayar') is-invalid @enderror" required>
                            <option value="sudah_bayar" @selected(old('status_bayar') === 'sudah_bayar')>SUDAH BAYAR</option>
                            <option value="belum_bayar" @selected(old('status_bayar', 'belum_bayar') === 'belum_bayar')>BELUM BAYAR</option>
                        </select>
                        @error('status_bayar')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group col-md-4">
                        <label>Status Akun</label>
                        <select name="status_akun" class="form-control @error('status_akun') is-invalid @enderror" required>
                            <option value="enable" @selected(old('status_akun', 'enable') === 'enable')>ENABLE</option>
                            <option value="disable" @selected(old('status_akun') === 'disable')>DISABLE</option>
                            <option value="isolir" @selected(old('status_akun') === 'isolir')>ISOLIR</option>
                        </select>
                        @error('status_akun')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Aksi Jatuh Tempo</label>
                        <select name="aksi_jatuh_tempo" class="form-control @error('aksi_jatuh_tempo') is-invalid @enderror" required>
                            <option value="isolir" @selected(old('aksi_jatuh_tempo', 'isolir') === 'isolir')>ISOLIR</option>
                            <option value="tetap_terhubung" @selected(old('aksi_jatuh_tempo') === 'tetap_terhubung')>TETAP TERHUBUNG</option>
                        </select>
                        @error('aksi_jatuh_tempo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group col-md-6">
                        <label>Jatuh Tempo</label>
                        <input type="date" name="jatuh_tempo" class="form-control @error('jatuh_tempo') is-invalid @enderror" value="{{ old('jatuh_tempo') }}">
                        @error('jatuh_tempo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <hr>
                <h6 class="text-uppercase text-muted">Informasi Pelanggan</h6>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="customer_name" class="form-control @error('customer_name') is-invalid @enderror" value="{{ old('customer_name') }}" required>
                        @error('customer_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group col-md-6">
                        <label>ID Pelanggan <span class="badge badge-info" style="font-size:10px">Auto</span></label>
                        <div class="input-group">
                            <input type="text" name="customer_id" id="customer_id" class="form-control @error('customer_id') is-invalid @enderror" value="{{ old('customer_id') }}" placeholder="Memuat..." readonly>
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-secondary" id="btn-generate-customer-id" title="Generate ulang ID">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                                <button type="button" class="btn btn-outline-secondary" id="btn-unlock-customer-id" title="Edit manual">
                                    <i class="fas fa-lock"></i>
                                </button>
                            </div>
                        </div>
                        @error('customer_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        <small class="text-muted" id="customer-id-hint">ID otomatis di-generate. Klik <i class="fas fa-lock"></i> untuk edit manual.</small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>No. HP</label>
                        <input type="text" name="nomor_hp" class="form-control @error('nomor_hp') is-invalid @enderror" value="{{ old('nomor_hp') }}" placeholder="08xxx / 62xxx">
                        @error('nomor_hp')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group col-md-4">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}">
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group col-md-4">
                        <label>NIK</label>
                        <input type="text" name="nik" class="form-control @error('nik') is-invalid @enderror" value="{{ old('nik') }}">
                        @error('nik')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="form-group">
                    <label>Alamat</label>
                    <textarea name="alamat" class="form-control @error('alamat') is-invalid @enderror" rows="2">{{ old('alamat') }}</textarea>
                    @error('alamat')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <hr>
                <h6 class="text-uppercase text-muted">Kredensial Hotspot</h6>

                <div class="form-group">
                    <label>Metode Login</label>
                    <select name="metode_login" id="metode-login-select" class="form-control @error('metode_login') is-invalid @enderror" required>
                        <option value="username_equals_password" @selected(old('metode_login', 'username_equals_password') === 'username_equals_password')>HANYA USERNAME ( PASSWORD = USERNAME )</option>
                        <option value="username_password" @selected(old('metode_login') === 'username_password')>USERNAME &amp; PASSWORD</option>
                    </select>
                    @error('metode_login')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Username <span class="text-danger">*</span></label>
                        <input type="text" name="username" class="form-control @error('username') is-invalid @enderror" value="{{ old('username') }}" required placeholder="Username">
                        @error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group col-md-6" id="password-field-row">
                        <label>Password Hotspot <span class="text-danger">*</span></label>
                        <input type="text" name="hotspot_password" id="hotspot-password-input" class="form-control @error('hotspot_password') is-invalid @enderror" value="{{ old('hotspot_password') }}" placeholder="Password">
                        @error('hotspot_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="form-group">
                    <label>Catatan</label>
                    <textarea name="catatan" class="form-control @error('catatan') is-invalid @enderror" rows="2">{{ old('catatan') }}</textarea>
                    @error('catatan')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="card-footer d-flex justify-content-between">
                <a href="{{ route('hotspot-users.index') }}" class="btn btn-secondary">Kembali</a>
                <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>

@push('scripts')
<script>
    $(function() {
        var $field = $('#customer_id');
        var $btnGen = $('#btn-generate-customer-id');
        var $btnUnlock = $('#btn-unlock-customer-id');
        var $hint = $('#customer-id-hint');
        var isLocked = true;

        function fetchCustomerId() {
            $btnGen.prop('disabled', true).find('i').addClass('fa-spin');
            $.get('{{ route('hotspot-users.generate-customer-id') }}', function(res) {
                $field.val(res.customer_id);
            }).always(function() {
                $btnGen.prop('disabled', false).find('i').removeClass('fa-spin');
            });
        }

        function setLocked(locked) {
            isLocked = locked;
            $field.prop('readonly', locked);
            if (locked) {
                $btnUnlock.html('<i class="fas fa-lock"></i>').attr('title', 'Edit manual');
                $btnGen.show();
                $hint.html('ID otomatis di-generate. Klik <i class="fas fa-lock"></i> untuk edit manual.');
            } else {
                $btnUnlock.html('<i class="fas fa-lock-open"></i>').attr('title', 'Kunci & generate otomatis');
                $btnGen.hide();
                $hint.text('Mode edit manual aktif.');
                $field.focus();
            }
        }

        if (!$field.val()) {
            fetchCustomerId();
        }

        $btnGen.on('click', fetchCustomerId);

        $btnUnlock.on('click', function() {
            if (isLocked) {
                setLocked(false);
            } else {
                setLocked(true);
                if (!$field.val()) fetchCustomerId();
            }
        });

        // Toggle password field berdasarkan metode login
        var $metodeSelect = $('#metode-login-select');
        var $passwordRow = $('#password-field-row');
        var $passwordInput = $('#hotspot-password-input');

        function togglePasswordField() {
            if ($metodeSelect.val() === 'username_equals_password') {
                $passwordRow.hide();
                $passwordInput.prop('required', false).val('');
            } else {
                $passwordRow.show();
                $passwordInput.prop('required', true);
            }
        }

        $metodeSelect.on('change', togglePasswordField);
        togglePasswordField();
    });
</script>
@endpush

@endsection
