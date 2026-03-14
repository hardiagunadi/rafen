@php
$iconMap = [
    'created'       => ['fa-plus-circle',  'created'],
    'assigned'      => ['fa-user-tag',     'assigned'],
    'status_change' => ['fa-exchange-alt', 'status_change'],
    'note'          => ['fa-comment',      'note'],
];
[$icon, $cls] = $iconMap[$note->type] ?? ['fa-circle', 'note'];
$userName = $note->user ? ($note->user->nickname ?? $note->user->name) : '-';
@endphp
<div class="timeline-item">
    <div class="tl-icon {{ $cls }}">
        <i class="fas {{ $icon }}"></i>
    </div>
    <div class="tl-body">
        <div><strong>{{ $userName }}</strong></div>
        @if($note->note)
        <div class="tl-note">{{ $note->note }}</div>
        @endif
        @if($note->image_path)
        <img src="{{ asset('storage/' . $note->image_path) }}" class="tl-img ticket-lightbox-img" alt="foto bukti">
        @endif
        <div class="tl-meta">
            @if($note->meta)<span>{{ $note->meta }}</span> · @endif
            {{ $note->created_at->format('d/m/Y H:i') }}
        </div>
    </div>
</div>
