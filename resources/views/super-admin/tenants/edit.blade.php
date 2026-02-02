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
                                <select name="subscription_status" class="form-control">
                                    <option value="trial" {{ $tenant->subscription_status === 'trial' ? 'selected' : '' }}>Trial</option>
                                    <option value="active" {{ $tenant->subscription_status === 'active' ? 'selected' : '' }}>Aktif</option>
                                    <option value="expired" {{ $tenant->subscription_status === 'expired' ? 'selected' : '' }}>Berakhir</option>
                                    <option value="suspended" {{ $tenant->subscription_status === 'suspended' ? 'selected' : '' }}>Suspend</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Paket Langganan</label>
                                <select name="subscription_plan_id" class="form-control">
                                    <option value="">- Tidak ada -</option>
                                    @foreach($plans as $plan)
                                    <option value="{{ $plan->id }}" {{ $tenant->subscription_plan_id == $plan->id ? 'selected' : '' }}>
                                        {{ $plan->name }}
                                    </option>
                                    @endforeach
                                </select>
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
                        <div class="col-md-6">
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
