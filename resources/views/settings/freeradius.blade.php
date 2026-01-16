@extends('layouts.admin')

@section('title', 'Pengaturan FreeRADIUS')

@section('content')
    <div class="card mb-4">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Status Sinkronisasi</h4>
                <form action="{{ route('settings.freeradius.sync') }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-success btn-sm">
                        Sync FreeRADIUS
                    </button>
                </form>
            </div>
        </div>
        <div class="card-body">
            <div class="mb-2"><strong>Clients Path:</strong> {{ $clientsPath ?: '-' }}</div>
            <div class="mb-2"><strong>Status:</strong> {{ $syncStatus['message'] }}</div>
            <div class="mb-2"><strong>Terakhir Update:</strong> {{ $syncStatus['updated_at'] ?? '-' }}</div>
            <div class="mb-2"><strong>Ukuran File:</strong> {{ $syncStatus['size'] !== null ? $syncStatus['size'].' bytes' : '-' }}</div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h4 class="mb-0">Log FreeRADIUS Terbaru</h4>
        </div>
        <div class="card-body">
            <div class="mb-2"><strong>Log Path:</strong> {{ $logPath ?: '-' }}</div>
            @if ($logPayload['error'])
                <div class="alert alert-danger mb-0">
                    {{ $logPayload['error'] }}
                </div>
            @else
                <pre class="mb-0" style="max-height: 420px; overflow:auto;">{{ implode("\n", $logPayload['lines']) }}</pre>
            @endif
        </div>
    </div>
@endsection
