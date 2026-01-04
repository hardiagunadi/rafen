@extends('layouts.admin')

@section('title', 'Profil Bandwidth')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0">Profil Bandwidth</h4>
            </div>
            <div class="btn-group">
                <a href="{{ route('bandwidth-profiles.create') }}" class="btn btn-primary btn-sm">Tambah Bandwidth</a>
                <button type="button" class="btn btn-danger btn-sm" id="bulk-delete-btn">Hapus</button>
            </div>
        </div>
        <form id="bulk-delete-form" action="{{ route('bandwidth-profiles.bulk-destroy') }}" method="POST">
            @csrf
            @method('DELETE')
            <div class="card-body p-0">
                <table class="table table-striped mb-0">
                    <thead>
                    <tr>
                        <th style="width:40px;">
                            <input type="checkbox" id="select-all">
                        </th>
                        <th>Nama Bandwidth</th>
                        <th>Upload (Min | Max) Mbps</th>
                        <th>Download (Min | Max) Mbps</th>
                        <th>Owner Data</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($profiles as $profile)
                        <tr>
                            <td>
                                <input type="checkbox" name="ids[]" value="{{ $profile->id }}">
                            </td>
                            <td>{{ $profile->name }}</td>
                            <td>{{ $profile->upload_min_mbps }} | {{ $profile->upload_max_mbps }}</td>
                            <td>{{ $profile->download_min_mbps }} | {{ $profile->download_max_mbps }}</td>
                            <td>{{ $profile->owner ?? '-' }}</td>
                            <td class="text-right">
                                <a href="{{ route('bandwidth-profiles.edit', $profile) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                <form action="{{ route('bandwidth-profiles.destroy', $profile) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus profil ini?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center p-4">Belum ada profil bandwidth.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($profiles->hasPages())
                <div class="card-footer">
                    {{ $profiles->links() }}
                </div>
            @endif
        </form>
    </div>

    <script>
        document.getElementById('select-all').addEventListener('change', function (e) {
            document.querySelectorAll('input[name="ids[]"]').forEach(cb => cb.checked = e.target.checked);
        });
        document.getElementById('bulk-delete-btn').addEventListener('click', function () {
            const anyChecked = Array.from(document.querySelectorAll('input[name="ids[]"]')).some(cb => cb.checked);
            if (! anyChecked) {
                alert('Pilih minimal satu profil untuk dihapus.');
                return;
            }
            if (confirm('Hapus profil terpilih?')) {
                document.getElementById('bulk-delete-form').submit();
            }
        });
    </script>
@endsection
