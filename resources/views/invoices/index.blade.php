@extends('layouts.admin')

@section('title', 'Data Tagihan')

@section('content')
    <div class="card">
        <div class="card-header">
            <h4 class="mb-0">Rekap Tagihan</h4>
        </div>
        <div class="card-body p-0">
            @if (session('status'))
                <div class="alert alert-success m-3">{{ session('status') }}</div>
            @endif
            <div class="table-responsive">
                <table class="table table-striped mb-0">
                    <thead class="thead-light">
                    <tr>
                        <th>ID Transaksi</th>
                        <th>Nomor Invoice</th>
                        <th>ID Pelanggan</th>
                        <th>Nama</th>
                        <th>Tipe Service</th>
                        <th>Paket Langganan</th>
                        <th>Tagihan (+PPN)</th>
                        <th>Jatuh Tempo</th>
                        <th>Owner Data</th>
                        <th>Renew | Print</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($invoices as $invoice)
                        <tr id="invoice-{{ $invoice->id }}">
                            <td>{{ $invoice->id }}</td>
                            <td>{{ $invoice->invoice_number }}</td>
                            <td>{{ $invoice->customer_id ?? '-' }}</td>
                            <td>{{ $invoice->customer_name ?? '-' }}</td>
                            <td>{{ strtoupper(str_replace('_', '/', $invoice->tipe_service ?? '')) }}</td>
                            <td>{{ $invoice->paket_langganan ?? '-' }}</td>
                            <td>Rp {{ number_format($invoice->total, 2, ',', '.') }}</td>
                            <td>{{ optional($invoice->due_date)->format('Y-m-d') ?? '-' }}</td>
                            <td>{{ $invoice->owner?->name ?? '-' }}</td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <form action="{{ route('invoices.pay', $invoice) }}" method="POST" class="d-inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-light" title="Renew / Pay"><i class="fas fa-bolt"></i></button>
                                    </form>
                                    <a href="{{ route('invoices.index') }}#invoice-{{ $invoice->id }}" class="btn btn-success" title="Print"><i class="fas fa-print"></i></a>
                                </div>
                            </td>
                            <td class="text-right">
                                <div class="btn-group btn-group-sm" role="group">
                                    @php $canPay = $invoice->status === 'unpaid'; $canRenew = $invoice->status === 'unpaid' && $invoice->created_at->equalTo($invoice->updated_at); @endphp
                                    <form action="{{ route('invoices.pay', $invoice) }}" method="POST" class="d-inline" onsubmit="return confirm('Bayar dan perpanjang layanan sekarang?');">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-success" title="Bayar" @disabled(! $canPay)><i class="fas fa-check"></i></button>
                                    </form>
                                    <form action="{{ route('invoices.destroy', $invoice) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus data pembayaran?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger" title="Hapus Pembayaran"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="text-center p-4">Belum ada tagihan.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($invoices->hasPages())
            <div class="card-footer">
                {{ $invoices->links() }}
            </div>
        @endif
    </div>
@endsection
