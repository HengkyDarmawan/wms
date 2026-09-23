@extends('layouts.auth')

@section('title', __('Beranda Super Admin'))

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Company') }}</h1>
            <p class="text-muted mb-0">{{ __('Daftar company beserta status langganannya.') }}</p>
        </div>

        <form method="POST" action="{{ route('platform.logout') }}">
            @csrf
            <button class="btn btn-outline-secondary btn-sm" type="submit">{{ __('Keluar') }}</button>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ __('Company') }}</th>
                    <th>{{ __('Subdomain') }}</th>
                    <th>{{ __('Status company') }}</th>
                    <th>{{ __('Langganan') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($companies as $company)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $company->name }}</div>
                            <div class="small text-muted">{{ $company->code }}</div>
                        </td>
                        <td>{{ $company->host() }}</td>
                        <td>{{ $company->status->label() }}</td>
                        <td>{{ $company->subscription?->status->label() ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">{{ __('Belum ada company.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
