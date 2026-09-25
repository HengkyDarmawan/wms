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

    @if ($setup)
        <div class="alert alert-info d-flex flex-wrap justify-content-between align-items-center gap-2" role="status">
            <span><i class="bi bi-rocket-takeoff"></i> {{ __('Setup awal company: :done dari :total langkah selesai.', ['done' => $setup['done'], 'total' => $setup['total']]) }}</span>
            <a class="btn btn-sm btn-info" href="{{ route('setup.index') }}">{{ __('Lanjutkan setup') }}</a>
        </div>
    @endif

    <h2 class="h6 text-uppercase text-muted mb-2">{{ __('Pekerjaan menunggu') }}</h2>
    <div class="row g-3 mb-4">
        @forelse ($cards as $c)
            <div class="col-6 col-md-4 col-xl-3">
                <a class="card h-100 text-decoration-none" href="{{ $c['url'] }}">
                    <div class="card-body d-flex align-items-start gap-3">
                        <i class="bi {{ $c['icon'] }} fs-3 text-{{ $c['count'] > 0 ? $c['tone'] : 'secondary' }}" aria-hidden="true"></i>
                        <div>
                            <div class="fs-4 fw-semibold text-body">{{ number_format($c['count'], 0, ',', '.') }}</div>
                            <div class="fw-semibold text-body">{{ $c['label'] }}</div>
                            <div class="small text-muted">{{ $c['hint'] }}</div>
                        </div>
                    </div>
                </a>
            </div>
        @empty
            <div class="col-12"><p class="text-muted mb-0">{{ __('Tidak ada antrean pekerjaan untuk peran Anda.') }}</p></div>
        @endforelse
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

    </div>
@endsection
