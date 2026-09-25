@extends('layouts.app')

@section('title', __('Tagihan langganan'))

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Tagihan langganan') }}</h1>
            <p class="text-muted mb-0">{{ __('Paket bulanan :company. Bayar lewat transfer lalu unggah buktinya; Super Admin memverifikasi dan masa aktif diperpanjang.', ['company' => $company->name]) }}</p>
        </div>
    </div>


    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            {{ $errors->first() }}
            @if (session('ruleCode')) <span class="badge text-bg-dark ms-1">{{ session('ruleCode') }}</span> @endif
        </div>
    @endif

    <div class="row g-3 mb-3">
        @php($tz = $company->timezone ?: 'Asia/Jakarta')
        @foreach ([
            [__('Status'), $subscription?->status->label() ?? '—'],
            [__('Paket'), $subscription?->plan?->name ?? '—'],
            [__('Masa berjalan sampai'), $subscription?->periodEnd()?->format('d/m/Y') ?? '—'],
            [__('Tenggang sampai'), $subscription?->grace_ends_at?->timezone($tz)->format('d/m/Y') ?? '—'],
        ] as [$label, $nilai])
            <div class="col-6 col-md-3">
                <div class="card h-100"><div class="card-body py-2">
                    <div class="small text-muted">{{ $label }}</div>
                    <div class="fs-6 fw-semibold">{{ $nilai }}</div>
                </div></div>
            </div>
        @endforeach
    </div>

    <div class="card">
        <div class="card-header"><strong>{{ __('Tagihan') }}</strong></div>
        <ul class="list-group list-group-flush">
            @forelse ($invoices as $inv)
                <li class="list-group-item">
                    <div class="d-flex flex-wrap justify-content-between gap-2">
                        <div>
                            <strong>{{ $inv->number }}</strong>
                            <span class="badge {{ $inv->status->badge() }}">{{ $inv->status->label() }}</span>
                            <div class="small text-muted">
                                {{ __('Periode') }} {{ $inv->period_start->format('d/m/Y') }} – {{ $inv->period_end->format('d/m/Y') }}
                                · {{ __('Jatuh tempo') }} {{ $inv->due_date->format('d/m/Y') }}
                                · {{ __('Rp') }} {{ number_format((float) $inv->amount, 0, ',', '.') }}
                            </div>
                        </div>
                    </div>

                    @foreach ($inv->payments as $p)
                        <div class="small mt-1">
                            {{ __('Bukti') }} {{ $p->paid_at?->lokal()->format('d/m/Y') }} · {{ __('Rp') }} {{ number_format((float) $p->amount, 0, ',', '.') }}
                            · {{ $p->uploaded_by_name }}
                            <span class="badge {{ $p->status->badge() }}">{{ $p->status->label() }}</span>
                            @if ($p->reject_reason) <span class="text-danger">— {{ $p->reject_reason }}</span> @endif
                            @if ($p->proof_path) <a href="{{ route('billing.payment.proof', $p->id) }}" target="_blank" rel="noopener">{{ __('Lihat bukti') }}</a> @endif
                        </div>
                    @endforeach

                    @can('billing.pay')
                        @if ($inv->status->isPayable() && $inv->payments->where('status', \App\Domain\Platform\Enums\PaymentStatus::Pending)->isEmpty() && $subscription?->status !== \App\Domain\Platform\Enums\SubscriptionStatus::Terminated)
                            <form class="row g-2 align-items-end mt-2" method="POST" action="{{ route('billing.payment.store', $inv->id) }}" enctype="multipart/form-data">
                                @csrf
                                <div class="col-md-3">
                                    <label class="form-label small" for="jumlah-{{ $inv->id }}">{{ __('Jumlah transfer (Rp)') }} <span class="wajib">*</span></label>
                                    <input class="form-control form-control-sm" id="jumlah-{{ $inv->id }}" name="amount" type="number" step="1" min="1" value="{{ old('amount', (int) $inv->amount) }}" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small" for="tgl-{{ $inv->id }}">{{ __('Tanggal transfer') }} <span class="wajib">*</span></label>
                                    <input class="form-control form-control-sm" id="tgl-{{ $inv->id }}" name="paid_at" type="date" value="{{ old('paid_at', now()->toDateString()) }}" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small" for="bukti-{{ $inv->id }}">{{ __('Bukti transfer (foto, maks 5 MB)') }} <span class="wajib">*</span></label>
                                    <input class="form-control form-control-sm" id="bukti-{{ $inv->id }}" name="proof" type="file" accept="image/jpeg,image/png,image/webp" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small" for="ket-{{ $inv->id }}">{{ __('Keterangan') }}</label>
                                    <input class="form-control form-control-sm" id="ket-{{ $inv->id }}" name="notes" type="text" maxlength="255">
                                </div>
                                <div class="col-md-2">
                                    <button class="btn btn-sm btn-primary w-100" type="submit">{{ __('Kirim bukti') }}</button>
                                </div>
                            </form>
                        @endif
                    @endcan
                </li>
            @empty
                <li class="list-group-item text-muted">{{ __('Belum ada tagihan. Tagihan pertama terbit 7 hari sebelum masa berjalan berakhir.') }}</li>
            @endforelse
        </ul>
    </div>
@endsection
