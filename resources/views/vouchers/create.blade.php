@extends('layouts.admin')

@section('title', 'Generate Voucher')

@section('content')
    <div class="card">
        <div class="card-header">
            <h4 class="mb-0">Generate Batch Voucher</h4>
        </div>
        <form action="{{ route('vouchers.store') }}" method="POST" novalidate>
            @csrf
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Profil Hotspot <span class="text-danger">*</span></label>
                        <select name="hotspot_profile_id" class="form-control @error('hotspot_profile_id') is-invalid @enderror" required>
                            <option value="" disabled @selected(! old('hotspot_profile_id'))>- pilih profil -</option>
                            @foreach($profiles as $profile)
                                <option value="{{ $profile->id }}" @selected(old('hotspot_profile_id') == $profile->id)>
                                    {{ $profile->name }}
                                    @if($profile->harga_jual > 0)
                                        (Rp {{ number_format($profile->harga_jual, 0, ',', '.') }})
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        @error('hotspot_profile_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group col-md-6">
                        <label>Nama Batch <span class="text-danger">*</span></label>
                        <input type="text" name="batch_name" class="form-control @error('batch_name') is-invalid @enderror"
                            value="{{ old('batch_name') }}" placeholder="Contoh: Voucher-2rb-Jan2026" required>
                        <small class="form-text text-muted">Nama untuk mengelompokkan voucher ini.</small>
                        @error('batch_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>Jumlah Voucher <span class="text-danger">*</span></label>
                        <input type="number" name="jumlah" class="form-control @error('jumlah') is-invalid @enderror"
                            value="{{ old('jumlah', 10) }}" min="1" max="1000" required>
                        <small class="form-text text-muted">Maksimal 1000 per sekali generate.</small>
                        @error('jumlah')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    Kode voucher akan digenerate otomatis (8 karakter alfanumerik uppercase).
                    Kode yang sama dengan kode yang sudah ada akan dilewati.
                </div>
            </div>

            <div class="card-footer d-flex justify-content-between">
                <a href="{{ route('vouchers.index') }}" class="btn btn-secondary">Kembali</a>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-magic"></i> Generate Voucher
                </button>
            </div>
        </form>
    </div>
@endsection
