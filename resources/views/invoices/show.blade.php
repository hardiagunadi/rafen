@extends('layouts.admin')

@section('title', 'Invoice #' . $invoice->invoice_number)

@section('content')
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Detail Invoice</h3>
                <div class="card-tools">
                    @if($invoice->isUnpaid())
                        <span class="badge badge-warning">Belum Dibayar</span>
                    @else
                        <span class="badge badge-success">Lunas</span>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td class="text-muted">No. Invoice</td>
                                <td><strong>{{ $invoice->invoice_number }}</strong></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Pelanggan</td>
                                <td>{{ $invoice->customer_name }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">ID Pelanggan</td>
                                <td>{{ $invoice->customer_id ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Tipe Service</td>
                                <td>{{ strtoupper($invoice->tipe_service) }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Paket</td>
                                <td>{{ $invoice->paket_langganan ?? '-' }}</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td class="text-muted">Tanggal</td>
                                <td>{{ $invoice->created_at->format('d M Y') }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Jatuh Tempo</td>
                                <td>
                                    {{ $invoice->due_date ? $invoice->due_date->format('d M Y') : '-' }}
                                    @if($invoice->isOverdue())
                                        <span class="badge badge-danger">Terlambat</span>
                                    @endif
                                </td>
                            </tr>
                            @if($invoice->isPaid())
                            <tr>
                                <td class="text-muted">Dibayar Pada</td>
                                <td>{{ $invoice->paid_at?->format('d M Y H:i') ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Metode Pembayaran</td>
                                <td>{{ $invoice->payment_channel ?? $invoice->payment_method ?? '-' }}</td>
                            </tr>
                            @endif
                        </table>
                    </div>
                </div>

                <hr>

                <table class="table">
                    <thead>
                        <tr>
                            <th>Deskripsi</th>
                            <th class="text-right">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>{{ $invoice->paket_langganan ?? 'Layanan Internet' }}</td>
                            <td class="text-right">Rp {{ number_format($invoice->harga_dasar, 0, ',', '.') }}</td>
                        </tr>
                        @if($invoice->ppn_amount > 0)
                        <tr>
                            <td>PPN ({{ $invoice->ppn_percent }}%)</td>
                            <td class="text-right">Rp {{ number_format($invoice->ppn_amount, 0, ',', '.') }}</td>
                        </tr>
                        @endif
                    </tbody>
                    <tfoot>
                        <tr class="font-weight-bold">
                            <td>Total</td>
                            <td class="text-right">Rp {{ number_format($invoice->total, 0, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        @if($invoice->isUnpaid())
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Pembayaran</h3>
            </div>
            <div class="card-body">
                @if($settings && $settings->hasPaymentGateway())
                    <a href="{{ route('payments.create-for-invoice', $invoice) }}" class="btn btn-primary btn-block mb-3">
                        <i class="fas fa-credit-card"></i> Bayar Online (QRIS/VA)
                    </a>
                @endif

                <form action="{{ route('invoices.pay', $invoice) }}" method="POST">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn btn-success btn-block" onclick="return confirm('Konfirmasi pembayaran manual?')">
                        <i class="fas fa-check"></i> Konfirmasi Bayar Manual
                    </button>
                </form>
            </div>
        </div>

        @if($bankAccounts->count() > 0)
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Transfer ke Rekening</h3>
            </div>
            <div class="card-body p-0">
                @foreach($bankAccounts as $bank)
                <div class="p-3 {{ !$loop->last ? 'border-bottom' : '' }}">
                    <strong>{{ $bank->bank_name }}</strong>
                    @if($bank->is_primary)
                        <span class="badge badge-primary">Utama</span>
                    @endif
                    <br>
                    <span class="text-muted">{{ $bank->account_number }}</span><br>
                    <small>a.n. {{ $bank->account_name }}</small>
                </div>
                @endforeach
            </div>
        </div>
        @endif
        @else
        <div class="card card-success">
            <div class="card-header">
                <h3 class="card-title">Invoice Lunas</h3>
            </div>
            <div class="card-body text-center">
                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                <p>Invoice ini sudah dibayar.</p>
                @if($invoice->payment_reference)
                <p class="text-muted small">Ref: {{ $invoice->payment_reference }}</p>
                @endif
            </div>
        </div>
        @endif

        <div class="card">
            <div class="card-body">
                <a href="{{ route('invoices.index') }}" class="btn btn-secondary btn-block">
                    <i class="fas fa-arrow-left"></i> Kembali ke Daftar Invoice
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
