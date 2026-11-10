@extends('layouts.app')

@section('title', __('Tugas gudang offline'))

@section('content')
    <div data-offline-tasks data-user="{{ auth()->id() }}" data-data="{{ route('offline.tasks.data') }}" data-sync="{{ route('offline.sync') }}">
        <div class="mb-3">
            <h1 class="h3 mb-1">{{ __('Tugas gudang offline') }}</h1>
            <p class="text-muted mb-0">{{ __('Buka halaman ini sekali saat ada sinyal. Setelah itu put-away, picking, penerimaan, konfirmasi muat, dan bukti terima bisa diisi tanpa sinyal, lalu terkirim otomatis saat sinyal kembali.') }}</p>
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
