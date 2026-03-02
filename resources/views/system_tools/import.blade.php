@extends('layouts.admin')

@section('title', 'Impor User')

@section('content')
<div class="row">
    <div class="col-md-7">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">Impor User dari CSV</h4>
            </div>
            <div class="card-body">
                @if(session('status'))
                    <div class="alert alert-success">{{ session('status') }}</div>
                @endif

                <form id="import-form" enctype="multipart/form-data">
                    @csrf
                    <div class="form-group">
                        <label>Tipe User</label>
                        <select name="type" id="import-type" class="form-control">
                            <option value="ppp">PPP Users</option>
                            <option value="hotspot">Hotspot Users</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>File CSV</label>
                        <div class="custom-file">
                            <input type="file" class="custom-file-input" id="csv-file" name="file" accept=".csv,.txt" required>
                            <label class="custom-file-label" for="csv-file">Pilih file CSV...</label>
                        </div>
                        <small class="text-muted">Maksimal 5 MB. Format: UTF-8 tanpa BOM.</small>
                    </div>
                    <button type="submit" class="btn btn-primary" id="btn-import">
                        <i class="fas fa-upload mr-1"></i>Impor
                    </button>
                </form>

                <div id="import-result" class="mt-3" style="display:none;"></div>
            </div>
        </div>
    </div>

    <div class="col-md-5">
        <div class="card">
            <div class="card-header"><h5 class="mb-0">Template CSV</h5></div>
            <div class="card-body">
                <p class="text-muted small">Gunakan kolom berikut di baris pertama (header). Kolom <strong>username</strong> dan <strong>customer_name</strong> wajib diisi.</p>

                <div id="template-ppp">
                    <strong>PPP Users (Format Rafen):</strong>
                    <pre class="bg-light p-2 small mt-1">customer_id,customer_name,nik,nomor_hp,email,alamat,username,ppp_password,status_akun,status_bayar,jatuh_tempo,tipe_service,catatan</pre>
                    <a href="{{ route('tools.import.template', 'ppp') }}" class="btn btn-sm btn-outline-secondary mb-3">
                        <i class="fas fa-download mr-1"></i>Download Template PPP
                    </a>

                    <hr>
                    <strong>PPP Users (Format Export MixRadius):</strong>
                    <p class="text-muted small mb-1">Upload langsung file CSV hasil export dari MixRadius. Sistem akan mendeteksi otomatis berdasarkan header <code>Login</code> dan <code>FullName</code>.</p>
                    <p class="text-muted small mb-0">Kolom yang digunakan: <code>Login, Password, FullName, CustomerId, Email, IdCard, Phone, Address, Latitude, Longitude, ExpiredAction, Plan, PaymentStatus, AuthStatus, Expired, Note</code></p>
                    <ul class="text-muted small mt-1 mb-0 pl-3">
                        <li>Kolom <strong>Plan</strong> akan dicocokkan dengan nama Paket PPP yang ada di sistem</li>
                        <li>Kolom <strong>AuthStatus</strong>: 1=enable, 0=disable</li>
                        <li>Kolom <strong>PaymentStatus</strong>: 1=sudah bayar, 0/kosong=belum bayar</li>
                        <li>Kolom <strong>ExpiredAction</strong>: isolir → isolir, lainnya → tetap terhubung</li>
                    </ul>
                </div>

                <div id="template-hotspot" style="display:none;">
                    <strong>Hotspot Users:</strong>
                    <pre class="bg-light p-2 small mt-1">customer_id,customer_name,nik,nomor_hp,email,alamat,username,hotspot_password,status_akun,status_bayar,jatuh_tempo,catatan</pre>
                    <a href="{{ route('tools.import.template', 'hotspot') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-download mr-1"></i>Download Template Hotspot
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    // Toggle template based on type
    document.getElementById('import-type').addEventListener('change', function () {
        document.getElementById('template-ppp').style.display = this.value === 'ppp' ? '' : 'none';
        document.getElementById('template-hotspot').style.display = this.value === 'hotspot' ? '' : 'none';
    });

    // Custom file input label
    document.getElementById('csv-file').addEventListener('change', function () {
        var label = this.nextElementSibling;
        label.textContent = this.files[0] ? this.files[0].name : 'Pilih file CSV...';
    });

    document.getElementById('import-form').addEventListener('submit', function (e) {
        e.preventDefault();
        var btn    = document.getElementById('btn-import');
        var result = document.getElementById('import-result');

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Mengimpor...';
        result.style.display = 'none';

        var fd = new FormData(this);

        fetch('{{ route("tools.import.store") }}', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': fd.get('_token') },
            body: fd,
        })
        .then(function (r) {
            return r.json().then(function (data) {
                return { ok: r.ok, status: r.status, data: data };
            });
        })
        .then(function (res) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-upload mr-1"></i>Impor';
            result.style.display = '';

            if (!res.ok) {
                // Handle validation errors (422) or other server errors
                var msg = res.data.message || res.data.error || 'Terjadi kesalahan server.';
                if (res.data.errors) {
                    var errs = Object.values(res.data.errors).flat();
                    msg = errs.join('<br>');
                }
                result.innerHTML = '<div class="alert alert-danger">' + msg + '</div>';
                return;
            }

            if (res.data.error) {
                result.innerHTML = '<div class="alert alert-danger">' + res.data.error + '</div>';
                return;
            }

            var html = '<div class="alert alert-success">Berhasil mengimpor <strong>' + res.data.inserted + '</strong> data.</div>';
            if (res.data.errors && res.data.errors.length > 0) {
                html += '<div class="alert alert-warning"><strong>' + res.data.errors.length + ' baris gagal:</strong><ul class="mb-0 mt-1">';
                res.data.errors.forEach(function (e) { html += '<li>' + e + '</li>'; });
                html += '</ul></div>';
            }
            result.innerHTML = html;
        })
        .catch(function (err) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-upload mr-1"></i>Impor';
            result.style.display = '';
            result.innerHTML = '<div class="alert alert-danger">Terjadi kesalahan koneksi. Coba lagi.</div>';
        });
    });
})();
</script>
@endsection
