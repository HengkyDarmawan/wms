@extends('layouts.app')

@section('title', __('Beranda'))

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1">{{ __('Selamat datang, :nama', ['nama' => $user->name]) }}</h1>
            <p class="text-muted mb-0">{{ $company?->name }} &middot; {{ $company?->timezone }}</p>
        </div>
        <span class="badge text-bg-{{ $user->status()->badge() }}">{{ $user->status()->label() }}</span>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-6">
            <div class="card h-100">
                <div class="card-body">
                    <h2 class="h6 text-uppercase text-muted mb-3">{{ __('Penugasan role & cakupan') }}</h2>

                    @forelse ($assignments as $assignment)
                        <div class="d-flex align-items-center justify-content-between border-bottom py-2">
                            <div>
                                <div class="fw-semibold">{{ $assignment->role->name }}</div>
                                <div class="small text-muted">{{ $assignment->role->code }}</div>
                            </div>
                            <span class="badge text-bg-light">{{ $assignment->describeScope() }}</span>
                        </div>
                    @empty
                        <p class="text-muted mb-0">{{ __('Belum ada penugasan role.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="card h-100">
                <div class="card-body">
                    <h2 class="h6 text-uppercase text-muted mb-3">{{ __('Modul berikutnya') }}</h2>
                    <p class="text-muted mb-2">
                        {{ __('Modul Access sudah aktif. Master, Gudang, Stok, dan seterusnya dibangun berurutan sesuai Arsitektur §12.') }}
                    </p>
                    <ol class="small text-muted mb-0">
                        <li>{{ __('Master data (klien, proyek, item, satuan, vendor)') }}</li>
                        <li>{{ __('Gudang & lokasi (zona, rak, bin)') }}</li>
                        <li>{{ __('Stok: kartu stok, saldo, reservasi') }}</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
@endsection
