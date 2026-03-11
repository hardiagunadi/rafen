@extends('layouts.admin')

@section('title', 'Edit Tenant: ' . $tenant->name)

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Edit Data Tenant</h3>
            </div>
            <form action="{{ route('super-admin.tenants.update', $tenant) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Nama Lengkap <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $tenant->name) }}" required>
                                @error('name')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Email <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $tenant->email) }}" required>
                                @error('email')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Telepon</label>
                                <input type="text" name="phone" class="form-control" value="{{ old('phone', $tenant->phone) }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Nama Perusahaan</label>
                                <input type="text" name="company_name" class="form-control" value="{{ old('company_name', $tenant->company_name) }}">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Alamat</label>
                        <textarea name="address" class="form-control" rows="2">{{ old('address', $tenant->address) }}</textarea>
                    </div>

                    <hr>
                    <h5>Pengaturan Langganan</h5>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Status Langganan</label>
                                <select name="subscription_status" id="subscription_status" class="form-control">
                                    <option value="trial" {{ old('subscription_status', $tenant->subscription_status) === 'trial' ? 'selected' : '' }}>Trial</option>
                                    <option value="active" {{ old('subscription_status', $tenant->subscription_status) === 'active' ? 'selected' : '' }}>Aktif</option>
                                    <option value="expired" {{ old('subscription_status', $tenant->subscription_status) === 'expired' ? 'selected' : '' }}>Berakhir</option>
                                    <option value="suspended" {{ old('subscription_status', $tenant->subscription_status) === 'suspended' ? 'selected' : '' }}>Suspend</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Metode Langganan</label>
                                <select name="subscription_method" id="subscription_method" class="form-control @error('subscription_method') is-invalid @enderror">
                                    <option value="monthly" {{ old('subscription_method', $tenant->subscription_method ?? 'monthly') === 'monthly' ? 'selected' : '' }}>Bulanan</option>
                                    <option value="license" {{ old('subscription_method', $tenant->subscription_method) === 'license' ? 'selected' : '' }}>Lisensi (Tahunan)</option>
                                </select>
                                @error('subscription_method')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div id="license_limit_fields" class="row d-none">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Limit Mikrotik (Lisensi)</label>
                                <input type="number" name="license_max_mikrotik" class="form-control @error('license_max_mikrotik') is-invalid @enderror" value="{{ old('license_max_mikrotik', $tenant->license_max_mikrotik) }}" min="-1">
                                @error('license_max_mikrotik')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                                <small class="text-muted">Isi <code>-1</code> untuk tanpa batas.</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Limit PPP Users (Lisensi)</label>
                                <input type="number" name="license_max_ppp_users" class="form-control @error('license_max_ppp_users') is-invalid @enderror" value="{{ old('license_max_ppp_users', $tenant->license_max_ppp_users) }}" min="-1">
                                @error('license_max_ppp_users')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                                <small class="text-muted">Isi <code>-1</code> untuk tanpa batas.</small>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label id="subscription_plan_label">Paket Langganan</label>
                                <select name="subscription_plan_id" id="subscription_plan_id" class="form-control">
                                    <option value="">- Tidak ada -</option>
                                    @foreach($plans as $plan)
                                    <option value="{{ $plan->id }}"
                                        data-plan-name="{{ $plan->name }}"
                                        data-plan-price="{{ number_format($plan->price, 0, ',', '.') }}"
                                        data-plan-duration="{{ $plan->duration_days }}"
                                        {{ (string) old('subscription_plan_id', $tenant->subscription_plan_id) === (string) $plan->id ? 'selected' : '' }}>
                                        {{ $plan->name }} - Rp {{ number_format($plan->price, 0, ',', '.') }} - {{ $plan->duration_days }} hari
                                    </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Daftar paket lisensi mengambil data dari menu Kelola Paket Langganan.</small>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Tanggal Berakhir</label>
                                <input type="date" name="subscription_expires_at" class="form-control" value="{{ old('subscription_expires_at', $tenant->subscription_expires_at?->format('Y-m-d')) }}">
                            </div>
                        </div>
                        <div class="col-md-6" id="trial_days_remaining_field">
                            <div class="form-group">
                                <label>Sisa Hari Trial</label>
                                <input type="number" name="trial_days_remaining" class="form-control" value="{{ old('trial_days_remaining', $tenant->trial_days_remaining) }}" min="0">
                            </div>
                        </div>
                    </div>

                    <hr>
                    <h5>Pengaturan VPN</h5>

                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="vpn_enabled" name="vpn_enabled" value="1" {{ $tenant->vpn_enabled ? 'checked' : '' }}>
                            <label class="custom-control-label" for="vpn_enabled">VPN Aktif</label>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>VPN Username</label>
                                <input type="text" name="vpn_username" class="form-control" value="{{ old('vpn_username', $tenant->vpn_username) }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>VPN Password</label>
                                <input type="text" name="vpn_password" class="form-control" value="{{ old('vpn_password', $tenant->vpn_password) }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>VPN IP</label>
                                <input type="text" name="vpn_ip" class="form-control" value="{{ old('vpn_ip', $tenant->vpn_ip) }}">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="{{ route('super-admin.tenants.show', $tenant) }}" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                    <button type="submit" class="btn btn-primary float-right">
                        <i class="fas fa-save"></i> Simpan Perubahan
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
    var status = document.getElementById('subscription_status');
    var trialOption = status ? status.querySelector('option[value="trial"]') : null;
    var trialDaysField = document.getElementById('trial_days_remaining_field');
    var trialDaysInput = document.querySelector('input[name="trial_days_remaining"]');
    if (!method || !wrapper) {
        return;
    }

    if (planSelect) {
        var isLicensePlan = method.value === 'license';
        var emptyOption = planSelect.querySelector('option[value=""]');
        if (emptyOption) {
            emptyOption.textContent = isLicensePlan
                ? '- Tidak ada paket lisensi -'
                : '- Tidak ada -';
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
        if (trialOption) {
            trialOption.disabled = true;
        }
        if (status && status.value === 'trial') {
            status.value = 'active';
        }
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
        if (trialOption) {
            trialOption.disabled = false;
        }
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
