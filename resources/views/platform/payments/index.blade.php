@extends('layouts.platform')

@section('title', __('Pembayaran'))

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Bukti bayar langganan') }}</h1>
            <p class="text-muted mb-0">{{ __('Periksa bukti transfer, lalu verifikasi untuk memperpanjang masa aktif atau tolak dengan alasan.') }}</p>
        </div>
        <form method="GET" action="{{ route('platform.payments.index') }}">
            <label class="visually-hidden" for="status-bayar">{{ __('Status') }}</label>
            <select class="form-select" id="status-bayar" name="status" onchange="this.form.submit()">
                <option value="">{{ __('Semua') }}</option>
                @foreach ($statuses as $nilai => $label)
                    <option value="{{ $nilai }}" @selected($status === $nilai)>{{ $label }}</option>
                @endforeach
            </select>
        </form>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Company') }}</th>
                        <th scope="col">{{ __('Tagihan') }}</th>
                        <th scope="col" class="text-end">{{ __('Ditransfer (Rp)') }}</th>
                        <th scope="col">{{ __('Bukti') }}</th>
                        <th scope="col">{{ __('Status') }}</th>
                        <th scope="col">{{ __('Tindakan') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($payments as $p)
                        @php($inv = $p->invoice)
                        <tr>
                            <td>
                                @if ($inv?->subscription?->company)
                                    <a href="{{ route('platform.companies.show', $inv->subscription->company->id) }}">{{ $inv->subscription->company->name }}</a>
                                @endif
                                <div class="small text-muted">{{ $p->uploaded_by_name }} · {{ $p->created_at?->lokal()->format('d/m/Y H:i') }}</div>
                            </td>
                            <td>
                                {{ $inv?->number }}
                                <div class="small text-muted">{{ __('Rp') }} {{ number_format((float) $inv?->amount, 0, ',', '.') }} · {{ $inv?->period_start?->format('d/m/Y') }} – {{ $inv?->period_end?->format('d/m/Y') }}</div>
                            </td>
                            <td class="text-end">
                                {{ number_format((float) $p->amount, 0, ',', '.') }}
                                <div class="small text-muted">{{ $p->paid_at?->lokal()->format('d/m/Y') }}</div>
                            </td>
                            <td>
                                @if ($p->proof_path) <a href="{{ route('platform.payments.proof', $p->id) }}" target="_blank" rel="noopener">{{ __('Lihat') }}</a> @endif
                                @if ($p->notes) <div class="small text-muted">{{ $p->notes }}</div> @endif
                            </td>
                            <td>
                                <span class="badge {{ $p->status->badge() }}">{{ $p->status->label() }}</span>
                                @if ($p->verifier) <div class="small text-muted">{{ $p->verifier->name }}</div> @endif
                                @if ($p->reject_reason) <div class="small text-danger">{{ $p->reject_reason }}</div> @endif
                            </td>
                            <td>
                                @if ($p->status === \App\Domain\Platform\Enums\PaymentStatus::Pending)
                                    <form class="mb-1" method="POST" action="{{ route('platform.payments.verify', $p->id) }}">
                                        @csrf
                                        <button class="btn btn-sm btn-success" type="submit">{{ __('Verifikasi') }}</button>
                                    </form>
                                    <form class="d-flex gap-1" method="POST" action="{{ route('platform.payments.reject', $p->id) }}">
                                        @csrf
                                        <input class="form-control form-control-sm" name="reject_reason" maxlength="255" placeholder="{{ __('Alasan tolak') }}" aria-label="{{ __('Alasan tolak') }}" required>
                                        <button class="btn btn-sm btn-outline-danger" type="submit">{{ __('Tolak') }}</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">{{ __('Tidak ada bukti bayar.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($payments->hasPages())
            <div class="card-footer">{{ $payments->links() }}</div>
        @endif
    </div>
@endsection
