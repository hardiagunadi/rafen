@extends('layouts.admin')

@section('title', 'User Hotspot')

@section('content')
    <div class="card" style="overflow: visible;">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap" style="overflow: visible;">
            <div class="btn-group">
                <div class="dropdown">
                    <button class="btn btn-success btn-sm dropdown-toggle" type="button" id="managementDropdown" data-toggle="dropdown" data-display="static" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-bars"></i> Manajemen Hotspot
                    </button>
                    <div class="dropdown-menu dropdown-menu-left" aria-labelledby="managementDropdown" style="min-width: 220px;">
                        <a class="dropdown-item" href="{{ route('hotspot-users.create') }}">Tambah User Hotspot</a>
                        <a class="dropdown-item" href="{{ route('hotspot-users.index') }}">List User Hotspot</a>
                        <div class="dropdown-header text-danger text-uppercase">Aksi Massal</div>
                        <a class="dropdown-item text-danger bulk-delete-action" href="#">Hapus Terpilih</a>
                    </div>
                </div>
            </div>
            <div class="mt-2 mt-sm-0">
                <h4 class="mb-0">User Hotspot</h4>
            </div>
        </div>

        <div class="card-body">
            <div class="row text-center mb-3">
                <div class="col-md-3 col-sm-6 mb-2">
                    <div class="p-3 border rounded h-100 d-flex align-items-center">
                        <div class="mr-3 text-info"><i class="fas fa-users fa-2x"></i></div>
                        <div class="text-left">
                            <div class="small text-uppercase text-muted">Registrasi Bulan Ini</div>
                            <div class="h5 mb-0">{{ $stats['registrasi_bulan_ini'] }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <div class="p-3 border rounded h-100 d-flex align-items-center">
                        <div class="mr-3 text-success"><i class="fas fa-user-check fa-2x"></i></div>
                        <div class="text-left">
                            <div class="small text-uppercase text-muted">Total User</div>
                            <div class="h5 mb-0">{{ $stats['total'] }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <div class="p-3 border rounded h-100 d-flex align-items-center">
                        <div class="mr-3 text-warning"><i class="fas fa-exclamation-triangle fa-2x"></i></div>
                        <div class="text-left">
                            <div class="small text-uppercase text-muted">Pelanggan Isolir</div>
                            <div class="h5 mb-0">{{ $stats['pelanggan_isolir'] }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <div class="p-3 border rounded h-100 d-flex align-items-center">
                        <div class="mr-3 text-danger"><i class="fas fa-ban fa-2x"></i></div>
                        <div class="text-left">
                            <div class="small text-uppercase text-muted">Akun Disable</div>
                            <div class="h5 mb-0">{{ $stats['akun_disable'] }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <form method="GET" action="{{ route('hotspot-users.index') }}" class="form-inline justify-content-between mb-3">
                <div class="form-group mb-2">
                    <label for="per-page" class="mr-2">Show</label>
                    <select name="per_page" id="per-page" class="form-control form-control-sm" onchange="this.form.submit()">
                        @foreach([10, 25, 50, 100] as $size)
                            <option value="{{ $size }}" @selected($perPage == $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                    <span class="ml-2">entries</span>
                </div>
                <div class="form-group mb-2">
                    <label for="search" class="mr-2">Search:</label>
                    <input type="text" name="search" id="search" value="{{ $search }}" class="form-control form-control-sm" placeholder="ID/Nama/Username">
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead class="thead-light">
                    <tr>
                        <th style="width:40px;"><input type="checkbox" id="select-all"></th>
                        <th>ID Pelanggan</th>
                        <th>Nama</th>
                        <th>Username</th>
                        <th>Profil Hotspot</th>
                        <th>Status Akun</th>
                        <th>Jatuh Tempo</th>
                        <th>Owner</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($users as $user)
                        <tr>
                            <td><input type="checkbox" name="ids[]" value="{{ $user->id }}"></td>
                            <td>{{ $user->customer_id ?? '-' }}</td>
                            <td>
                                <div class="font-weight-bold text-uppercase">{{ $user->customer_name ?? '-' }}</div>
                                <div class="small text-muted">{{ $user->nomor_hp ?? '' }}</div>
                            </td>
                            <td>{{ $user->username ?? '-' }}</td>
                            <td>{{ $user->hotspotProfile?->name ?? '-' }}</td>
                            <td>
                                @php
                                    $statusClass = match($user->status_akun) {
                                        'enable'  => 'success',
                                        'disable' => 'danger',
                                        'isolir'  => 'warning',
                                        default   => 'secondary',
                                    };
                                @endphp
                                <span class="badge badge-{{ $statusClass }}">{{ strtoupper($user->status_akun) }}</span>
                            </td>
                            <td>
                                @if($user->jatuh_tempo)
                                    <span class="{{ $user->jatuh_tempo->isPast() ? 'text-danger' : 'text-primary' }} font-weight-bold">
                                        {{ $user->jatuh_tempo->format('Y-m-d') }}
                                    </span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>{{ $user->owner?->name ?? '-' }}</td>
                            <td class="text-right">
                                <a href="{{ route('hotspot-users.edit', $user) }}" class="btn btn-sm btn-warning text-white" title="Edit">
                                    <i class="fas fa-pen"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-danger" title="Hapus"
                                    data-ajax-delete="{{ route('hotspot-users.destroy', $user) }}"
                                    data-confirm="Hapus user hotspot ini?">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center p-4">Belum ada user hotspot.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($users->hasPages())
            <div class="card-footer">
                {{ $users->links() }}
            </div>
        @endif

        <form id="bulk-delete-form" action="{{ route('hotspot-users.bulk-destroy') }}" method="POST">
            @csrf
            @method('DELETE')
        </form>
    </div>

    <script>
        const selectAll = document.getElementById('select-all');
        if (selectAll) {
            selectAll.addEventListener('change', function (e) {
                document.querySelectorAll('input[name="ids[]"]').forEach(cb => cb.checked = e.target.checked);
            });
        }

        document.querySelector('.bulk-delete-action')?.addEventListener('click', function (e) {
            e.preventDefault();
            const ids = [...document.querySelectorAll('input[name="ids[]"]:checked')].map(cb => cb.value);
            if (ids.length === 0) { alert('Pilih user terlebih dahulu.'); return; }
            if (!confirm('Hapus ' + ids.length + ' user terpilih?')) return;
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
