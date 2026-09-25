@extends('layouts.platform')

@section('title', __('Paket'))

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Paket langganan') }}</h1>
            <p class="text-muted mb-0">{{ __('Paket bulanan flat per company. Harga baru berlaku untuk tagihan berikutnya.') }}</p>
        </div>
        <a class="btn btn-primary" href="{{ route('platform.plans.create') }}">{{ __('Paket baru') }}</a>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Paket') }}</th>
                        <th scope="col" class="text-end">{{ __('Harga bulanan (Rp)') }}</th>
                        <th scope="col" class="text-end">{{ __('Trial (hari)') }}</th>
                        <th scope="col" class="text-end">{{ __('Kuota WA') }}</th>
                        <th scope="col" class="text-end">{{ __('Kuota berkas (MB)') }}</th>
                        <th scope="col" class="text-end">{{ __('Company') }}</th>
                        <th scope="col">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($plans as $p)
                        <tr>
                            <td><a href="{{ route('platform.plans.edit', $p->id) }}">{{ $p->name }}</a> <div class="small text-muted">{{ $p->code }}</div></td>
                            <td class="text-end">{{ number_format((float) $p->monthly_price, 0, ',', '.') }}</td>
                            <td class="text-end">{{ $p->trial_days }}</td>
                            <td class="text-end">{{ $p->wa_quota ?? '—' }}</td>
                            <td class="text-end">{{ $p->storage_quota_mb ?? '—' }}</td>
                            <td class="text-end">{{ $p->companies_count }}</td>
                            <td>{{ $p->is_active ? __('Aktif') : __('Nonaktif') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">{{ __('Belum ada paket.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
