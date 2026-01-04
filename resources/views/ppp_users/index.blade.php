@extends('layouts.admin')

@section('title', 'User PPP')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0">User PPP</h4>
            </div>
            <div class="btn-group">
                <div class="dropdown">
                    <button class="btn btn-success btn-sm dropdown-toggle" type="button" id="managementDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-bars"></i> Manajemen Pelanggan
                    </button>
                    <div class="dropdown-menu dropdown-menu-right" aria-labelledby="managementDropdown" style="min-width: 240px;">
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
        </div>
        <form id="bulk-delete-form" action="{{ route('ppp-users.bulk-destroy') }}" method="POST">
            @csrf
            @method('DELETE')
            <div class="card-body p-0">
                <table class="table table-striped mb-0">
                    <thead>
                    <tr>
                        <th style="width:40px;"><input type="checkbox" id="select-all"></th>
                        <th>Owner Data</th>
                        <th>Status Registrasi</th>
                        <th>Tipe Bayar</th>
                        <th>Status Bayar</th>
                        <th>Status Akun</th>
                        <th>Tipe Service</th>
                        <th>Tipe IP</th>
                        <th>Jatuh Tempo</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($users as $user)
                        <tr>
                            <td><input type="checkbox" name="ids[]" value="{{ $user->id }}"></td>
                            <td>{{ $user->owner?->name ?? '-' }}</td>
                            <td>{{ strtoupper($user->status_registrasi) }}</td>
                            <td>{{ strtoupper($user->tipe_pembayaran) }}</td>
                            <td>{{ strtoupper(str_replace('_', ' ', $user->status_bayar)) }}</td>
                            <td>{{ strtoupper($user->status_akun) }}</td>
                            <td>{{ strtoupper(str_replace('_', '/', $user->tipe_service)) }}</td>
                            <td>{{ strtoupper($user->tipe_ip) }}</td>
                            <td>{{ $user->jatuh_tempo?->format('Y-m-d') ?? '-' }}</td>
                            <td class="text-right">
                                <a href="{{ route('ppp-users.edit', $user) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                <form action="{{ route('ppp-users.destroy', $user) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus user ini?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="text-center p-4">Belum ada user PPP.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($users->hasPages())
                <div class="card-footer">
                    {{ $users->links() }}
                </div>
            @endif
        </form>
    </div>
    <script>
        document.getElementById('select-all').addEventListener('change', function (e) {
            document.querySelectorAll('input[name="ids[]"]').forEach(cb => cb.checked = e.target.checked);
        });
    </script>
@endsection
