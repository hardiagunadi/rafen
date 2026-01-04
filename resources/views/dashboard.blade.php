@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
    <div class="row">
        <div class="col-lg-3 col-12">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3>Rp. {{ number_format($stats['income_today'], 0, ',', '.') }}</h3>
                    <p>Income Hari Ini (IDR)</p>
                </div>
                <div class="icon">
                    <i class="fas fa-shopping-bag"></i>
                </div>
                <button type="button" class="small-box-footer btn btn-link text-white" data-toggle="modal" data-target="#incomeModal">
                    Lihat Detil <i class="fas fa-arrow-circle-right"></i>
                </button>
            </div>
        </div>
        <div class="col-lg-3 col-12">
            <div class="small-box bg-warning">
                <div class="inner">
                    <h3>{{ $stats['invoice_count'] }} Invoice</h3>
                    <p>Data Tagihan</p>
                </div>
                <div class="icon">
                    <i class="fas fa-file-invoice"></i>
                </div>
                <a href="#" class="small-box-footer">Lihat Detil <i class="fas fa-arrow-circle-right"></i></a>
            </div>
        </div>
        <div class="col-lg-3 col-12">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3>{{ $stats['ppp_online'] }} Users</h3>
                    <p>PPP Online</p>
                </div>
                <div class="icon">
                    <i class="fas fa-exchange-alt"></i>
                </div>
                <a href="{{ route('ppp-users.index') }}" class="small-box-footer">Lihat Detil <i class="fas fa-arrow-circle-right"></i></a>
            </div>
        </div>
        <div class="col-lg-3 col-12">
            <div class="small-box bg-danger">
                <div class="inner">
                    <h3>{{ $stats['hotspot_online'] }} Users</h3>
                    <p>Hotspot Online</p>
                </div>
                <div class="icon">
                    <i class="fas fa-signal"></i>
                </div>
                    <a href="{{ route('radius-accounts.index') }}" class="small-box-footer">Lihat Detil <i class="fas fa-arrow-circle-right"></i></a>
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-3 col-6 mb-2">
            <button class="btn btn-light w-100 d-flex justify-content-between align-items-center px-2 py-2">
                <strong>UPTIME</strong>
                <span class="text-monospace ml-2 text-right">{{ $systemInfo['uptime'] }}</span>
            </button>
        </div>
        <div class="col-md-3 col-6 mb-2">
            <button class="btn btn-light w-100 d-flex justify-content-between align-items-center px-2 py-2">
                <strong>RAM</strong>
                <span class="text-monospace ml-2 text-right">{{ $systemInfo['ram_total'] }}</span>
            </button>
        </div>
        <div class="col-md-3 col-6 mb-2">
            <button class="btn btn-light w-100 d-flex justify-content-between align-items-center px-2 py-2">
                <strong>FREE RAM</strong>
                <span class="text-monospace ml-2 text-right">{{ $systemInfo['ram_free'] }}</span>
            </button>
        </div>
        <div class="col-md-3 col-6 mb-2">
            <button class="btn btn-light w-100 d-flex justify-content-between align-items-center px-2 py-2">
                <strong>HDD/SSD</strong>
                <span class="text-monospace ml-2 text-right">{{ $systemInfo['disk_free'] }} FREE</span>
            </button>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <ul class="nav nav-tabs card-header-tabs" role="tablist">
                <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#ringkasan" role="tab">Ringkasan</a></li>
                <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#aktivitas" role="tab">Aktivitas</a></li>
                <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#trafik" role="tab">Trafik</a></li>
            </ul>
            <div class="text-muted small">Informasi Layanan</div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-8">
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="ringkasan" role="tabpanel">
                            <div class="row text-center">
                                <div class="col-md-4 mb-3">
                                    <div class="p-3 border rounded bg-light">
                                        <div class="mb-2"><i class="fas fa-signal fa-2x text-secondary"></i></div>
                                        <div class="text-uppercase small">Hotspot User</div>
                                        <div class="badge badge-secondary mt-2">{{ $stats['hotspot_online'] }}</div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="p-3 border rounded bg-light">
                                        <div class="mb-2"><i class="fas fa-plug fa-2x text-secondary"></i></div>
                                        <div class="text-uppercase small">PPPoE User</div>
                                        <div class="badge badge-secondary mt-2">{{ $stats['ppp_online'] }}</div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="p-3 border rounded bg-light">
                                        <div class="mb-2"><i class="fas fa-random fa-2x text-secondary"></i></div>
                                        <div class="text-uppercase small">VPN User</div>
                                        <div class="badge badge-secondary mt-2">0</div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="p-3 border rounded bg-light">
                                        <div class="mb-2"><i class="fas fa-id-card fa-2x text-secondary"></i></div>
                                        <div class="text-uppercase small">Total Voucher</div>
                                        <div class="badge badge-secondary mt-2">0</div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="p-3 border rounded bg-light">
                                        <div class="mb-2"><i class="fas fa-print fa-2x text-secondary"></i></div>
                                        <div class="text-uppercase small">VC Created Today</div>
                                        <div class="badge badge-secondary mt-2">0</div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="p-3 border rounded bg-light">
                                        <div class="mb-2"><i class="fas fa-sign-in-alt fa-2x text-secondary"></i></div>
                                        <div class="text-uppercase small">VC Login Today</div>
                                        <div class="badge badge-secondary mt-2">0</div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="p-3 border rounded bg-light">
                                        <div class="mb-2"><i class="fas fa-calendar-times fa-2x text-secondary"></i></div>
                                        <div class="text-uppercase small">Exp Voucher</div>
                                        <div class="badge badge-secondary mt-2">0</div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="p-3 border rounded bg-light">
                                        <div class="mb-2"><i class="fas fa-user-times fa-2x text-secondary"></i></div>
                                        <div class="text-uppercase small">Exp Customer</div>
                                        <div class="badge badge-secondary mt-2">0</div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="p-3 border rounded bg-light">
                                        <div class="mb-2"><i class="fas fa-calendar-alt fa-2x text-secondary"></i></div>
                                        <div class="text-uppercase small">{{ now()->format('F d, Y') }}</div>
                                        <div class="badge badge-secondary mt-2">{{ now()->format('H:i:s') }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="aktivitas" role="tabpanel">
                            <p class="text-muted">Belum ada data aktivitas.</p>
                        </div>
                        <div class="tab-pane fade" id="trafik" role="tabpanel">
                            <p class="text-muted">Grafik trafik belum tersedia.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <h6 class="mb-3">Informasi Layanan</h6>
                    <ul class="list-group">
                        @foreach($serviceInfo as $service)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="font-weight-bold">{{ $service['label'] }}</div>
                                    <div class="text-muted small">{{ $service['status'] }}</div>
                                </div>
                                <div>
                                    <span class="badge badge-{{ $service['color'] }}">{{ $service['status'] }}</span>
                                    @if(!empty($service['action_route']))
                                        <button type="button" class="btn btn-sm btn-outline-secondary ml-2 service-action-btn" data-url="{{ $service['action_route'] }}">
                                            {{ $service['action'] }}
                                        </button>
                                    @elseif($service['action'])
                                        <button class="btn btn-sm btn-outline-secondary ml-2" disabled>{{ $service['action'] }}</button>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="incomeModal" tabindex="-1" aria-labelledby="incomeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="incomeModalLabel">Pendapatan Harian</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form method="GET" action="{{ route('reports.income') }}">
                        <div class="form-group">
                            <label class="d-block">Tipe User</label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="tipe_user" id="user-semua" value="semua" checked>
                                <label class="form-check-label" for="user-semua">SEMUA TIPE</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="tipe_user" id="user-customer" value="customer">
                                <label class="form-check-label" for="user-customer">CUSTOMER</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="tipe_user" id="user-voucher" value="voucher">
                                <label class="form-check-label" for="user-voucher">VOUCHER</label>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="service-type">Tipe Service</label>
                            <select class="form-control" id="service-type" name="service_type">
                                <option value="">- Semua Transaksi -</option>
                                <option value="pppoe">PPPoE</option>
                                <option value="hotspot">Hotspot</option>
                                <option value="voucher">Voucher</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="owner-filter">Owner Data</label>
                            <select class="form-control" id="owner-filter" name="owner_id">
                                <option value="">- Semua Owner -</option>
                                @foreach($owners as $owner)
                                    <option value="{{ $owner->id }}">{{ $owner->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="text-right mt-4">
                            <button type="submit" class="btn btn-primary">Lihat Laporan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const buttons = document.querySelectorAll('.service-action-btn');

            buttons.forEach(btn => {
                btn.addEventListener('click', async function () {
                    if (! this.dataset.url) {
                        return;
                    }

                    const originalHtml = this.innerHTML;
                    this.disabled = true;
                    this.innerHTML = '<span class="spinner-border spinner-border-sm mr-1" role="status" aria-hidden="true"></span> Processing...';

                    try {
                        const response = await fetch(this.dataset.url, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json',
                            },
                        });

                        const data = await response.json();
                        if (response.ok && data?.status === 'ok') {
                            this.classList.remove('btn-outline-secondary');
                            this.classList.add('btn-success');
                            this.innerHTML = 'Reloaded';
                        } else {
                            throw new Error(data?.message || 'Gagal memproses permintaan.');
                        }
                    } catch (e) {
                        alert(e?.message || 'Gagal memproses permintaan.');
                        this.disabled = false;
                        this.innerHTML = originalHtml;
                    }
                });
            });
        })();
    </script>
@endpush
