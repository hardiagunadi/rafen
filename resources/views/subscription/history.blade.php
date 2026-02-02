@extends('layouts.admin')

@section('title', 'Riwayat Pembayaran')

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Riwayat Pembayaran Langganan</h3>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover text-nowrap">
            <thead>
                <tr>
                    <th>No. Pembayaran</th>
                    <th>Paket</th>
                    <th>Metode</th>
                    <th>Jumlah</th>
                    <th>Status</th>
                    <th>Tanggal</th>
                </tr>
            </thead>
            <tbody>
                @forelse($payments as $payment)
                <tr>
                    <td>{{ $payment->payment_number }}</td>
                    <td>{{ $payment->subscription?->plan?->name ?? '-' }}</td>
                    <td>{{ $payment->payment_channel ?? '-' }}</td>
                    <td>Rp {{ number_format($payment->total_amount, 0, ',', '.') }}</td>
                    <td>
                        @switch($payment->status)
                            @case('paid')
                                <span class="badge badge-success">Dibayar</span>
                                @break
                            @case('pending')
                                <span class="badge badge-warning">Menunggu</span>
                                @break
                            @case('expired')
                                <span class="badge badge-secondary">Kedaluwarsa</span>
                                @break
                            @case('failed')
                                <span class="badge badge-danger">Gagal</span>
                                @break
                        @endswitch
                    </td>
                    <td>{{ $payment->created_at->format('d M Y H:i') }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center text-muted">Belum ada riwayat pembayaran</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($payments->hasPages())
    <div class="card-footer">
        {{ $payments->links() }}
    </div>
    @endif
</div>
@endsection
