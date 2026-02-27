@extends('layouts.admin')

@section('title', 'Manajemen Voucher')

@section('content')
    <div class="card" style="overflow: visible;">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap" style="overflow: visible;">
            <div class="btn-group">
                <div class="dropdown">
                    <button class="btn btn-success btn-sm dropdown-toggle" type="button" data-toggle="dropdown" data-display="static" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-bars"></i> Manajemen Voucher
                    </button>
                    <div class="dropdown-menu dropdown-menu-left" style="min-width: 200px;">
                        <a class="dropdown-item" href="{{ route('vouchers.create') }}">Generate Voucher Baru</a>
                        <a class="dropdown-item" href="{{ route('vouchers.index') }}">List Semua Voucher</a>
                        <div class="dropdown-header text-danger text-uppercase">Aksi Massal</div>
                        <a class="dropdown-item text-danger bulk-delete-action" href="#">Hapus Unused Terpilih</a>
                    </div>
                </div>
            </div>
            <div class="mt-2 mt-sm-0">
                <h4 class="mb-0">Voucher Hotspot</h4>
            </div>
        </div>

        <div class="card-body">
            <div class="row text-center mb-3">
                <div class="col-md-4 mb-2">
                    <div class="p-3 border rounded h-100 d-flex align-items-center">
                        <div class="mr-3 text-success"><i class="fas fa-ticket-alt fa-2x"></i></div>
                        <div class="text-left">
                            <div class="small text-uppercase text-muted">Unused</div>
                            <div class="h5 mb-0">{{ number_format($stats['unused']) }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-2">
                    <div class="p-3 border rounded h-100 d-flex align-items-center">
                        <div class="mr-3 text-info"><i class="fas fa-check-circle fa-2x"></i></div>
                        <div class="text-left">
                            <div class="small text-uppercase text-muted">Used</div>
                            <div class="h5 mb-0">{{ number_format($stats['used']) }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-2">
                    <div class="p-3 border rounded h-100 d-flex align-items-center">
                        <div class="mr-3 text-secondary"><i class="fas fa-times-circle fa-2x"></i></div>
                        <div class="text-left">
                            <div class="small text-uppercase text-muted">Expired</div>
                            <div class="h5 mb-0">{{ number_format($stats['expired']) }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <form method="GET" action="{{ route('vouchers.index') }}" class="form-inline justify-content-between mb-3 flex-wrap">
                <div class="d-flex flex-wrap">
                    <div class="form-group mb-2 mr-2">
                        <label for="per-page" class="mr-2">Show</label>
                        <select name="per_page" id="per-page" class="form-control form-control-sm" onchange="this.form.submit()">
                            @foreach([20, 50, 100, 200] as $size)
                                <option value="{{ $size }}" @selected($perPage == $size)>{{ $size }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group mb-2 mr-2">
                        <select name="status" class="form-control form-control-sm" onchange="this.form.submit()">
                            <option value="">- Semua Status -</option>
                            @foreach(['unused', 'used', 'expired'] as $s)
                                <option value="{{ $s }}" @selected($status === $s)>{{ strtoupper($s) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group mb-2 mr-2">
                        <select name="batch" class="form-control form-control-sm" onchange="this.form.submit()">
                            <option value="">- Semua Batch -</option>
                            @foreach($batches as $b)
                                <option value="{{ $b }}" @selected($batch === $b)>{{ $b }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-group mb-2">
                    <input type="text" name="search" value="{{ $search }}" class="form-control form-control-sm" placeholder="Cari kode...">
                    <button type="submit" class="btn btn-sm btn-primary ml-1">Cari</button>
                </div>
            </form>

            {{-- Print batch shortcut --}}
            @if($batch)
                <div class="mb-2">
                    <a href="{{ route('vouchers.print', $batch) }}" class="btn btn-sm btn-outline-secondary" target="_blank">
                        <i class="fas fa-print"></i> Print Batch "{{ $batch }}"
                    </a>
                </div>
            @endif

            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead class="thead-light">
                    <tr>
                        <th style="width:40px;"><input type="checkbox" id="select-all"></th>
                        <th>Kode</th>
                        <th>Batch</th>
                        <th>Profil Hotspot</th>
                        <th>Status</th>
                        <th>Expired</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($vouchers as $voucher)
                        <tr>
                            <td><input type="checkbox" name="ids[]" value="{{ $voucher->id }}" @disabled($voucher->status !== 'unused')></td>
                            <td><code class="text-lg font-weight-bold">{{ $voucher->code }}</code></td>
                            <td>{{ $voucher->batch_name ?? '-' }}</td>
                            <td>{{ $voucher->hotspotProfile?->name ?? '-' }}</td>
                            <td>
                                @php
                                    $statusClass = match($voucher->status) {
                                        'unused'  => 'success',
                                        'used'    => 'info',
                                        'expired' => 'secondary',
                                        default   => 'light',
                                    };
                                @endphp
                                <span class="badge badge-{{ $statusClass }}">{{ strtoupper($voucher->status) }}</span>
                            </td>
                            <td>{{ $voucher->expired_at?->format('Y-m-d') ?? '-' }}</td>
                            <td class="text-right">
                                @if($voucher->status === 'unused')
                                    <button type="button" class="btn btn-sm btn-danger" title="Hapus"
                                        data-ajax-delete="{{ route('vouchers.destroy', $voucher) }}"
                                        data-confirm="Hapus voucher {{ $voucher->code }}?">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                @else
                                    <button class="btn btn-sm btn-light" disabled><i class="fas fa-trash"></i></button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center p-4">Belum ada voucher.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($vouchers->hasPages())
            <div class="card-footer">
                {{ $vouchers->links() }}
            </div>
        @endif

        <form id="bulk-delete-form" action="{{ route('vouchers.bulk-destroy') }}" method="POST">
            @csrf
            @method('DELETE')
        </form>
    </div>

    <script>
        const selectAll = document.getElementById('select-all');
        if (selectAll) {
            selectAll.addEventListener('change', function (e) {
                document.querySelectorAll('input[name="ids[]"]:not(:disabled)').forEach(cb => cb.checked = e.target.checked);
            });
        }

        document.querySelector('.bulk-delete-action')?.addEventListener('click', function (e) {
            e.preventDefault();
            const ids = [...document.querySelectorAll('input[name="ids[]"]:checked')].map(cb => cb.value);
            if (ids.length === 0) { alert('Pilih voucher unused terlebih dahulu.'); return; }
            if (!confirm('Hapus ' + ids.length + ' voucher terpilih?')) return;
            const form = document.getElementById('bulk-delete-form');
            ids.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden'; input.name = 'ids[]'; input.value = id;
                form.appendChild(input);
            });
            form.submit();
        });
    </script>
@endsection
