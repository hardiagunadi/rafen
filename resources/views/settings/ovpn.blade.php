@extends('layouts.admin')

@section('title', 'Pengaturan OpenVPN')

@section('content')
    <div class="card mb-4">
        <div class="card-header">
            <h4 class="mb-0">Informasi Koneksi OpenVPN</h4>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-2"><strong>IP/Host:</strong> {{ $ovpn['host'] !== '' ? $ovpn['host'] : '-' }}</div>
                    <div class="mb-2"><strong>Port:</strong> {{ $ovpn['port'] !== '' ? $ovpn['port'] : '-' }}</div>
                    <div class="mb-2"><strong>Proto:</strong> {{ $ovpn['proto'] !== '' ? strtoupper($ovpn['proto']) : '-' }}</div>
                    <div class="mb-2"><strong>Network:</strong> {{ $ovpn['network'] !== '' ? $ovpn['network'] : '-' }}</div>
                    <div class="mb-2"><strong>Netmask:</strong> {{ $ovpn['netmask'] !== '' ? $ovpn['netmask'] : '-' }}</div>
                </div>
                <div class="col-md-6">
                    <div class="mb-2"><strong>Username:</strong> {{ $ovpn['username'] !== '' ? $ovpn['username'] : '-' }}</div>
                    <div class="mb-2"><strong>Password:</strong> {{ $ovpn['password'] !== '' ? $ovpn['password'] : '-' }}</div>
                </div>
            </div>
            <div class="alert alert-info mb-0 mt-3">
                Untuk banyak Mikrotik, disarankan membuat client OpenVPN per router dan atur IP lokal
                statis via CCD (client-config-dir) agar tiap router punya IP berbeda.
            </div>
        </div>
    </div>
@endsection
