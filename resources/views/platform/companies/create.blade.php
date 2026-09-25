@extends('layouts.platform')

@section('title', __('Company baru'))

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Company baru') }}</h1>
            <p class="text-muted mb-0">{{ __('Sistem membuat database company, mengisi data acuan, memulai trial, lalu mengirim undangan ke Admin Company.') }}</p>
        </div>
        <a class="btn btn-outline-secondary" href="{{ route('platform.dashboard') }}">{{ __('Kembali') }}</a>
    </div>

    <form class="card" method="POST" action="{{ route('platform.companies.store') }}" novalidate>
        @csrf
        <div class="card-body row g-3">
            <div class="col-md-2">
                <label class="form-label" for="kode">{{ __('Kode') }} <span class="wajib">*</span></label>
                <input class="form-control @error('code') is-invalid @enderror" id="kode" name="code" value="{{ old('code') }}" maxlength="10" required>
                @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text">{{ __('Huruf besar/angka; dipakai di nomor dokumen dan nama database.') }}</div>
            </div>
            <div class="col-md-5">
                <label class="form-label" for="nama">{{ __('Nama company') }} <span class="wajib">*</span></label>
                <input class="form-control @error('name') is-invalid @enderror" id="nama" name="name" value="{{ old('name') }}" maxlength="150" required>
                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-5">
                <label class="form-label" for="subdomain">{{ __('Subdomain') }} <span class="wajib">*</span></label>
                <div class="input-group">
                    <input class="form-control @error('subdomain') is-invalid @enderror" id="subdomain" name="subdomain" value="{{ old('subdomain') }}" maxlength="63" required>
                    <span class="input-group-text">.{{ $domain }}</span>
                    @error('subdomain') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="paket">{{ __('Paket') }} <span class="wajib">*</span></label>
                <select class="form-select @error('plan_id') is-invalid @enderror" id="paket" name="plan_id" required>
                    @foreach ($plans as $p)
                        <option value="{{ $p->id }}" @selected(old('plan_id') == $p->id)>{{ $p->name }} — {{ __('trial') }} {{ $p->trial_days }} {{ __('hari') }}</option>
                    @endforeach
                </select>
                @error('plan_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-2">
                <label class="form-label" for="trial">{{ __('Durasi trial (hari)') }}</label>
                <input class="form-control @error('trial_days') is-invalid @enderror" id="trial" name="trial_days" type="number" min="0" max="90" value="{{ old('trial_days') }}" placeholder="{{ __('ikut paket') }}">
                @error('trial_days') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3">
                <label class="form-label" for="zona">{{ __('Zona waktu') }} <span class="wajib">*</span></label>
                <select class="form-select" id="zona" name="timezone">
                    @foreach ($timezones as $nilai => $label)
                        <option value="{{ $nilai }}" @selected(old('timezone', 'Asia/Jakarta') === $nilai)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12"><hr class="my-1"><div class="small text-muted">{{ __('Admin Company pertama — menerima undangan untuk mengatur password.') }}</div></div>
            <div class="col-md-5">
                <label class="form-label" for="admin-nama">{{ __('Nama Admin Company') }} <span class="wajib">*</span></label>
                <input class="form-control @error('admin_name') is-invalid @enderror" id="admin-nama" name="admin_name" value="{{ old('admin_name') }}" maxlength="100" required>
                @error('admin_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-5">
                <label class="form-label" for="admin-email">{{ __('Email Admin Company') }} <span class="wajib">*</span></label>
                <input class="form-control @error('admin_email') is-invalid @enderror" id="admin-email" name="admin_email" type="email" value="{{ old('admin_email') }}" maxlength="150" required>
                @error('admin_email') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>
        <div class="card-footer">
            <button class="btn btn-primary" type="submit">{{ __('Buat company') }}</button>
        </div>
    </form>
@endsection
