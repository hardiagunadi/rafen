@extends('layouts.admin')

@section('title', 'User PPP')

@section('content')
    <div class="card" style="overflow: visible;">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap" style="overflow: visible;">
            <div class="btn-group">
                <div class="dropdown">
                    <button class="btn btn-success btn-sm dropdown-toggle" type="button" id="managementDropdown" data-toggle="dropdown" data-display="static" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-bars"></i> Manajemen Pelanggan
                    </button>
                    <div class="dropdown-menu dropdown-menu-left" aria-labelledby="managementDropdown" style="min-width: 260px;">
                        <a class="dropdown-item" href="{{ route('ppp-users.create') }}">Tambah Pelanggan</a>
                        <a class="dropdown-item" href="{{ route('ppp-users.index') }}">Cari Pelanggan</a>
                        <a class="dropdown-item" href="{{ route('ppp-users.index') }}">List Pelanggan</a>
                        <a class="dropdown-item d-flex justify-content-between align-items-center" href="#">
                            Kirim Notifikasi WA
                            <span class="badge badge-warning">OLD</span>
                        </a>
                        <div class="dropdown-header text-danger text-uppercase">Aksi Checkbox (Massal)</div>
                        <a class="dropdown-item d-flex justify-content-between align-items-center" href="#">
                            Kirim Notifikasi WA
                            <span class="badge badge-success">NEW</span>
                        </a>
                        <a class="dropdown-item" href="#">Proses Registrasi</a>
                        <a class="dropdown-item" href="#">Perpanjang Langganan</a>
                        <a class="dropdown-item" href="#">Ubah Owner Data</a>
                        <a class="dropdown-item" href="#">Ubah Tipe Pelanggan</a>
                        <a class="dropdown-item" href="#">Set Bind Onlogin</a>
                        <a class="dropdown-item" href="#">Ekspor CSV</a>
                        <a class="dropdown-item" href="#">Aktifkan Pelanggan</a>
                        <a class="dropdown-item" href="#">Nonaktifkan Pelanggan</a>
                        <a class="dropdown-item text-danger" href="#">Hapus Pelanggan</a>
                    </div>
                </div>
            </div>
            <div class="mt-2 mt-sm-0">
                <h4 class="mb-0">User PPP</h4>
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
                        <div class="mr-3 text-success"><i class="fas fa-recycle fa-2x"></i></div>
                        <div class="text-left">
                            <div class="small text-uppercase text-muted">Renewal Bulan Ini</div>
                            <div class="h5 mb-0">{{ $stats['renewal_bulan_ini'] }}</div>
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

            <form method="GET" action="{{ route('ppp-users.index') }}" class="form-inline justify-content-between mb-3">
                <div class="form-group mb-2">
                    <label for="per-page" class="mr-2">Show</label>
                    <select name="per_page" id="per-page" class="form-control form-control-sm" onchange="this.form.submit()">
                        @foreach([10,25,50,100] as $size)
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
                        <th>Tipe Service</th>
                        <th>Paket Langganan</th>
                        <th>IP Address</th>
                        <th>Diperpanjang</th>
                        <th>Jatuh Tempo</th>
                        <th>Owner Data</th>
                        <th>Renew | Print</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($users as $user)
                        @php
                            $invoice = $user->invoices->firstWhere('status', 'unpaid');
                            $canRenew = $invoice && $invoice->status === 'unpaid' && $invoice->created_at->equalTo($invoice->updated_at);
                            $canPay = $invoice && $invoice->status === 'unpaid';
                        @endphp
                        <tr>
                            <td><input type="checkbox" name="ids[]" value="{{ $user->id }}"></td>
                            <td>{{ $user->customer_id ?? '-' }}</td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-info-circle text-secondary mr-2"></i>
                                    <div class="text-uppercase font-weight-bold">{{ $user->customer_name ?? '-' }}</div>
                                </div>
                            </td>
                            <td>
                                @if($user->status_registrasi)
                                    <span class="badge badge-success mr-1">{{ strtoupper(substr($user->status_registrasi, 0, 3)) }}</span>
                                @endif
                                {{ strtoupper(str_replace('_', '/', $user->tipe_service)) }}
                            </td>
                            <td>{{ $user->profile?->name ?? '-' }}</td>
                            <td>{{ $user->tipe_ip === 'static' ? ($user->ip_static ?? '-') : 'Automatic' }}</td>
                            <td>{{ $user->updated_at?->format('Y-m-d H:i:s') ?? '-' }}</td>
                            <td>
                                @if($user->jatuh_tempo)
                                    <a href="#" class="text-primary font-weight-bold">{{ $user->jatuh_tempo->format('Y-m-d H:i:s') }}</a>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>{{ $user->owner?->email ?? $user->owner?->name ?? '-' }}</td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    @if($invoice)
                                        <form action="{{ route('invoices.renew', $invoice) }}" method="POST" class="d-inline" onsubmit="return confirm('Perpanjang layanan tanpa pembayaran?');">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-primary" title="Renew (BELUM BAYAR)" @disabled(! $canRenew)><i class="fas fa-bolt"></i></button>
                                        </form>
                                        <a href="{{ route('invoices.index') }}#invoice-{{ $invoice->id }}" class="btn btn-success @if(! $invoice) disabled @endif" title="Print Invoice"><i class="fas fa-print"></i></a>
                                    @else
                                        <button type="button" class="btn btn-light" disabled title="Renew (BELUM BAYAR)"><i class="fas fa-bolt"></i></button>
                                        <button type="button" class="btn btn-light" disabled title="Print Invoice"><i class="fas fa-print"></i></button>
                                    @endif
                                </div>
                            </td>
                            <td class="text-right">
                                @if($invoice)
                                    <form action="{{ route('invoices.pay', $invoice) }}" method="POST" class="d-inline" onsubmit="return confirm('Bayar dan perpanjang layanan sekarang?');">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-sm btn-success" title="Bayar" @disabled(! $canPay)>
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </form>
                                    <form action="{{ route('invoices.destroy', $invoice) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus pembayaran?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger" title="Hapus Pembayaran">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                @else
                                    <button type="button" class="btn btn-sm btn-light" disabled title="Bayar"><i class="fas fa-check"></i></button>
                                    <button type="button" class="btn btn-sm btn-light" disabled title="Hapus Pembayaran"><i class="fas fa-trash"></i></button>
                                @endif
                                <a href="{{ route('ppp-users.edit', $user) }}" class="btn btn-sm btn-warning text-white" title="Edit">
                                    <i class="fas fa-pen"></i>
                                </a>
                                <form action="{{ route('ppp-users.destroy', $user) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus user ini?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="text-center p-4">Belum ada user PPP.</td></tr>
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
        <form id="bulk-delete-form" action="{{ route('ppp-users.bulk-destroy') }}" method="POST">
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
    </script>
@endsection
