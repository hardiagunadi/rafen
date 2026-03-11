@extends('layouts.admin')

@section('title', 'Tambah Tenant Baru')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Form Tenant Baru</h3>
            </div>
            <form action="{{ route('super-admin.tenants.store') }}" method="POST">
                @csrf
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Nama Lengkap <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                                @error('name')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Email <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required>
                                @error('email')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Password <span class="text-danger">*</span></label>
                                <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
                                @error('password')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Konfirmasi Password <span class="text-danger">*</span></label>
                                <input type="password" name="password_confirmation" class="form-control" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Telepon</label>
                                <input type="text" name="phone" class="form-control" value="{{ old('phone') }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Nama Perusahaan</label>
                                <input type="text" name="company_name" class="form-control" value="{{ old('company_name') }}">
                            </div>
                        </div>
                    </div>

                    <hr>
                    <h5>Pengaturan Langganan</h5>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label id="subscription_plan_label">Paket Langganan</label>
                                <select name="subscription_plan_id" id="subscription_plan_id" class="form-control">
                                    <option value="">- Tidak ada (Trial) -</option>
                                    @foreach($plans as $plan)
                                    <option value="{{ $plan->id }}"
                                        data-plan-name="{{ $plan->name }}"
                                        data-plan-price="{{ number_format($plan->price, 0, ',', '.') }}"
                                        data-plan-duration="{{ $plan->duration_days }}"
                                        {{ (string) old('subscription_plan_id') === (string) $plan->id ? 'selected' : '' }}>
                                        {{ $plan->name }} - Rp {{ number_format($plan->price, 0, ',', '.') }} - {{ $plan->duration_days }} hari
                                    </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Daftar paket lisensi mengambil data dari menu Kelola Paket Langganan.</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Metode Langganan</label>
                                <select name="subscription_method" id="subscription_method" class="form-control @error('subscription_method') is-invalid @enderror">
                                    <option value="monthly" {{ old('subscription_method', 'monthly') === 'monthly' ? 'selected' : '' }}>Bulanan</option>
                                    <option value="license" {{ old('subscription_method') === 'license' ? 'selected' : '' }}>Lisensi (Tahunan)</option>
                                </select>
                                @error('subscription_method')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                                <small class="text-muted">Lisensi otomatis berlaku 1 tahun.</small>
                            </div>
                        </div>
                    </div>

                    <div id="license_limit_fields" class="row d-none">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Limit Mikrotik (Lisensi)</label>
                                <input type="number" name="license_max_mikrotik" class="form-control @error('license_max_mikrotik') is-invalid @enderror" value="{{ old('license_max_mikrotik') }}" min="-1">
                                @error('license_max_mikrotik')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                                <small class="text-muted">Isi <code>-1</code> untuk tanpa batas.</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Limit PPP Users (Lisensi)</label>
                                <input type="number" name="license_max_ppp_users" class="form-control @error('license_max_ppp_users') is-invalid @enderror" value="{{ old('license_max_ppp_users') }}" min="-1">
                                @error('license_max_ppp_users')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                                <small class="text-muted">Isi <code>-1</code> untuk tanpa batas.</small>
                            </div>
                        </div>
                    </div>

                    <div class="row" id="trial_days_field">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Masa Trial (hari)</label>
                                <input type="number" name="trial_days" class="form-control" value="{{ old('trial_days', 14) }}" min="0" max="90">
                                <small class="text-muted">Berlaku jika tidak memilih paket</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="{{ route('super-admin.tenants') }}" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                    <button type="submit" class="btn btn-primary float-right">
                        <i class="fas fa-save"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function toggleLicenseLimitFields() {
    var method = document.getElementById('subscription_method');
    var wrapper = document.getElementById('license_limit_fields');
    var planLabel = document.getElementById('subscription_plan_label');
    var planSelect = document.getElementById('subscription_plan_id');
    var trialDaysField = document.getElementById('trial_days_field');
    var trialDaysInput = document.querySelector('input[name="trial_days"]');
    if (!method || !wrapper) {
        return;
    }

    if (planSelect) {
        var isLicensePlan = method.value === 'license';
        var emptyOption = planSelect.querySelector('option[value=""]');
        if (emptyOption) {
            emptyOption.textContent = isLicensePlan
                ? '- Tidak ada paket lisensi -'
                : '- Tidak ada (Trial) -';
        }
        Array.from(planSelect.options).forEach(function (option) {
            var planName = option.getAttribute('data-plan-name');
            if (!planName) {
                return;
            }
            var planPrice = option.getAttribute('data-plan-price') || '0';
            var planDuration = option.getAttribute('data-plan-duration') || '30';
            var durationLabel = isLicensePlan
                ? '{{ \App\Models\User::LICENSE_DURATION_DAYS }} hari (Lisensi)'
                : planDuration + ' hari';
            option.textContent = planName + ' - Rp ' + planPrice + ' - ' + durationLabel;
        });
    }

    if (method.value === 'license') {
        if (planLabel) {
            planLabel.textContent = 'Paket Lisensi';
        }
        wrapper.classList.remove('d-none');
        if (trialDaysField) {
            trialDaysField.classList.add('d-none');
        }
        if (trialDaysInput) {
            trialDaysInput.value = '0';
        }
    } else {
        if (planLabel) {
            planLabel.textContent = 'Paket Langganan';
        }
        wrapper.classList.add('d-none');
        if (trialDaysField) {
            trialDaysField.classList.remove('d-none');
        }
    }
}

document.addEventListener('DOMContentLoaded', function () {
    var method = document.getElementById('subscription_method');
    if (!method) {
        return;
    }
    method.addEventListener('change', toggleLicenseLimitFields);
    toggleLicenseLimitFields();
});
</script>
@endpush
