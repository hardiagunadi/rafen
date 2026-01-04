@extends('layouts.admin')

@section('title', 'Pengaturan Koneksi')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h3 class="card-title mb-0">Router [ NAS ]</h3>
            </div>
            <a href="{{ route('mikrotik-connections.create') }}" class="btn btn-info btn-sm text-white">
                <i class="fas fa-plus"></i> TAMBAH ROUTER [NAS]
            </a>
        </div>
        <div class="card-body">
            <div class="mb-3">
                <strong>INFO :</strong>
                <ul class="mb-0">
                    <li>Sistem akan mengecek status ping ke router setiap 5 menit</li>
                    <li>Tabel Router akan di refresh otomatis setiap 1 menit</li>
                    <li>Status online mensyaratkan ping sukses <strong>dan</strong> port API (SSL/non-SSL) terbuka.</li>
                </ul>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="thead-light">
                    <tr>
                        <th style="width:60px;">API</th>
                        <th>Status Ping</th>
                        <th>Detail</th>
                        <th>Nama Router</th>
                        <th>IP Address</th>
                        <th>Zona Waktu</th>
                        <th>Deskripsi</th>
                        <th>User Online</th>
                        <th>Cek Status Terakhir</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($connections as $connection)
                        @php
                            $staleSeconds = (int) config('ping.stale_seconds', 60);
                            $isStale = $connection->last_ping_at && $connection->last_ping_at->diffInSeconds(now()) > $staleSeconds;
                        @endphp
                        <tr>
                            <td>
                                <button type="button" class="btn btn-info btn-sm text-white">
                                    <i class="fas fa-plug"></i>
                                </button>
                            </td>
                            <td>
                                @if ($isStale)
                                    <span class="badge badge-danger">offline</span>
                                @elseif ($connection->is_online === null)
                                    <span class="badge badge-warning">belum dicek</span>
                                @elseif ($connection->is_online)
                                    <span class="badge badge-success">online</span>
                                @else
                                    <span class="badge badge-danger">offline</span>
                                @endif
                            </td>
                            <td>
                                @if ($connection->last_ping_message)
                                    <div>{{ $connection->last_ping_message }}</div>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>{{ $connection->name }}</td>
                            <td>{{ $connection->host }}</td>
                            <td>+07:00<br><small class="text-muted">Asia/Jakarta</small></td>
                            <td>{{ $connection->notes ?? '-' }}</td>
                            <td><i class="fas fa-chart-bar"></i> <strong>active {{ $connection->radius_accounts_count }}</strong></td>
                            <td>
                                @if ($connection->last_ping_at)
                                    <div>{{ $connection->last_ping_at->format('Y-m-d') }}</div>
                                    <div class="text-muted">{{ $connection->last_ping_at->format('H:i:s') }}</div>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <a href="{{ route('mikrotik-connections.edit', $connection) }}" class="btn btn-sm btn-info text-white mb-1">
                                    <i class="fas fa-pen"></i>
                                </a>
                                <form action="{{ route('mikrotik-connections.destroy', $connection) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus koneksi ini?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-warning text-white mb-1">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center p-4">Belum ada koneksi Mikrotik.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($connections->hasPages())
                <div class="d-flex justify-content-end mt-3">
                    {{ $connections->links() }}
                </div>
            @endif
        </div>
    </div>
    <script>
        setInterval(function () {
            window.location.reload();
        }, 60000);
    </script>
@endsection
