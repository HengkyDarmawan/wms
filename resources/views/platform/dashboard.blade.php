@extends('layouts.platform')

@section('title', __('Company'))

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Company') }}</h1>
            <p class="text-muted mb-0">{{ __('Daftar company beserta status langganannya.') }}</p>
        </div>
        <div class="d-flex gap-2">
            @if ($pendingPayments > 0)
                <a class="btn btn-outline-warning" href="{{ route('platform.payments.index') }}">{{ __(':n bukti bayar menunggu', ['n' => $pendingPayments]) }}</a>
            @endif
            <a class="btn btn-primary" href="{{ route('platform.companies.create') }}">{{ __('Company baru') }}</a>
        </div>
    </div>

    <form class="mb-3" method="GET" action="{{ route('platform.dashboard') }}">
        <label class="visually-hidden" for="cari-company">{{ __('Cari') }}</label>
        <input class="form-control" id="cari-company" name="q" type="search" value="{{ $cari }}" placeholder="{{ __('Cari nama, kode, atau subdomain') }}">
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Company') }}</th>
                        <th scope="col">{{ __('Subdomain') }}</th>
                        <th scope="col">{{ __('Paket') }}</th>
                        <th scope="col">{{ __('Status company') }}</th>
                        <th scope="col">{{ __('Langganan') }}</th>
                        <th scope="col">{{ __('Masa berjalan sampai') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($companies as $company)
                        <tr>
                            <td>
                                <a class="fw-semibold" href="{{ route('platform.companies.show', $company->id) }}">{{ $company->name }}</a>
                                <div class="small text-muted">{{ $company->code }}</div>
                            </td>
                            <td>{{ $company->host() }}</td>
                            <td>{{ $company->plan?->name ?? '—' }}</td>
                            <td>
                                {{ $company->status->label() }}
                                @if ($company->provisioningError()) <span class="badge text-bg-danger">{{ __('gagal disiapkan') }}</span> @endif
                            </td>
                            <td>
                                {{ $company->subscription?->status->label() ?? '—' }}
                                @if ($company->subscription?->purge_after && $company->subscription->purge_after->isPast())
                                    <span class="badge text-bg-dark">{{ __('siap dihapus') }}</span>
                                @endif
                            </td>
                            <td>{{ $company->subscription?->periodEnd()?->format('d/m/Y') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">{{ __('Belum ada company.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($companies->hasPages())
            <div class="card-footer">{{ $companies->links() }}</div>
        @endif
    </div>
@endsection
