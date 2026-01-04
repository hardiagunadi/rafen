@extends('layouts.admin')

@section('title', 'Edit Akun PPPoE / Hotspot')

@section('content')
    <div class="card">
        <div class="card-header">Ubah Akun</div>
        <form action="{{ route('radius-accounts.update', $radiusAccount) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Username</label>
                        <input type="text" name="username" value="{{ old('username', $radiusAccount->username) }}" class="form-control @error('username') is-invalid @enderror" required>
                        @error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group col-md-6">
                        <label>Password</label>
                        <input type="text" name="password" value="{{ old('password', $radiusAccount->password) }}" class="form-control @error('password') is-invalid @enderror" required>
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>Layanan</label>
                        <select name="service" class="form-control @error('service') is-invalid @enderror">
                            <option value="pppoe" @selected(old('service', $radiusAccount->service) === 'pppoe')>PPPoE</option>
                            <option value="hotspot" @selected(old('service', $radiusAccount->service) === 'hotspot')>Hotspot</option>
                        </select>
                        @error('service')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group col-md-4">
                        <label>IP PPPoE</label>
                        <input type="text" name="ipv4_address" value="{{ old('ipv4_address', $radiusAccount->ipv4_address) }}" class="form-control @error('ipv4_address') is-invalid @enderror" placeholder="Hanya untuk PPPoE">
                        @error('ipv4_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group col-md-4">
                        <label>Rate Limit (mis. 10M/10M)</label>
                        <input type="text" name="rate_limit" value="{{ old('rate_limit', $radiusAccount->rate_limit) }}" class="form-control @error('rate_limit') is-invalid @enderror">
                        @error('rate_limit')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>Profile (Hotspot / PPPoE)</label>
                        <input type="text" name="profile" value="{{ old('profile', $radiusAccount->profile) }}" class="form-control @error('profile') is-invalid @enderror">
                        @error('profile')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group col-md-4">
                        <label>Koneksi Mikrotik</label>
                        <select name="mikrotik_connection_id" class="form-control @error('mikrotik_connection_id') is-invalid @enderror">
                            <option value="">- Opsional -</option>
                            @foreach($mikrotikConnections as $connection)
                                <option value="{{ $connection->id }}" @selected(old('mikrotik_connection_id', $radiusAccount->mikrotik_connection_id) == $connection->id)>{{ $connection->name }}</option>
                            @endforeach
                        </select>
                        @error('mikrotik_connection_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group col-md-4">
                        <label>Catatan</label>
                        <input type="text" name="notes" value="{{ old('notes', $radiusAccount->notes) }}" class="form-control @error('notes') is-invalid @enderror">
                        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" @checked(old('is_active', $radiusAccount->is_active))>
                            <label class="form-check-label" for="is_active">Aktif</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-between">
                <a href="{{ route('radius-accounts.index') }}" class="btn btn-secondary">Kembali</a>
                <div>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </div>
        </form>
    </div>
@endsection
