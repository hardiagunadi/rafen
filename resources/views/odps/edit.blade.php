@extends('layouts.admin')

@section('title', 'Edit ODP')

@section('content')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
<style>
    #odp-location-map {
        height: 320px;
        border: 1px solid #dee2e6;
        border-radius: 6px;
    }
</style>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="mb-0">Edit Data ODP — <span class="text-primary">{{ $odp->code }}</span></h4>
        <a href="{{ route('odps.index') }}" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
    </div>
    <form action="{{ route('odps.update', $odp) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="card-body">
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Owner Data</label>
                    <select name="owner_id" id="odp-owner-id" class="form-control @error('owner_id') is-invalid @enderror" required>
                        @foreach($owners as $owner)
                            <option value="{{ $owner->id }}" @selected(old('owner_id', $odp->owner_id) == $owner->id)>{{ $owner->name }}</option>
                        @endforeach
                    </select>
                    @error('owner_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="form-group col-md-4">
                    <label>Kode ODP</label>
                    <div class="input-group">
                        <input type="text" name="code" id="odp-code" value="{{ old('code', $odp->code) }}" class="form-control @error('code') is-invalid @enderror" required>
                        <div class="input-group-append">
                            <button type="button" class="btn btn-outline-secondary" id="btn-generate-odp-code" title="Generate otomatis dari titik map">
                                <i class="fas fa-magic"></i>
                            </button>
                        </div>
                    </div>
                    @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <small class="text-muted d-block mt-1" id="odp-code-result">Format otomatis: KODELOKASI-WILAYAH-001</small>
                </div>
                <div class="form-group col-md-4">
                    <label>Nama ODP</label>
                    <input type="text" name="name" value="{{ old('name', $odp->name) }}" class="form-control @error('name') is-invalid @enderror" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Area</label>
                    <input type="text" name="area" id="odp-area" value="{{ old('area', $odp->area) }}" class="form-control @error('area') is-invalid @enderror">
                    @error('area')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="form-group col-md-4">
                    <label>Kapasitas Port</label>
                    <input type="number" name="capacity_ports" value="{{ old('capacity_ports', $odp->capacity_ports) }}" min="0" class="form-control @error('capacity_ports') is-invalid @enderror">
                    @error('capacity_ports')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="form-group col-md-4">
                    <label>Status</label>
                    <select name="status" class="form-control @error('status') is-invalid @enderror">
                        <option value="active" @selected(old('status', $odp->status) === 'active')>Active</option>
                        <option value="maintenance" @selected(old('status', $odp->status) === 'maintenance')>Maintenance</option>
                        <option value="inactive" @selected(old('status', $odp->status) === 'inactive')>Inactive</option>
                    </select>
                    @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="form-row">
                <div class="form-group col-md-5">
                    <label>Latitude</label>
                    <input type="text" name="latitude" id="odp-latitude" value="{{ old('latitude', $odp->latitude) }}" class="form-control @error('latitude') is-invalid @enderror" placeholder="-7.1234567">
                    @error('latitude')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="form-group col-md-5">
                    <label>Longitude</label>
                    <input type="text" name="longitude" id="odp-longitude" value="{{ old('longitude', $odp->longitude) }}" class="form-control @error('longitude') is-invalid @enderror" placeholder="109.1234567">
                    @error('longitude')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="form-group col-md-2 d-flex align-items-end">
                    <button type="button" class="btn btn-outline-primary btn-block" id="btn-capture-odp-gps">
                        <i class="fas fa-location-arrow mr-1"></i>Ambil Titik
                    </button>
                </div>
            </div>
            <small class="text-muted d-block mb-3" id="odp-gps-result">Klik "Ambil Titik" saat berada di lokasi ODP.</small>
            <div id="odp-location-map" class="mb-3"></div>
            <small class="text-muted d-block mb-3" id="odp-map-result">Gunakan layer Earth untuk cek visual satelit. Marker bisa digeser untuk koreksi titik presisi.</small>

            <div class="form-group mb-0">
                <label>Catatan</label>
                <textarea name="notes" class="form-control @error('notes') is-invalid @enderror" rows="3">{{ old('notes', $odp->notes) }}</textarea>
                @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
        <div class="card-footer d-flex justify-content-between">
            <a href="{{ route('odps.index') }}" class="btn btn-link">Batal</a>
            <button type="submit" class="btn btn-primary">Update</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function () {
    var map;
    var marker;
    var generatingCode = false;

    function median(values) {
        var sorted = values.slice().sort(function (a, b) { return a - b; });
        var middle = Math.floor(sorted.length / 2);
        if (sorted.length % 2 === 0) {
            return (sorted[middle - 1] + sorted[middle]) / 2;
        }
        return sorted[middle];
    }

    function parseCoordinate(value) {
        var parsed = parseFloat(value);
        return Number.isFinite(parsed) ? parsed : null;
    }

    function sanitizeSegment(value, fallback, maxLength) {
        var normalized = String(value || '')
            .toUpperCase()
            .replace(/[^A-Z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');

        if (!normalized) {
            normalized = fallback;
        }

        if (normalized.length > maxLength) {
            normalized = normalized.slice(0, maxLength);
        }

        return normalized;
    }

    function setCodeResult(message, className) {
        var resultElement = document.getElementById('odp-code-result');
        if (!resultElement) {
            return;
        }

        resultElement.textContent = message;
        resultElement.className = className;
    }

    function extractAreaName(address) {
        return address.suburb
            || address.village
            || address.hamlet
            || address.quarter
            || address.neighbourhood
            || address.city_district
            || address.town
            || address.city
            || address.county
            || address.state_district
            || address.state
            || '';
    }

    function extractLocationCode(address, areaName) {
        return address.city
            || address.town
            || address.county
            || address.state_district
            || address.state
            || areaName
            || 'LOC';
    }

    async function reverseGeocode(lat, lng) {
        var url = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&zoom=18&addressdetails=1'
            + '&lat=' + encodeURIComponent(lat)
            + '&lon=' + encodeURIComponent(lng);
        var response = await fetch(url, {
            headers: {
                Accept: 'application/json',
            },
        });

        if (!response.ok) {
            throw new Error('Gagal mengambil nama wilayah dari peta.');
        }

        return response.json();
    }

    async function requestGeneratedCode(ownerId, locationCode, areaName) {
        var query = new URLSearchParams({
            owner_id: String(ownerId),
            location_code: locationCode,
            area_name: areaName,
        });
        var response = await fetch('{{ route('odps.generate-code') }}?' + query.toString(), {
            headers: {
                Accept: 'application/json',
            },
        });

        var payload = await response.json().catch(function () {
            return {};
        });

        if (!response.ok) {
            throw new Error(payload.message || 'Gagal generate kode ODP otomatis.');
        }

        return payload;
    }

    async function generateCodeFromMap() {
        if (generatingCode) {
            return;
        }

        var ownerInput = document.getElementById('odp-owner-id');
        var codeInput = document.getElementById('odp-code');
        var areaInput = document.getElementById('odp-area');
        var latInput = document.getElementById('odp-latitude');
        var lngInput = document.getElementById('odp-longitude');
        var button = document.getElementById('btn-generate-odp-code');

        if (!ownerInput || !codeInput || !latInput || !lngInput) {
            return;
        }

        var ownerId = ownerInput.value;
        var lat = parseCoordinate(latInput.value);
        var lng = parseCoordinate(lngInput.value);

        if (!ownerId) {
            setCodeResult('Owner data wajib dipilih terlebih dahulu.', 'text-danger d-block mt-1');
            return;
        }

        if (lat === null || lng === null) {
            setCodeResult('Isi titik koordinat dulu, lalu generate kode otomatis.', 'text-danger d-block mt-1');
            return;
        }

        generatingCode = true;
        if (button) {
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        }
        setCodeResult('Mengambil wilayah dari peta...', 'text-info d-block mt-1');

        try {
            var reverseData = await reverseGeocode(lat, lng);
            var address = reverseData && reverseData.address ? reverseData.address : {};
            var areaName = extractAreaName(address) || (areaInput ? areaInput.value : '') || 'Wilayah';
            var locationCodeSource = extractLocationCode(address, areaName);
            var locationCode = sanitizeSegment(locationCodeSource, 'LOC', 12);
            var areaSegmentSource = sanitizeSegment(areaName, 'WILAYAH', 40);

            if (areaInput) {
                areaInput.value = areaName;
            }

            var generated = await requestGeneratedCode(ownerId, locationCode, areaSegmentSource);
            codeInput.value = generated.code;
            setCodeResult('Kode otomatis: ' + generated.code, 'text-success d-block mt-1');
        } catch (error) {
            var message = error instanceof Error ? error.message : 'Gagal generate kode ODP otomatis.';
            setCodeResult(message, 'text-danger d-block mt-1');
        } finally {
            generatingCode = false;
            if (button) {
                button.disabled = false;
                button.innerHTML = '<i class="fas fa-magic"></i>';
            }
        }
    }

    function setCoordinates(lat, lng, shouldFocusMap) {
        var latInput = document.getElementById('odp-latitude');
        var lngInput = document.getElementById('odp-longitude');
        var mapResult = document.getElementById('odp-map-result');

        latInput.value = lat.toFixed(7);
        lngInput.value = lng.toFixed(7);

        if (marker) {
            marker.setLatLng([lat, lng]);
        }
        if (shouldFocusMap && map) {
            map.setView([lat, lng], Math.max(map.getZoom(), 17));
        }

        if (mapResult) {
            mapResult.textContent = 'Titik disetel: ' + lat.toFixed(7) + ', ' + lng.toFixed(7);
            mapResult.className = 'text-success d-block mb-3';
        }
    }

    function initLocationMap() {
        var mapContainer = document.getElementById('odp-location-map');
        if (!mapContainer || typeof L === 'undefined') {
            return;
        }

        var latInput = document.getElementById('odp-latitude');
        var lngInput = document.getElementById('odp-longitude');
        var lat = parseCoordinate(latInput.value);
        var lng = parseCoordinate(lngInput.value);
        var initialPoint = (lat !== null && lng !== null) ? [lat, lng] : [-7.36, 109.90];
        var initialZoom = (lat !== null && lng !== null) ? 16 : 12;

        map = L.map('odp-location-map').setView(initialPoint, initialZoom);
        var streetLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        var earthLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            maxZoom: 19,
            attribution: 'Tiles &copy; Esri'
        });

        L.control.layers({
            Street: streetLayer,
            Earth: earthLayer
        }).addTo(map);

        marker = L.marker(initialPoint, { draggable: true }).addTo(map);

        marker.on('dragend', function () {
            var position = marker.getLatLng();
            setCoordinates(position.lat, position.lng, false);
        });

        map.on('click', function (event) {
            setCoordinates(event.latlng.lat, event.latlng.lng, false);
        });

        latInput.addEventListener('change', function () {
            var nextLat = parseCoordinate(latInput.value);
            var nextLng = parseCoordinate(lngInput.value);

            if (nextLat === null || nextLng === null) {
                return;
            }

            setCoordinates(nextLat, nextLng, true);
        });

        lngInput.addEventListener('change', function () {
            var nextLat = parseCoordinate(latInput.value);
            var nextLng = parseCoordinate(lngInput.value);

            if (nextLat === null || nextLng === null) {
                return;
            }

            setCoordinates(nextLat, nextLng, true);
        });
    }

    function captureOdpGps() {
        var btn = document.getElementById('btn-capture-odp-gps');
        var result = document.getElementById('odp-gps-result');

        if (!navigator.geolocation) {
            result.textContent = 'Browser tidak mendukung geolocation.';
            result.className = 'text-danger d-block mb-3';
            return;
        }

        btn.disabled = true;
        result.textContent = 'Mengambil 3 sampel GPS...';
        result.className = 'text-info d-block mb-3';

        var samples = [];

        function takeSample() {
            navigator.geolocation.getCurrentPosition(function (position) {
                samples.push({
                    lat: position.coords.latitude,
                    lng: position.coords.longitude,
                    acc: position.coords.accuracy,
                });

                if (samples.length < 3) {
                    result.textContent = 'Sampel ' + samples.length + '/3 berhasil, melanjutkan...';
                    setTimeout(takeSample, 1200);
                    return;
                }

                var latValues = samples.map(function (s) { return s.lat; });
                var lngValues = samples.map(function (s) { return s.lng; });
                var accValues = samples.map(function (s) { return s.acc; });

                setCoordinates(median(latValues), median(lngValues), true);
                generateCodeFromMap();
                result.textContent = 'Titik diambil. Akurasi median: ' + median(accValues).toFixed(1) + ' meter.';
                result.className = 'text-success d-block mb-3';
                btn.disabled = false;
            }, function (error) {
                var message = 'Gagal mengambil GPS.';
                if (error.code === 1) message = 'Izin lokasi ditolak.';
                if (error.code === 2) message = 'Lokasi tidak tersedia.';
                if (error.code === 3) message = 'Permintaan lokasi timeout.';
                result.textContent = message;
                result.className = 'text-danger d-block mb-3';
                btn.disabled = false;
            }, {
                enableHighAccuracy: true,
                timeout: 15000,
                maximumAge: 0,
            });
        }

        takeSample();
    }

    document.addEventListener('DOMContentLoaded', function () {
        initLocationMap();
        var btn = document.getElementById('btn-capture-odp-gps');
        var generateCodeBtn = document.getElementById('btn-generate-odp-code');
        if (btn) {
            btn.addEventListener('click', captureOdpGps);
        }
        if (generateCodeBtn) {
            generateCodeBtn.addEventListener('click', function () {
                generateCodeFromMap();
            });
        }
    });
})();
</script>
@endpush
