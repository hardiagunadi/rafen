@php
$typeIcon = [
    'created'       => ['fas fa-plus-circle', 'text-success'],
    'status_change' => ['fas fa-exchange-alt', 'text-warning'],
    'note'          => ['fas fa-comment', 'text-info'],
    'resolved'      => ['fas fa-check-circle', 'text-success'],
    'assigned'      => ['fas fa-user-check', 'text-primary'],
];
[$icon, $color] = $typeIcon[$update->type] ?? ['fas fa-info-circle', 'text-muted'];
@endphp
<div class="timeline-item">
    <span class="time"><i class="fas fa-clock"></i> {{ $update->created_at?->format('d/m/Y H:i') }}</span>
    <h3 class="timeline-header {{ $color }}">
        <i class="{{ $icon }} mr-1"></i>
        {{ $update->user ? ($update->user->nickname ?? $update->user->name) : 'Sistem' }}
        @if(!$update->is_public)
            <span class="badge badge-light badge-sm ml-1" title="Hanya terlihat oleh staf">internal</span>
        @endif
    </h3>
    @if($update->meta)
    <div class="timeline-body text-muted small">{{ $update->meta }}</div>
    @endif
    @if($update->body)
    <div class="timeline-body">{{ $update->body }}</div>
    @endif
    @if($update->image_path)
    <div class="timeline-body">
        <a href="{{ asset('storage/'.$update->image_path) }}" target="_blank">
            <img src="{{ asset('storage/'.$update->image_path) }}" alt="Foto update" style="max-height:200px;border-radius:8px;">
        </a>
    </div>
    @endif
</div>
