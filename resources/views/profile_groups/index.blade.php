@extends('layouts.admin')

@section('title', 'Profil Group')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0">Profil Group</h4>
                <small class="text-muted">Kelola group Hotspot/PPPoE</small>
            </div>
            <div class="btn-group">
                <a href="{{ route('profile-groups.create') }}" class="btn btn-primary btn-sm">Tambah Group</a>
                <button type="button" class="btn btn-success btn-sm" id="bulk-export-btn" data-toggle="modal" data-target="#bulk-export-modal">
                    Ekspor Group Ke Router
                </button>
                <button type="button" class="btn btn-danger btn-sm" id="bulk-delete-btn">Hapus</button>
            </div>
        </div>
        @if ($errors->any())
            <div class="alert alert-danger m-3">
                {{ $errors->first() }}
            </div>
        @endif
        <form id="bulk-delete-form" action="{{ route('profile-groups.bulk-destroy') }}" method="POST">
            @csrf
            @method('DELETE')
            <div class="card-body p-0">
                <table class="table table-striped mb-0">
                    <thead>
                    <tr>
                        <th style="width:40px;"><input type="checkbox" id="select-all"></th>
                        <th>Nama Group</th>
                        <th>Owner Data</th>
                        <th>Router (NAS)</th>
                        <th>Tipe</th>
                        <th>Modul IP Pool</th>
                        <th>IP Pool Mikrotik</th>
                        <th>IP Pool SQL</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($groups as $group)
                        <tr>
                            <td><input type="checkbox" name="ids[]" value="{{ $group->id }}"></td>
                            <td>{{ $group->name }}</td>
                            <td>{{ $group->owner ?? '-' }}</td>
                            <td>{{ $group->mikrotikConnection?->name ?? 'Semua Router (NAS)' }}</td>
                            <td>{{ strtoupper($group->type) }}</td>
                            <td>{{ $group->ip_pool_mode === 'group_only' ? 'Group Only' : 'SQL IP Pool' }}</td>
                            <td>
                                @if($group->ip_pool_mode === 'group_only')
                                    <div>Pool: {{ $group->ip_pool_name ?? '-' }}</div>
                                    <div>Range: {{ $group->range_start ?? '-' }} - {{ $group->range_end ?? '-' }}</div>
                                    <div>DNS: {{ $group->dns_servers ?? '-' }}</div>
                                    <div>Parent: {{ $group->parent_queue ?? '-' }}</div>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @if($group->ip_pool_mode === 'sql')
                                    <div>IP: {{ $group->ip_address ?? '-' }}</div>
                                    <div>Netmask: {{ $group->netmask ?? '-' }}</div>
                                    <div>HostMin-Max: {{ $group->host_min ?? '-' }} - {{ $group->host_max ?? '-' }}</div>
                                    <div>DNS: {{ $group->dns_servers ?? '-' }}</div>
                                    <div>Parent: {{ $group->parent_queue ?? '-' }}</div>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <a href="{{ route('profile-groups.edit', $group) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                <form action="{{ route('profile-groups.destroy', $group) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus group ini?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center p-4">Belum ada profil group.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($groups->hasPages())
                <div class="card-footer">
                    {{ $groups->links() }}
                </div>
            @endif
        </form>
    </div>

    <div class="modal fade" id="bulk-export-modal" tabindex="-1" aria-labelledby="bulk-export-modal-label" aria-hidden="true">
        <div class="modal-dialog">
            <form class="modal-content" id="bulk-export-form" action="{{ route('profile-groups.export-bulk') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="bulk-export-modal-label">Ekspor Profil Group ke Router</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-2">Pilih router (NAS) yang akan menerima export.</p>
                    <div class="form-group mb-2">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="select-all-routers">
                            <label class="custom-control-label" for="select-all-routers">Pilih semua router</label>
                        </div>
                    </div>
                    <div class="form-group">
                        @forelse($mikrotikConnections as $connection)
                            <div class="custom-control custom-checkbox mb-1">
                                <input type="checkbox"
                                       class="custom-control-input router-checkbox"
                                       id="router-{{ $connection->id }}"
                                       name="mikrotik_connection_ids[]"
                                       value="{{ $connection->id }}">
                                <label class="custom-control-label" for="router-{{ $connection->id }}">
                                    {{ $connection->name }}
                                </label>
                            </div>
                        @empty
                            <div class="text-muted">Belum ada router aktif.</div>
                        @endforelse
                    </div>
                    <div id="bulk-export-group-ids"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success">Ekspor</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('select-all').addEventListener('change', function (e) {
            document.querySelectorAll('input[name="ids[]"]').forEach(cb => cb.checked = e.target.checked);
        });
        document.getElementById('bulk-delete-btn').addEventListener('click', function () {
            const any = Array.from(document.querySelectorAll('input[name="ids[]"]')).some(cb => cb.checked);
            if (! any) {
                alert('Pilih minimal satu group untuk dihapus.');
                return;
            }
            if (confirm('Hapus group terpilih?')) {
                document.getElementById('bulk-delete-form').submit();
            }
        });

        document.getElementById('bulk-export-btn').addEventListener('click', function (event) {
            const selected = Array.from(document.querySelectorAll('input[name="ids[]"]')).filter(cb => cb.checked);
            if (! selected.length) {
                event.preventDefault();
                event.stopPropagation();
                alert('Pilih minimal satu group untuk export.');
                return;
            }

            const container = document.getElementById('bulk-export-group-ids');
            container.innerHTML = '';
            selected.forEach(cb => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'profile_group_ids[]';
                input.value = cb.value;
                container.appendChild(input);
            });
        });

        const selectAllRouters = document.getElementById('select-all-routers');
        if (selectAllRouters) {
            selectAllRouters.addEventListener('change', function (e) {
                document.querySelectorAll('.router-checkbox').forEach(cb => cb.checked = e.target.checked);
            });
        }
    </script>
@endsection
