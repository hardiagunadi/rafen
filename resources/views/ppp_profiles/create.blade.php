@extends('layouts.admin')

@section('title', 'Tambah Profil PPP')

@section('content')
    <div class="card">
        <div class="card-header">
            <h4 class="mb-0">Tambah Profil PPP</h4>
        </div>
        <form action="{{ route('ppp-profiles.store') }}" method="POST">
            @csrf
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Nama</label>
                        <input type="text" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group col-md-6">
                        <label>Owner Data</label>
                        <select name="owner_id" class="form-control @error('owner_id') is-invalid @enderror">
                            @foreach($owners as $owner)
                                <option value="{{ $owner->id }}" @selected(old('owner_id') == $owner->id)>{{ $owner->name }}</option>
                            @endforeach
                        </select>
                        @error('owner_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>Harga Modal</label>
                        <input type="number" step="0.01" name="harga_modal" value="{{ old('harga_modal', 0) }}" class="form-control @error('harga_modal') is-invalid @enderror">
                        @error('harga_modal')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group col-md-4">
                        <label>Harga Promo</label>
                        <input type="number" step="0.01" name="harga_promo" value="{{ old('harga_promo', 0) }}" class="form-control @error('harga_promo') is-invalid @enderror">
                        @error('harga_promo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group col-md-4">
                        <label>PPN (%)</label>
                        <input type="number" step="0.01" name="ppn" value="{{ old('ppn', 0) }}" class="form-control @error('ppn') is-invalid @enderror">
                        @error('ppn')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
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
                        <label>Bandwidth</label>
                        <select name="bandwidth_profile_id" class="form-control @error('bandwidth_profile_id') is-invalid @enderror">
                            <option value="">- pilih -</option>
                            @foreach($bandwidths as $bw)
                                <option value="{{ $bw->id }}" @selected(old('bandwidth_profile_id') == $bw->id)>{{ $bw->name }}</option>
                            @endforeach
                        </select>
                        @error('bandwidth_profile_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Masa Aktif</label>
                        <input type="number" name="masa_aktif" value="{{ old('masa_aktif', 1) }}" class="form-control @error('masa_aktif') is-invalid @enderror" min="1">
                        @error('masa_aktif')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group col-md-6">
                        <label>Satuan</label>
                        <select name="satuan" class="form-control @error('satuan') is-invalid @enderror">
                            <option value="bulan" @selected(old('satuan', 'bulan') === 'bulan')>Bulan</option>
                        </select>
                        @error('satuan')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-between">
                <a href="{{ route('ppp-profiles.index') }}" class="btn btn-link">Batal</a>
                <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
@endsection
