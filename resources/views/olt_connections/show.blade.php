@extends('layouts.admin')

@section('title', 'Detail OLT HSGQ')

@section('content')
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h4 class="mb-0">{{ $oltConnection->name }}</h4>
            <small class="text-muted">Monitoring OLT Vendor {{ strtoupper($oltConnection->vendor) }}</small>
        </div>
        <div class="btn-group mt-2 mt-md-0">
            <a href="{{ route('olt-connections.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left mr-1"></i>Daftar OLT
            </a>
            @if(auth()->user()->role !== 'teknisi')
                <form action="{{ route('olt-connections.poll', $oltConnection) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-success">
                        <i class="fas fa-sync-alt mr-1"></i>Polling Sekarang
                    </button>
                </form>
                <a href="{{ route('olt-connections.edit', $oltConnection) }}" class="btn btn-sm btn-warning text-white">
                    <i class="fas fa-pen mr-1"></i>Edit
                </a>
            @endif
        </div>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-lg-4">
                <div><strong>Host:</strong> {{ $oltConnection->host }}:{{ $oltConnection->snmp_port }}</div>
                <div><strong>Model:</strong> {{ $oltConnection->olt_model ?: '-' }}</div>
                <div><strong>SNMP:</strong> v{{ $oltConnection->snmp_version }} / timeout {{ $oltConnection->snmp_timeout }}s / retry {{ $oltConnection->snmp_retries }}</div>
                <div><strong>Status:</strong>
                    @if($oltConnection->is_active)
                        <span class="badge badge-success">Aktif</span>
                    @else
                        <span class="badge badge-secondary">Nonaktif</span>
                    @endif
                </div>
            </div>
            <div class="col-lg-4">
                <div><strong>Polling terakhir:</strong> {{ $oltConnection->last_polled_at?->format('Y-m-d H:i:s') ?? '-' }}</div>
                <div><strong>Hasil polling:</strong>
                    @if($oltConnection->last_poll_success === null)
                        <span class="badge badge-secondary">Belum Pernah Polling</span>
                    @elseif($oltConnection->last_poll_success)
                        <span class="badge badge-success">Sukses</span>
                    @else
                        <span class="badge badge-danger">Gagal</span>
                    @endif
                </div>
                <div><strong>Total ONU tersimpan:</strong> {{ number_format($totalOnuStored) }}</div>
            </div>
            <div class="col-lg-4">
                <div><strong>OID MAC / Identifier:</strong> <code>{{ $oltConnection->oid_serial ?: '-' }}</code></div>
                <div><strong>OID Rx ONU:</strong> <code>{{ $oltConnection->oid_rx_onu ?: '-' }}</code></div>
                <div><strong>OID Distance:</strong> <code>{{ $oltConnection->oid_distance ?: '-' }}</code></div>
                <div><strong>OID Status:</strong> <code>{{ $oltConnection->oid_status ?: '-' }}</code></div>
            </div>
        </div>
        @if($oltConnection->last_poll_message)
            <div class="alert {{ $oltConnection->last_poll_success ? 'alert-success' : 'alert-danger' }} mt-3 mb-0">
                {{ $oltConnection->last_poll_message }}
            </div>
        @endif
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
        <h3 class="card-title mb-2 mb-md-0">Data Redaman ONU</h3>
        <div class="d-flex flex-wrap align-items-center">
            <div class="input-group input-group-sm mr-2 mb-2 mb-md-0" style="min-width: 180px;">
                <select id="onu-port-filter" class="form-control">
                    <option value="">Semua Port ID</option>
                    @foreach($availablePortIds as $portId)
                        <option value="{{ $portId }}" @selected($selectedPortId === $portId)>{{ $portId }}</option>
                    @endforeach
                </select>
            </div>
            <div class="input-group input-group-sm" style="min-width: 260px;">
                <input type="text" id="onu-search-input" value="{{ $search }}" class="form-control"
                    placeholder="Cari MAC/nama/ONU ID">
                <div class="input-group-append">
                    <button class="btn btn-outline-secondary" type="button" id="onu-filter-reset">Reset</button>
                </div>
            </div>
        </div>
    </div>
    <div class="card-body border-bottom">
        <div class="d-flex flex-wrap align-items-center justify-content-between">
            <div class="d-flex flex-wrap mb-2 mb-md-0">
                <button type="button" data-status-filter=""
                    class="btn btn-sm mr-2 mb-2 {{ $selectedStatus === '' ? 'btn-primary' : 'btn-outline-primary' }}">
                    Semua Status
                </button>
                <button type="button" data-status-filter="online"
                    class="btn btn-sm mr-2 mb-2 {{ $selectedStatus === 'online' ? 'btn-success' : 'btn-outline-success' }}">
                    ONLINE
                </button>
                <button type="button" data-status-filter="offline"
                    class="btn btn-sm mb-2 {{ $selectedStatus === 'offline' ? 'btn-danger' : 'btn-outline-danger' }}">
                    OFFLINE
                </button>
            </div>
            <div class="small text-muted">
                Total ONU: {{ number_format($activeSummary) }} |
                Online: {{ number_format($onlineSummary) }} |
                Offline: {{ number_format($offlineSummary) }}
            </div>
        </div>
        @if($summaryRows->isNotEmpty())
            <div class="row mt-2">
                @foreach($summaryRows as $summaryRow)
                    <div class="col-xl-3 col-lg-4 col-md-6 mb-2">
                        <div class="border rounded px-3 py-2 h-100 {{ $selectedPortId === $summaryRow['port_id'] ? 'border-primary bg-light' : '' }}"
                            data-port-summary="{{ $summaryRow['port_id'] }}">
                            <div class="d-flex justify-content-between align-items-center">
                                <strong>{{ $summaryRow['port_id'] }}</strong>
                                <button type="button" class="btn btn-link btn-sm p-0" data-port-summary-filter="{{ $summaryRow['port_id'] }}">
                                    Filter
                                </button>
                            </div>
                            <div class="small text-muted mt-2">Total {{ number_format($summaryRow['total']) }}</div>
                            <div class="small text-success">Online {{ number_format($summaryRow['online']) }}</div>
                            <div class="small text-danger">Offline {{ number_format($summaryRow['offline']) }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="onu-optics-table" class="table table-hover table-sm mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>PON</th>
                        <th>ONU</th>
                        <th>ONU ID</th>
                        <th>MAC / ID</th>
                        <th>Nama ONU</th>
                        <th>Distance</th>
                        <th>Rx ONU</th>
                        <th>Tx ONU</th>
                        <th>Tx OLT</th>
                        <th>Status</th>
                        <th>Last Seen</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    function init() {
        var tableElement = document.getElementById('onu-optics-table');

        if (!tableElement || $.fn.DataTable.isDataTable('#onu-optics-table')) {
            return;
        }

        var portFilter = document.getElementById('onu-port-filter');
        var searchInput = document.getElementById('onu-search-input');
        var resetButton = document.getElementById('onu-filter-reset');
        var statusButtons = Array.from(document.querySelectorAll('[data-status-filter]'));
        var summaryCards = Array.from(document.querySelectorAll('[data-port-summary]'));
        var summaryFilterButtons = Array.from(document.querySelectorAll('[data-port-summary-filter]'));
        var searchTimer = null;

        function activeStatus() {
            var activeButton = document.querySelector('[data-status-filter].active, [data-status-filter].btn-primary, [data-status-filter].btn-success, [data-status-filter].btn-danger');

            return activeButton ? activeButton.getAttribute('data-status-filter') || '' : '';
        }

        function updateStatusButtons(selectedStatus) {
            statusButtons.forEach(function (button) {
                var status = button.getAttribute('data-status-filter') || '';
                var isActive = status === selectedStatus;

                button.classList.remove('btn-primary', 'btn-outline-primary', 'btn-success', 'btn-outline-success', 'btn-danger', 'btn-outline-danger', 'active');

                if (status === 'online') {
                    button.classList.add(isActive ? 'btn-success' : 'btn-outline-success');
                } else if (status === 'offline') {
                    button.classList.add(isActive ? 'btn-danger' : 'btn-outline-danger');
                } else {
                    button.classList.add(isActive ? 'btn-primary' : 'btn-outline-primary');
                }

                if (isActive) {
                    button.classList.add('active');
                }
            });
        }

        function updateSummaryCards(selectedPortId) {
            summaryCards.forEach(function (card) {
                var portId = card.getAttribute('data-port-summary');
                var isActive = selectedPortId !== '' && portId === selectedPortId;

                card.classList.toggle('border-primary', isActive);
                card.classList.toggle('bg-light', isActive);
            });
        }

        function syncQueryString() {
            var url = new URL(window.location.href);
            var status = activeStatus();

            if (searchInput.value.trim() !== '') {
                url.searchParams.set('search', searchInput.value.trim());
            } else {
                url.searchParams.delete('search');
            }

            if (portFilter.value !== '') {
                url.searchParams.set('port_id', portFilter.value);
            } else {
                url.searchParams.delete('port_id');
            }

            if (status !== '') {
                url.searchParams.set('status', status);
            } else {
                url.searchParams.delete('status');
            }

            window.history.replaceState({}, '', url);
        }

        var table = $('#onu-optics-table').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            search: {
                search: searchInput.value || ''
            },
            ajax: {
                url: '{{ route('olt-connections.datatable', $oltConnection) }}',
                data: function (data) {
                    data.port_id = portFilter.value;
                    data.status = activeStatus();
                }
            },
            columns: [
                { data: 'pon_interface' },
                { data: 'onu_number' },
                { data: 'onu_id', render: function (data) {
                    return data === '-' ? '-' : '<code>' + data + '</code>';
                }},
                { data: 'serial_number', render: function (data) {
                    return data === '-' ? '-' : '<code>' + data + '</code>';
                }},
                { data: 'onu_name' },
                { data: 'distance_m' },
                { data: 'rx_onu_dbm' },
                { data: 'tx_onu_dbm' },
                { data: 'tx_olt_dbm' },
                { data: 'status_badge', searchable: false },
                { data: 'last_seen_at' }
            ],
            pageLength: 50,
            searchDelay: 350,
            dom: "rt<'row align-items-center px-3 py-3'<'col-md-4'l><'col-md-4 text-center'i><'col-md-4'p>>",
            language: {
                lengthMenu: 'Tampilkan _MENU_ data',
                info: 'Menampilkan _START_ - _END_ dari _TOTAL_ data',
                infoEmpty: 'Tidak ada data',
                infoFiltered: '(disaring dari _MAX_ total data)',
                zeroRecords: 'Tidak ada data yang cocok.',
                emptyTable: 'Belum ada data ONU. Jalankan polling untuk mengambil data redaman dari OLT.',
                paginate: {
                    first: 'Pertama',
                    last: 'Terakhir',
                    next: 'Selanjutnya',
                    previous: 'Sebelumnya'
                },
                processing: 'Memuat...'
            },
            order: [[0, 'asc'], [1, 'asc']]
        });

        function reloadTable(resetPaging) {
            syncQueryString();
            updateSummaryCards(portFilter.value);
            table.ajax.reload(null, resetPaging);
        }

        portFilter.addEventListener('change', function () {
            reloadTable(true);
        });

        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimer);
            searchTimer = window.setTimeout(function () {
                table.search(searchInput.value.trim()).draw();
                syncQueryString();
            }, 300);
        });

        statusButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                updateStatusButtons(button.getAttribute('data-status-filter') || '');
                reloadTable(true);
            });
        });

        summaryFilterButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                portFilter.value = button.getAttribute('data-port-summary-filter') || '';
                reloadTable(true);
            });
        });

        resetButton.addEventListener('click', function () {
            searchInput.value = '';
            portFilter.value = '';
            updateStatusButtons('');
            table.search('');
            reloadTable(true);
        });

        updateStatusButtons('{{ $selectedStatus }}');
        updateSummaryCards(portFilter.value);
        syncQueryString();
    }

    document.addEventListener('DOMContentLoaded', init);

    if (document.readyState !== 'loading') {
        init();
    }
})();
</script>
@endpush
