@extends('layouts.admin')

@section('title', 'Edit Paket: ' . $plan->name)

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Edit Paket Langganan</h3>
            </div>
            <form action="{{ route('super-admin.subscription-plans.update', $plan) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label>Nama Paket <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $plan->name) }}" required>
                                @error('name')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Urutan</label>
                                <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', $plan->sort_order) }}" min="0">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Deskripsi</label>
                        <textarea name="description" class="form-control" rows="3">{{ old('description', $plan->description) }}</textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Harga (Rp) <span class="text-danger">*</span></label>
                                <input type="number" name="price" class="form-control @error('price') is-invalid @enderror" value="{{ old('price', $plan->price) }}" min="0" required>
                                @error('price')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Durasi (hari) <span class="text-danger">*</span></label>
                                <input type="number" name="duration_days" class="form-control @error('duration_days') is-invalid @enderror" value="{{ old('duration_days', $plan->duration_days) }}" min="1" required>
                                @error('duration_days')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Max Mikrotik <span class="text-danger">*</span></label>
                                <input type="number" name="max_mikrotik" class="form-control" value="{{ old('max_mikrotik', $plan->max_mikrotik) }}" min="-1" required>
                                <small class="text-muted">-1 untuk unlimited</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Max PPP Users <span class="text-danger">*</span></label>
                                <input type="number" name="max_ppp_users" class="form-control" value="{{ old('max_ppp_users', $plan->max_ppp_users) }}" min="-1" required>
                                <small class="text-muted">-1 untuk unlimited</small>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Fitur (satu per baris)</label>
                        <textarea name="features_text" class="form-control" rows="4">{{ old('features_text', is_array($plan->features) ? implode("\n", $plan->features) : '') }}</textarea>
                        <small class="text-muted">Masukkan satu fitur per baris</small>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" value="1" {{ old('is_active', $plan->is_active) ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="is_active">Aktif</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="is_featured" name="is_featured" value="1" {{ old('is_featured', $plan->is_featured) ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="is_featured">Featured (Tampil Menonjol)</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="{{ route('super-admin.subscription-plans.index') }}" class="btn btn-secondary">
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
