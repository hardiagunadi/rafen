@extends('layouts.admin')

@section('title', 'API Dashboard')

@section('content')
    <div class="card">
        <div class="card-header d-flex flex-wrap align-items-center">
            <ul class="nav nav-tabs card-header-tabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" data-toggle="tab" href="#resource-tab" role="tab">Resource</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-toggle="tab" href="#traffic-tab" role="tab">Trafik Live</a>
                </li>
            </ul>
            <div class="ml-auto d-flex align-items-center mt-2 mt-md-0">
                <span class="text-muted mr-2">Select Interface</span>
                <select id="dashboard-connection" class="custom-select custom-select-sm">
                    <option value="">- Select Router -</option>
                    @foreach($connections as $connection)
                        <option value="{{ $connection->id }}" @selected($selectedConnection && $selectedConnection->id === $connection->id)>
                            {{ $connection->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="card-body" id="api-dashboard" data-endpoint="{{ route('dashboard.api.data') }}">
            <div class="tab-content">
                <div class="tab-pane fade show active" id="resource-tab" role="tabpanel">
                    <div class="text-center my-3">
                        <h5 class="mb-0">
                            <i class="far fa-clock mr-1"></i>
                            Uptime : <span data-field="uptime">{{ $resource['uptime'] }}</span>
                        </h5>
                    </div>
                    <div class="row">
                        <div class="col-md-3 col-sm-6">
                            <div class="info-box bg-info">
                                <span class="info-box-icon"><i class="fas fa-flag"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Platform</span>
                                    <span class="info-box-number" data-field="platform_vendor">{{ $resource['platform_vendor'] }}</span>
                                    <span class="text-sm" data-field="platform_model">{{ $resource['platform_model'] }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-box bg-success">
                                <span class="info-box-icon"><i class="fas fa-globe"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">RouterOS</span>
                                    <span class="info-box-number" data-field="routeros">{{ $resource['routeros'] }}</span>
                                    <span class="text-sm">stable</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-box bg-warning">
                                <span class="info-box-icon"><i class="fas fa-microchip"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">CPU Type</span>
                                    <span class="info-box-number" data-field="cpu_type">{{ $resource['cpu_type'] }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-box bg-danger">
                                <span class="info-box-icon"><i class="fas fa-cube"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">CPU Cores</span>
                                    <span class="info-box-number" data-field="cpu_mhz">{{ $resource['cpu_mhz'] }}</span>
                                    <span class="text-sm" data-field="cpu_cores">{{ $resource['cpu_cores'] }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-box bg-secondary">
                                <span class="info-box-icon"><i class="fas fa-tachometer-alt"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">CPU Load</span>
                                    <span class="info-box-number" data-field="cpu_load">{{ $resource['cpu_load'] }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-box bg-warning">
                                <span class="info-box-icon"><i class="fas fa-server"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Free Memory</span>
                                    <span class="info-box-number" data-field="ram_free_percent">{{ $resource['ram_free_percent'] }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-box bg-success">
                                <span class="info-box-icon"><i class="fas fa-hdd"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Free Disk</span>
                                    <span class="info-box-number" data-field="disk_free_percent">{{ $resource['disk_free_percent'] }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-box bg-info">
                                <span class="info-box-icon"><i class="fas fa-calendar-alt"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Build Time</span>
                                    <span class="info-box-number" data-field="build_date">{{ $resource['build_date'] }}</span>
                                    <span class="text-sm" data-field="build_time">{{ $resource['build_time'] }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade" id="traffic-tab" role="tabpanel">
                    <div class="text-center text-muted py-5">
                        Trafik live belum tersedia. Pilih router untuk menambahkan data trafik.
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            const container = document.getElementById('api-dashboard');
            const select = document.getElementById('dashboard-connection');
            const endpoint = container?.dataset.endpoint;

            if (! container || ! select || ! endpoint) {
                return;
            }

            const updateFields = (payload) => {
                Object.entries(payload || {}).forEach(([key, value]) => {
                    const node = container.querySelector(`[data-field="${key}"]`);
                    if (node) {
                        node.textContent = value ?? '-';
                    }
                });
            };

            select.addEventListener('change', async () => {
                const params = new URLSearchParams();
                if (select.value) {
                    params.set('connection_id', select.value);
                }

                try {
                    const response = await fetch(`${endpoint}?${params.toString()}`, {
                        headers: { 'Accept': 'application/json' },
                    });
                    const data = await response.json();
                    if (response.ok && data?.data) {
                        updateFields(data.data);
                    }
                } catch (error) {
                    console.error(error);
                }
            });
        })();
    </script>
@endpush
