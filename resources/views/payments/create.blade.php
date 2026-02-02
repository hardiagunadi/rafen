@extends('layouts.admin')

@section('title', 'Bayar Invoice')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Pembayaran Invoice #{{ $invoice->invoice_number }}</h3>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h5>Detail Invoice</h5>
                        <table class="table table-sm">
                            <tr>
                                <td>No. Invoice</td>
                                <td><strong>{{ $invoice->invoice_number }}</strong></td>
                            </tr>
                            <tr>
                                <td>Pelanggan</td>
                                <td>{{ $invoice->customer_name }}</td>
                            </tr>
                            <tr>
                                <td>Paket</td>
                                <td>{{ $invoice->paket_langganan }}</td>
                            </tr>
                            <tr>
                                <td>Jatuh Tempo</td>
                                <td>{{ $invoice->due_date?->format('d M Y') ?? '-' }}</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h6 class="text-muted">Total Pembayaran</h6>
                                <h2 class="text-primary mb-0">
                                    Rp {{ number_format($invoice->total, 0, ',', '.') }}
                                </h2>
                            </div>
                        </div>
                    </div>
                </div>

                <hr>

                <form action="{{ route('payments.store-for-invoice', $invoice) }}" method="POST">
                    @csrf
                    <h5 class="mb-3">Pilih Metode Pembayaran</h5>

                    @foreach($groupedChannels as $groupKey => $group)
                        <h6 class="text-muted">{{ $group['name'] }}</h6>
                        <p class="small text-muted">{{ $group['description'] }}</p>
                        <div class="row mb-3">
                            @foreach($group['channels'] as $channel)
                            <div class="col-md-3 col-4 mb-2">
                                <div class="card payment-channel-card h-100" style="cursor: pointer;" onclick="selectChannel('{{ $channel['code'] }}')">
                                    <div class="card-body text-center p-2">
                                        <input type="radio" name="payment_channel" value="{{ $channel['code'] }}" id="channel_{{ $channel['code'] }}" class="d-none">
                                        @if(isset($channel['icon_url']))
                                        <img src="{{ $channel['icon_url'] }}" alt="{{ $channel['name'] }}" class="mb-1" style="height: 30px; max-width: 100%;">
                                        @else
                                        <i class="fas fa-credit-card fa-2x mb-1 text-primary"></i>
                                        @endif
                                        <p class="mb-0 small">{{ $channel['name'] }}</p>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @endforeach

                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-primary btn-lg" id="payBtn" disabled>
                            <i class="fas fa-lock"></i> Bayar Sekarang
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.payment-channel-card {
    transition: all 0.2s;
    border: 2px solid transparent;
}
.payment-channel-card:hover {
    border-color: #007bff;
}
.payment-channel-card.selected {
    border-color: #007bff;
    background-color: #f0f7ff;
}
</style>

@push('scripts')
<script>
function selectChannel(code) {
    document.querySelectorAll('.payment-channel-card').forEach(card => card.classList.remove('selected'));
    document.getElementById('channel_' + code).checked = true;
    document.getElementById('channel_' + code).closest('.payment-channel-card').classList.add('selected');
    document.getElementById('payBtn').disabled = false;
}
</script>
@endpush
@endsection
