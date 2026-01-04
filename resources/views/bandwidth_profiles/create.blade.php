@extends('layouts.admin')

@section('title', 'Tambah Profil Bandwidth')

@section('content')
    <div class="card">
        <div class="card-header">
            <h4 class="mb-0">Tambah Profil Bandwidth</h4>
        </div>
        <form action="{{ route('bandwidth-profiles.store') }}" method="POST">
            @csrf
            <div class="card-body">
                <div class="form-group">
                    <label>Nama Bandwidth</label>
                    <input type="text" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Upload Min (Mbps)</label>
                        <input type="number" name="upload_min_mbps" value="{{ old('upload_min_mbps', 0) }}" class="form-control @error('upload_min_mbps') is-invalid @enderror" min="0">
                        @error('upload_min_mbps')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group col-md-6">
                        <label>Upload Max (Mbps)</label>
                        <input type="number" name="upload_max_mbps" value="{{ old('upload_max_mbps', 0) }}" class="form-control @error('upload_max_mbps') is-invalid @enderror" min="0">
                        @error('upload_max_mbps')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Download Min (Mbps)</label>
                        <input type="number" name="download_min_mbps" value="{{ old('download_min_mbps', 0) }}" class="form-control @error('download_min_mbps') is-invalid @enderror" min="0">
                        @error('download_min_mbps')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group col-md-6">
                        <label>Download Max (Mbps)</label>
                        <input type="number" name="download_max_mbps" value="{{ old('download_max_mbps', 0) }}" class="form-control @error('download_max_mbps') is-invalid @enderror" min="0">
                        @error('download_max_mbps')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="form-group">
                    <label>Owner Data</label>
                    <select name="owner" class="form-control @error('owner') is-invalid @enderror">
                        <option value="">- pilih -</option>
                        @foreach($users as $user)
                            <option value="{{ $user->name }}" @selected(old('owner') === $user->name)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                    @error('owner')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="card-footer d-flex justify-content-between">
                <a href="{{ route('bandwidth-profiles.index') }}" class="btn btn-link">Batal</a>
                <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
@endsection
