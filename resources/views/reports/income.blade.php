@extends('layouts.admin')

@section('title', 'Pendapatan Harian')

@section('content')
    <div class="card">
        <div class="card-header">
            <h4 class="mb-0">Pendapatan Harian</h4>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('reports.income') }}" class="mb-4">
                <div class="form-group">
                    <label class="d-block">Tipe User</label>
                    @php $tipeUser = $filters['tipe_user'] ?? 'semua'; @endphp
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="tipe_user" id="tipe-semua" value="semua" @checked($tipeUser === 'semua')>
                        <label class="form-check-label" for="tipe-semua">SEMUA TIPE</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="tipe_user" id="tipe-customer" value="customer" @checked($tipeUser === 'customer')>
                        <label class="form-check-label" for="tipe-customer">CUSTOMER</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="tipe_user" id="tipe-voucher" value="voucher" @checked($tipeUser === 'voucher')>
                        <label class="form-check-label" for="tipe-voucher">VOUCHER</label>
                    </div>
                </div>
                <div class="form-group">
                    <label for="service-type">Tipe Service</label>
                    <select class="form-control" id="service-type" name="service_type">
                        <option value="" @selected(($filters['service_type'] ?? '') === '')>- Semua Transaksi -</option>
                        <option value="pppoe" @selected(($filters['service_type'] ?? '') === 'pppoe')>PPPoE</option>
                        <option value="hotspot" @selected(($filters['service_type'] ?? '') === 'hotspot')>Hotspot</option>
                        <option value="voucher" @selected(($filters['service_type'] ?? '') === 'voucher')>Voucher</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="owner-filter">Owner Data</label>
                    <select class="form-control" id="owner-filter" name="owner_id">
                        <option value="" @selected(($filters['owner_id'] ?? '') === '')>- Semua Owner -</option>
                        @foreach($owners as $owner)
                            <option value="{{ $owner->id }}" @selected(($filters['owner_id'] ?? '') == $owner->id)>{{ $owner->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="text-right">
                    <button type="submit" class="btn btn-primary">Lihat Laporan</button>
                </div>
            </form>

            <div class="alert alert-info">
                Total Pendapatan Hari Ini: <strong>Rp {{ number_format($report['total'], 0, ',', '.') }}</strong> ({{ $report['currency'] }})
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Tipe User</th>
                            <th>Service</th>
                            <th>Owner</th>
                            <th>Jumlah (IDR)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($report['items'] as $item)
                            <tr>
                                <td>{{ $item['time'] }}</td>
                                <td>{{ strtoupper($item['user_type']) }}</td>
                                <td>{{ strtoupper($item['service']) }}</td>
                                <td>{{ $item['owner'] }}</td>
                                <td class="text-right">{{ number_format($item['amount'], 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted">Belum ada transaksi untuk filter ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
