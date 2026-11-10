@extends('layouts.app')

@section('title', __('Hitung opname offline'))

@section('content')
    <div data-offline-count data-user="{{ auth()->id() }}" data-data="{{ route('offline.count.data') }}" data-sync="{{ route('offline.sync') }}">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <div>
                <h1 class="h3 mb-1">{{ __('Hitung opname offline') }}</h1>
                <p class="text-muted mb-0">{{ __('Buka halaman ini sekali saat ada sinyal; setelah itu hitung tetap bisa diisi tanpa sinyal dan terkirim otomatis saat sinyal kembali.') }}</p>
            </div>
            <a wire:navigate class="btn btn-outline-secondary" href="{{ route('count-tasks.index') }}">{{ __('Hitungan saya (online)') }}</a>
        </div>
        <div class="alert alert-secondary py-2 small" role="status" aria-live="polite" data-status>{{ __('Memuat…') }}</div>
        <div class="d-flex flex-wrap gap-2 align-items-center mb-3 small">
            <button class="btn btn-sm btn-outline-primary" type="button" data-unduh><i class="bi bi-cloud-download" aria-hidden="true"></i> {{ __('Perbarui tugas') }}</button>
            <button class="btn btn-sm btn-outline-primary" type="button" data-kirim><i class="bi bi-cloud-upload" aria-hidden="true"></i> {{ __('Kirim sekarang') }}</button>
            <span class="text-muted">{{ __('Belum terkirim') }}: <b data-jumlah-antrean>0</b> · {{ __('Tugas diunduh') }}: <span data-diunduh>-</span></span>
        </div>
        <div data-daftar></div>
    </div>
@endsection
