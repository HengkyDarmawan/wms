@extends('layouts.platform')

@section('title', $plan->exists ? __('Ubah paket') : __('Paket baru'))

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h1 class="h3 mb-0">{{ $plan->exists ? __('Ubah paket').' '.$plan->name : __('Paket baru') }}</h1>
        <a class="btn btn-outline-secondary" href="{{ route('platform.plans.index') }}">{{ __('Kembali') }}</a>
    </div>

    <form class="card" method="POST" action="{{ $plan->exists ? route('platform.plans.update', $plan->id) : route('platform.plans.store') }}" novalidate>
        @csrf
        <div class="card-body row g-3">
            <div class="col-md-3">
                <label class="form-label" for="kode">{{ __('Kode') }} <span class="wajib">*</span></label>
                <input class="form-control @error('code') is-invalid @enderror" id="kode" name="code" value="{{ old('code', $plan->code) }}" maxlength="30" required>
                @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-5">
                <label class="form-label" for="nama">{{ __('Nama') }} <span class="wajib">*</span></label>
                <input class="form-control @error('name') is-invalid @enderror" id="nama" name="name" value="{{ old('name', $plan->name) }}" maxlength="80" required>
                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="harga">{{ __('Harga bulanan (Rp)') }} <span class="wajib">*</span></label>
                <input class="form-control @error('monthly_price') is-invalid @enderror" id="harga" name="monthly_price" type="number" min="0" step="1" value="{{ old('monthly_price', $plan->monthly_price !== null ? (int) $plan->monthly_price : '') }}" required>
                @error('monthly_price') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3">
                <label class="form-label" for="trial">{{ __('Durasi trial bawaan (hari)') }} <span class="wajib">*</span></label>
                <input class="form-control @error('trial_days') is-invalid @enderror" id="trial" name="trial_days" type="number" min="0" max="90" value="{{ old('trial_days', $plan->trial_days) }}" required>
                @error('trial_days') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3">
                <label class="form-label" for="wa">{{ __('Kuota WA / bulan') }}</label>
                <input class="form-control" id="wa" name="wa_quota" type="number" min="0" value="{{ old('wa_quota', $plan->wa_quota) }}">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="berkas">{{ __('Kuota berkas (MB)') }}</label>
                <input class="form-control" id="berkas" name="storage_quota_mb" type="number" min="0" value="{{ old('storage_quota_mb', $plan->storage_quota_mb) }}">
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="aktif" name="is_active" value="1" @checked(old('is_active', $plan->is_active))>
                    <label class="form-check-label" for="aktif">{{ __('Aktif (bisa dipilih untuk company baru)') }}</label>
                </div>
            </div>
        </div>
        <div class="card-footer"><button class="btn btn-primary" type="submit">{{ __('Simpan') }}</button></div>
    </form>
@endsection
