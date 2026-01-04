@extends('layouts.admin')

@section('title', 'Edit Profil Hotspot')

@section('content')
    @php
        $profileType = old('profile_type', $hotspotProfile->profile_type);
        $limitType = old('limit_type', $hotspotProfile->limit_type ?? 'time');
    @endphp
    <div class="card">
        <div class="card-header">
            <h4 class="mb-0">Edit Profil Hotspot</h4>
        </div>
        <form action="{{ route('hotspot-profiles.update', $hotspotProfile) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Nama</label>
                        <input type="text" name="name" value="{{ old('name', $hotspotProfile->name) }}" class="form-control @error('name') is-invalid @enderror" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group col-md-6">
                        <label>Owner Data</label>
                        <select name="owner_id" class="form-control @error('owner_id') is-invalid @enderror" required>
                            @foreach($owners as $owner)
                                <option value="{{ $owner->id }}" @selected(old('owner_id', $hotspotProfile->owner_id) == $owner->id)>{{ $owner->name }}</option>
                            @endforeach
                        </select>
                        @error('owner_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>Harga Jual</label>
                        <input type="number" step="0.01" name="harga_jual" value="{{ old('harga_jual', $hotspotProfile->harga_jual) }}" class="form-control @error('harga_jual') is-invalid @enderror">
                        @error('harga_jual')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group col-md-4">
                        <label>Harga Promo</label>
                        <input type="number" step="0.01" name="harga_promo" value="{{ old('harga_promo', $hotspotProfile->harga_promo) }}" class="form-control @error('harga_promo') is-invalid @enderror">
                        @error('harga_promo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group col-md-4">
                        <label>PPN (%)</label>
                        <input type="number" step="0.01" name="ppn" value="{{ old('ppn', $hotspotProfile->ppn) }}" class="form-control @error('ppn') is-invalid @enderror">
                        @error('ppn')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Bandwidth</label>
                        <select name="bandwidth_profile_id" class="form-control @error('bandwidth_profile_id') is-invalid @enderror">
                            <option value="">- pilih -</option>
                            @foreach($bandwidths as $bw)
                                <option value="{{ $bw->id }}" @selected(old('bandwidth_profile_id', $hotspotProfile->bandwidth_profile_id) == $bw->id)>{{ $bw->name }}</option>
                            @endforeach
                        </select>
                        @error('bandwidth_profile_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group col-md-6">
                        <label>Profil Group</label>
                        <select name="profile_group_id" class="form-control @error('profile_group_id') is-invalid @enderror">
                            <option value="">- pilih -</option>
                            @foreach($groups as $group)
                                <option value="{{ $group->id }}" @selected(old('profile_group_id', $hotspotProfile->profile_group_id) == $group->id)>{{ $group->name }}</option>
                            @endforeach
                        </select>
                        @error('profile_group_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="mb-3">
                    <label class="d-block">Tipe Profil</label>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="profile_type" id="profile-type-unlimited" value="unlimited" @checked(old('profile_type', $hotspotProfile->profile_type) === 'unlimited')>
                        <label class="form-check-label" for="profile-type-unlimited">Unlimited</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="profile_type" id="profile-type-limited" value="limited" @checked(old('profile_type', $hotspotProfile->profile_type) === 'limited')>
                        <label class="form-check-label" for="profile-type-limited">Limited</label>
                    </div>
                    @error('profile_type')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>

                <div id="limit-type-section" class="mb-3" style="{{ $profileType === 'limited' ? '' : 'display:none;' }}">
                    <label class="d-block">Tipe Limit</label>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="limit_type" id="limit-type-time" value="time" @checked(old('limit_type', $hotspotProfile->limit_type ?? 'time') === 'time')>
                        <label class="form-check-label" for="limit-type-time">TimeBase</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="limit_type" id="limit-type-quota" value="quota" @checked(old('limit_type', $hotspotProfile->limit_type) === 'quota')>
                        <label class="form-check-label" for="limit-type-quota">QuotaBase</label>
                    </div>
                    @error('limit_type')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>

                <div id="time-limit-section" class="form-row" style="{{ $profileType === 'limited' && $limitType === 'time' ? '' : 'display:none;' }}">
                    <div class="form-group col-md-6">
                        <label>TimeBase</label>
                        <input type="number" min="1" name="time_limit_value" value="{{ old('time_limit_value', $hotspotProfile->time_limit_value) }}" class="form-control @error('time_limit_value') is-invalid @enderror">
                        @error('time_limit_value')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group col-md-6">
                        <label>Satuan</label>
                        <select name="time_limit_unit" class="form-control @error('time_limit_unit') is-invalid @enderror">
                            <option value="menit" @selected(old('time_limit_unit', $hotspotProfile->time_limit_unit) === 'menit')>Menit</option>
                            <option value="jam" @selected(old('time_limit_unit', $hotspotProfile->time_limit_unit) === 'jam')>Jam</option>
                            <option value="hari" @selected(old('time_limit_unit', $hotspotProfile->time_limit_unit) === 'hari')>Hari</option>
                            <option value="bulan" @selected(old('time_limit_unit', $hotspotProfile->time_limit_unit) === 'bulan')>Bulan</option>
                        </select>
                        @error('time_limit_unit')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div id="quota-limit-section" class="form-row" style="{{ $profileType === 'limited' && $limitType === 'quota' ? '' : 'display:none;' }}">
                    <div class="form-group col-md-6">
                        <label>QuotaBase</label>
                        <input type="number" min="1" step="0.01" name="quota_limit_value" value="{{ old('quota_limit_value', $hotspotProfile->quota_limit_value) }}" class="form-control @error('quota_limit_value') is-invalid @enderror">
                        @error('quota_limit_value')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group col-md-6">
                        <label>Satuan</label>
                        <select name="quota_limit_unit" class="form-control @error('quota_limit_unit') is-invalid @enderror">
                            <option value="mb" @selected(old('quota_limit_unit', $hotspotProfile->quota_limit_unit) === 'mb')>MB</option>
                            <option value="gb" @selected(old('quota_limit_unit', $hotspotProfile->quota_limit_unit) === 'gb')>GB</option>
                        </select>
                        @error('quota_limit_unit')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div id="masa-aktif-section" class="form-row" style="{{ $profileType === 'unlimited' ? '' : 'display:none;' }}">
                    <div class="form-group col-md-6">
                        <label>Masa Aktif</label>
                        <input type="number" min="1" name="masa_aktif_value" value="{{ old('masa_aktif_value', $hotspotProfile->masa_aktif_value) }}" class="form-control @error('masa_aktif_value') is-invalid @enderror">
                        @error('masa_aktif_value')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group col-md-6">
                        <label>Satuan</label>
                        <select name="masa_aktif_unit" class="form-control @error('masa_aktif_unit') is-invalid @enderror">
                            <option value="menit" @selected(old('masa_aktif_unit', $hotspotProfile->masa_aktif_unit) === 'menit')>Menit</option>
                            <option value="jam" @selected(old('masa_aktif_unit', $hotspotProfile->masa_aktif_unit) === 'jam')>Jam</option>
                            <option value="hari" @selected(old('masa_aktif_unit', $hotspotProfile->masa_aktif_unit) === 'hari')>Hari</option>
                            <option value="bulan" @selected(old('masa_aktif_unit', $hotspotProfile->masa_aktif_unit) === 'bulan')>Bulan</option>
                        </select>
                        @error('masa_aktif_unit')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Shared Users</label>
                        <input type="number" min="1" name="shared_users" value="{{ old('shared_users', $hotspotProfile->shared_users) }}" class="form-control @error('shared_users') is-invalid @enderror">
                        @error('shared_users')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group col-md-6">
                        <label>Prioritas</label>
                        <select name="prioritas" class="form-control @error('prioritas') is-invalid @enderror">
                            <option value="default" @selected(old('prioritas', $hotspotProfile->prioritas) === 'default')>Default</option>
                            @for($i = 1; $i <= 8; $i++)
                                <option value="prioritas{{ $i }}" @selected(old('prioritas', $hotspotProfile->prioritas) === 'prioritas'.$i)>Prioritas {{ $i }}</option>
                            @endfor
                        </select>
                        @error('prioritas')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-between">
                <a href="{{ route('hotspot-profiles.index') }}" class="btn btn-link">Batal</a>
                <button type="submit" class="btn btn-primary">Update</button>
            </div>
        </form>
    </div>

    <script>
        (function () {
            const profileTypeRadios = document.querySelectorAll('input[name="profile_type"]');
            const limitTypeRadios = document.querySelectorAll('input[name="limit_type"]');
            const limitTypeSection = document.getElementById('limit-type-section');
            const timeSection = document.getElementById('time-limit-section');
            const quotaSection = document.getElementById('quota-limit-section');
            const masaAktifSection = document.getElementById('masa-aktif-section');

            const syncVisibility = () => {
                const profileType = document.querySelector('input[name="profile_type"]:checked')?.value;
                const limitType = document.querySelector('input[name="limit_type"]:checked')?.value;

                if (profileType === 'limited') {
                    limitTypeSection.style.display = '';
                    masaAktifSection.style.display = 'none';
                    timeSection.style.display = limitType === 'time' ? '' : 'none';
                    quotaSection.style.display = limitType === 'quota' ? '' : 'none';
                } else {
                    limitTypeSection.style.display = 'none';
                    timeSection.style.display = 'none';
                    quotaSection.style.display = 'none';
                    masaAktifSection.style.display = '';
                }
            };

            profileTypeRadios.forEach(radio => radio.addEventListener('change', syncVisibility));
            limitTypeRadios.forEach(radio => radio.addEventListener('change', syncVisibility));
            syncVisibility();
        })();
    </script>
@endsection
