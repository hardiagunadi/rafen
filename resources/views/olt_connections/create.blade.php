@extends('layouts.admin')

@section('title', 'Tambah OLT HSGQ')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="mb-0">Tambah Koneksi OLT HSGQ</h4>
        <a href="{{ route('olt-connections.index') }}" class="btn btn-sm btn-secondary">
            <i class="fas fa-arrow-left mr-1"></i>Kembali
        </a>
    </div>
    <form action="{{ route('olt-connections.store') }}" method="POST">
        @csrf
        <div class="card-body">
            @if ($errors->any())
                <div class="alert alert-danger">
                    <strong>Data belum valid:</strong>
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @include('olt_connections._form', ['oltConnection' => null])
        </div>
        <div class="card-footer d-flex justify-content-between">
            <a href="{{ route('olt-connections.index') }}" class="btn btn-link">Batal</a>
            <button type="submit" class="btn btn-primary">Simpan Koneksi OLT</button>
        </div>
    </form>
</div>
@endsection
