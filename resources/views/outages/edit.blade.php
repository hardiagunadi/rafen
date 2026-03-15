@extends('layouts.admin')

@section('title', 'Edit Gangguan #' . $outage->id)

@section('content')
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-edit"></i> Edit Insiden #{{ $outage->id }}</h5>
                <a href="{{ route('outages.show', $outage) }}" class="btn btn-sm btn-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
            <form id="editOutageForm">
                @csrf
                @method('PUT')
                <div class="card-body">
                    <div class="form-group">
                        <label>Judul</label>
                        <input type="text" name="title" class="form-control" value="{{ $outage->title }}">
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Severity</label>
                                <select name="severity" class="form-control">
                                    @foreach(['low','medium','high','critical'] as $s)
                                    <option value="{{ $s }}" {{ $outage->severity === $s ? 'selected' : '' }}>{{ strtoupper($s) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Waktu Mulai</label>
                                <input type="datetime-local" name="started_at" class="form-control"
                                       value="{{ $outage->started_at->format('Y-m-d\TH:i') }}">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Estimasi Selesai</label>
                        <input type="datetime-local" name="estimated_resolved_at" class="form-control"
                               value="{{ $outage->estimated_resolved_at?->format('Y-m-d\TH:i') }}">
                    </div>
                    <div class="form-group">
                        <label>Deskripsi</label>
                        <textarea name="description" class="form-control" rows="3">{{ $outage->description }}</textarea>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary" id="saveBtn">
                        <i class="fas fa-save"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('js')
<script>
document.getElementById('editOutageForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('saveBtn');
    btn.disabled = true;

    const fd = new FormData(this);
    fetch('{{ route('outages.update', $outage) }}', {
        method: 'POST',
        body: fd,
        headers: {'X-Requested-With': 'XMLHttpRequest'},
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            window.location.href = '{{ route('outages.show', $outage) }}';
        } else {
            alert(res.message || 'Gagal menyimpan.');
            btn.disabled = false;
        }
    })
    .catch(() => { alert('Terjadi kesalahan.'); btn.disabled = false; });
});
</script>
@endpush
