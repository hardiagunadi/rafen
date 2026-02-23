@extends('layouts.admin')

@section('title', 'Profil Hotspot')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0">Profil Hotspot</h4>
            </div>
            <div class="btn-group">
                <a href="{{ route('hotspot-profiles.create') }}" class="btn btn-primary btn-sm">Tambah Profil</a>
                <button type="button" class="btn btn-danger btn-sm" id="bulk-delete-btn">Hapus</button>
            </div>
        </div>
        <form id="bulk-delete-form" action="{{ route('hotspot-profiles.bulk-destroy') }}" method="POST">
            @csrf
            @method('DELETE')
            <div class="card-body p-0">
                <table class="table table-striped mb-0">
                    <thead>
                    <tr>
                        <th style="width:40px;"><input type="checkbox" id="select-all"></th>
                        <th>Nama</th>
                        <th>Owner Data</th>
                        <th>Harga Jual</th>
                        <th>Harga Promo</th>
                        <th>PPN</th>
                        <th>Bandwidth</th>
                        <th>Tipe Profil</th>
                        <th>Profil Group</th>
                        <th>Shared Users</th>
                        <th>Prioritas</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($profiles as $profile)
                        <tr>
                            <td><input type="checkbox" name="ids[]" value="{{ $profile->id }}"></td>
                            <td>{{ $profile->name }}</td>
                            <td>{{ $profile->owner?->name ?? '-' }}</td>
                            <td>{{ number_format($profile->harga_jual, 2) }}</td>
                            <td>{{ number_format($profile->harga_promo, 2) }}</td>
                            <td>{{ number_format($profile->ppn, 2) }}%</td>
                            <td>{{ $profile->bandwidthProfile?->name ?? '-' }}</td>
                            <td>
                                @if($profile->profile_type === 'unlimited')
                                    <span class="badge badge-success">Unlimited</span>
                                    <div class="small text-muted">Masa Aktif: {{ $profile->masa_aktif_value }} {{ $profile->masa_aktif_unit }}</div>
                                @elseif($profile->limit_type === 'time')
                                    <span class="badge badge-info">Limited - TimeBase</span>
                                    <div class="small text-muted">{{ $profile->time_limit_value }} {{ $profile->time_limit_unit }}</div>
                                @elseif($profile->limit_type === 'quota')
                                    <span class="badge badge-info">Limited - QuotaBase</span>
                                    <div class="small text-muted">{{ $profile->quota_limit_value }} {{ strtoupper($profile->quota_limit_unit) }}</div>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>{{ $profile->profileGroup?->name ?? '-' }}</td>
                            <td>{{ $profile->shared_users }}</td>
                            <td>
                                @if($profile->prioritas === 'default')
                                    Default
                                @else
                                    Prioritas {{ (int) str_replace('prioritas', '', $profile->prioritas) }}
                                @endif
                            </td>
                            <td class="text-right">
                                <a href="{{ route('hotspot-profiles.edit', $profile) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                <button type="button" class="btn btn-sm btn-outline-danger"
                                    data-ajax-delete="{{ route('hotspot-profiles.destroy', $profile) }}"
                                    data-confirm="Hapus profil ini?">Delete</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="12" class="text-center p-4">Belum ada profil Hotspot.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($profiles->hasPages())
                <div class="card-footer">
                    {{ $profiles->links() }}
                </div>
            @endif
        </form>
    </div>

    <script>
        const selectAll = document.getElementById('select-all');
        if (selectAll) {
            selectAll.addEventListener('change', function (e) {
                document.querySelectorAll('input[name="ids[]"]').forEach(cb => cb.checked = e.target.checked);
            });
        }
        const bulkBtn = document.getElementById('bulk-delete-btn');
        if (bulkBtn) {
            bulkBtn.addEventListener('click', function () {
                const any = Array.from(document.querySelectorAll('input[name="ids[]"]')).some(cb => cb.checked);
                if (! any) {
                    alert('Pilih minimal satu profil untuk dihapus.');
                    return;
                }
                if (confirm('Hapus profil terpilih?')) {
                    const form = document.getElementById('bulk-delete-form');
                    const ids = Array.from(document.querySelectorAll('input[name="ids[]"]:checked')).map(cb => cb.value);
                    const params = new URLSearchParams();
                    params.append('_method', 'DELETE');
                    ids.forEach(id => params.append('ids[]', id));
                    fetch(form.action, {
                        method: 'POST',
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                        body: params,
                    }).then(r => r.json()).then(function(data) {
                        AppAjax.showToast(data.message || data.status || 'Profil dihapus.', 'success');
                        document.querySelectorAll('input[name="ids[]"]:checked').forEach(cb => { const row = cb.closest('tr'); if (row) { row.style.opacity='0'; row.style.transition='opacity .3s'; setTimeout(()=>row.remove(),300); } });
                        document.getElementById('select-all').checked = false;
                    }).catch(function(err) {
                        AppAjax.showToast((err && err.error) || 'Gagal menghapus.', 'danger');
                    });
                }
            });
        }
    </script>
@endsection
