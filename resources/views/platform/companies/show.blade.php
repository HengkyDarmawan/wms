@extends('layouts.platform')

@section('title', $company->name)

@section('content')
    @php($sub = $company->subscription)
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ $company->name }} <span class="badge text-bg-secondary">{{ $company->status->label() }}</span></h1>
            <p class="text-muted mb-0">
                {{ $company->code }} · {{ $company->host() }} · {{ __('Database') }} {{ $company->db_name }} · {{ $company->timezone }}
                · {{ __('Admin pertama') }} {{ $company->getAttribute('admin_email') ?? '—' }}
            </p>
            @if ($company->getAttribute('status_reason')) <p class="small text-danger mb-0">{{ __('Alasan penangguhan') }}: {{ $company->getAttribute('status_reason') }}</p> @endif
        </div>
        <a class="btn btn-outline-secondary" href="{{ route('platform.dashboard') }}">{{ __('Kembali') }}</a>
    </div>

    @if ($company->provisioningError())
        <div class="alert alert-danger d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span>{{ __('Provisioning gagal') }}: {{ $company->provisioningError() }}</span>
            <form method="POST" action="{{ route('platform.companies.provision', $company->id) }}">
                @csrf
                <button class="btn btn-sm btn-light" type="submit">{{ __('Lanjutkan provisioning') }}</button>
            </form>
        </div>
    @endif

    <div class="row g-3 mb-3">
        @foreach ([
            [__('Langganan'), $sub?->status->label() ?? '—'],
            [__('Paket'), $sub?->plan?->name ?? $company->plan?->name ?? '—'],
            [__('Trial sampai'), $sub?->trial_ends_at?->format('d/m/Y') ?? '—'],
            [__('Masa berjalan sampai'), $sub?->periodEnd()?->format('d/m/Y') ?? '—'],
            [__('Tenggang sampai'), $sub?->grace_ends_at?->format('d/m/Y') ?? '—'],
            [__('Data disimpan sampai'), $sub?->purge_after?->format('d/m/Y') ?? '—'],
        ] as [$label, $nilai])
            <div class="col-6 col-md-2">
                <div class="card h-100"><div class="card-body py-2">
                    <div class="small text-muted">{{ $label }}</div>
                    <div class="fw-semibold">{{ $nilai }}</div>
                </div></div>
            </div>
        @endforeach
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header"><strong>{{ __('Tagihan & pembayaran') }}</strong></div>
                <ul class="list-group list-group-flush small">
                    @forelse ($invoices as $inv)
                        <li class="list-group-item">
                            <strong>{{ $inv->number }}</strong> <span class="badge {{ $inv->status->badge() }}">{{ $inv->status->label() }}</span>
                            · {{ $inv->period_start->format('d/m/Y') }} – {{ $inv->period_end->format('d/m/Y') }}
                            · {{ __('jatuh tempo') }} {{ $inv->due_date->format('d/m/Y') }}
                            · {{ __('Rp') }} {{ number_format((float) $inv->amount, 0, ',', '.') }}
                            @foreach ($inv->payments as $p)
                                <div class="ms-3 mt-1">
                                    {{ __('Bukti') }} #{{ $p->id }} · {{ $p->paid_at?->lokal()->format('d/m/Y') }} · {{ __('Rp') }} {{ number_format((float) $p->amount, 0, ',', '.') }} · {{ $p->uploaded_by_name }}
                                    <span class="badge {{ $p->status->badge() }}">{{ $p->status->label() }}</span>
                                    @if ($p->reject_reason) <span class="text-danger">— {{ $p->reject_reason }}</span> @endif
                                    @if ($p->proof_path) <a href="{{ route('platform.payments.proof', $p->id) }}" target="_blank" rel="noopener">{{ __('Lihat bukti') }}</a> @endif
                                </div>
                            @endforeach
                        </li>
                    @empty
                        <li class="list-group-item text-muted">{{ __('Belum ada tagihan.') }}</li>
                    @endforelse
                </ul>
                <div class="card-footer small"><a href="{{ route('platform.payments.index') }}">{{ __('Verifikasi bukti bayar') }}</a></div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><strong>{{ __('Riwayat') }}</strong></div>
                <ul class="list-group list-group-flush small">
                    @forelse ($history as $h)
                        <li class="list-group-item">{{ $h->created_at?->lokal()->format('d/m/Y H:i') }} · {{ $h->description }}</li>
                    @empty
                        <li class="list-group-item text-muted">{{ __('Belum ada riwayat.') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header"><strong>{{ __('Status company') }}</strong></div>
                <div class="card-body">
                    @if ($company->status === \App\Domain\Platform\Enums\CompanyStatus::Active)
                        <form method="POST" action="{{ route('platform.companies.suspend', $company->id) }}" class="d-grid gap-2">
                            @csrf
                            <label class="form-label small" for="alasan-tangguh">{{ __('Alasan penangguhan') }} <span class="wajib">*</span></label>
                            <input class="form-control form-control-sm" id="alasan-tangguh" name="reason" maxlength="255" required>
                            <button class="btn btn-sm btn-outline-danger" type="submit" onclick="return confirm('{{ __('Tangguhkan company ini? Semua user hanya bisa membaca.') }}')">{{ __('Tangguhkan company') }}</button>
                        </form>
                    @elseif ($company->status === \App\Domain\Platform\Enums\CompanyStatus::Suspended)
                        <form method="POST" action="{{ route('platform.companies.reactivate', $company->id) }}">
                            @csrf
                            <button class="btn btn-sm btn-outline-success w-100" type="submit">{{ __('Aktifkan kembali') }}</button>
                        </form>
                    @else
                        <p class="small text-muted mb-0">{{ __('Tidak ada tindakan untuk status ini.') }}</p>
                    @endif
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><strong>{{ __('Flag fitur') }}</strong></div>
                <ul class="list-group list-group-flush small">
                    @foreach ($flags as $kunci => $label)
                        @php($nyala = in_array($kunci, $enabled, true))
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>{{ $label }}</span>
                            <form method="POST" action="{{ route('platform.companies.flag', $company->id) }}">
                                @csrf
                                <input type="hidden" name="key" value="{{ $kunci }}">
                                <input type="hidden" name="enabled" value="{{ $nyala ? 0 : 1 }}">
                                <button class="btn btn-sm {{ $nyala ? 'btn-success' : 'btn-outline-secondary' }}" type="submit">{{ $nyala ? __('Nyala') : __('Mati') }}</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="card mb-3">
                <div class="card-header"><strong>{{ __('Akses dukungan') }}</strong> <span class="small text-muted">{{ __('diberikan Admin Company') }}</span></div>
                <ul class="list-group list-group-flush small">
                    @forelse ($supportAccesses as $a)
                        <li class="list-group-item">
                            {{ $a->platformUser?->name }} · {{ $a->starts_at->lokal()->format('d/m/Y H:i') }} – {{ $a->ends_at->lokal()->format('d/m/Y H:i') }}
                            @if ($a->revoked_at) <span class="badge text-bg-secondary">{{ __('dicabut') }}</span>
                            @elseif ($a->isActive()) <span class="badge text-bg-success">{{ __('berlaku') }}</span>
                            @endif
                            <div class="text-muted">{{ $a->reason }}</div>
                            @if ($a->isActive() && (int) $a->platform_user_id === (int) auth('platform')->id())
                                <form class="mt-1" method="POST" action="{{ route('platform.companies.support', $company->id) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-primary" type="submit">{{ __('Buka company (hanya-baca)') }}</button>
                                </form>
                            @endif
                        </li>
                    @empty
                        <li class="list-group-item text-muted">{{ __('Belum pernah diberikan.') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
@endsection
