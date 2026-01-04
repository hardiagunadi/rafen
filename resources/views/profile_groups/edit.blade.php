@extends('layouts.admin')

@section('title', 'Edit Profil Group')

@section('content')
    <div class="card">
        <div class="card-header">
            <h4 class="mb-0">Edit Profil Group</h4>
        </div>
        <form action="{{ route('profile-groups.update', $profileGroup) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Nama Group</label>
                        <input type="text" name="name" value="{{ old('name', $profileGroup->name) }}" class="form-control @error('name') is-invalid @enderror" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group col-md-6">
                        <label>Owner Data</label>
                        <select name="owner" class="form-control @error('owner') is-invalid @enderror">
                            <option value="">- pilih -</option>
                            @foreach($users as $user)
                                <option value="{{ $user->name }}" @selected(old('owner', $profileGroup->owner) === $user->name)>{{ $user->name }}</option>
                            @endforeach
                        </select>
                        @error('owner')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Router (NAS)</label>
                        <select name="mikrotik_connection_id" class="form-control @error('mikrotik_connection_id') is-invalid @enderror">
                            <option value="">Semua Router (NAS)</option>
                            @foreach($mikrotikConnections as $conn)
                                <option value="{{ $conn->id }}" @selected(old('mikrotik_connection_id', $profileGroup->mikrotik_connection_id) == $conn->id)>{{ $conn->name }}</option>
                            @endforeach
                        </select>
                        @error('mikrotik_connection_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group col-md-6">
                        <label>Tipe</label>
                        <select name="type" class="form-control @error('type') is-invalid @enderror">
                            <option value="hotspot" @selected(old('type', $profileGroup->type) === 'hotspot')>Hotspot</option>
                            <option value="pppoe" @selected(old('type', $profileGroup->type) === 'pppoe')>PPPoE</option>
                        </select>
                        @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Modul IP Pool</label>
                        <select name="ip_pool_mode" class="form-control @error('ip_pool_mode') is-invalid @enderror">
                            <option value="group_only" @selected(old('ip_pool_mode', $profileGroup->ip_pool_mode) === 'group_only')>Group Only (Mikrotik)</option>
                            <option value="sql" @selected(old('ip_pool_mode', $profileGroup->ip_pool_mode) === 'sql')>SQL IP Pool</option>
                        </select>
                        @error('ip_pool_mode')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group col-md-6">
                        <label>IP Pool Mikrotik (nama)</label>
                        <input type="text" name="ip_pool_name" value="{{ old('ip_pool_name', $profileGroup->ip_pool_name) }}" class="form-control @error('ip_pool_name') is-invalid @enderror" placeholder="Pool Mikrotik">
                        @error('ip_pool_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Range IP Awal</label>
                        <input type="text" name="range_start" value="{{ old('range_start', $profileGroup->range_start) }}" class="form-control @error('range_start') is-invalid @enderror" placeholder="10.0.0.2">
                        @error('range_start')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group col-md-6">
                        <label>Range IP Akhir</label>
                        <input type="text" name="range_end" value="{{ old('range_end', $profileGroup->range_end) }}" class="form-control @error('range_end') is-invalid @enderror" placeholder="10.0.0.254">
                        @error('range_end')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>IP Address (SQL IP Pool)</label>
                        <input type="text" name="ip_address" value="{{ old('ip_address', $profileGroup->ip_address) }}" class="form-control @error('ip_address') is-invalid @enderror" placeholder="10.0.1.0">
                        @error('ip_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group col-md-6">
                        <label>Netmask (SQL IP Pool)</label>
                        <input type="text" name="netmask" value="{{ old('netmask', $profileGroup->netmask) }}" class="form-control @error('netmask') is-invalid @enderror" placeholder="255.255.255.0 atau 24">
                        @error('netmask')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>DNS Server</label>
                        <input type="text" name="dns_servers" value="{{ old('dns_servers', $profileGroup->dns_servers ?? '8.8.8.8,8.8.4.4') }}" class="form-control @error('dns_servers') is-invalid @enderror" placeholder="8.8.8.8,8.8.4.4">
                        @error('dns_servers')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group col-md-6">
                        <label>Parent Queue</label>
                        <input type="text" name="parent_queue" value="{{ old('parent_queue', $profileGroup->parent_queue) }}" class="form-control @error('parent_queue') is-invalid @enderror" placeholder="Parent Queue">
                        @error('parent_queue')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-between">
                <a href="{{ route('profile-groups.index') }}" class="btn btn-link">Batal</a>
                <button type="submit" class="btn btn-primary">Update</button>
            </div>
        </form>
    </div>
@endsection
