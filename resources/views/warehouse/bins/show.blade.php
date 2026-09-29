@extends('layouts.app')

@section('title', __('Isi bin').' '.($pendek[$bin->id] ?? $bin->code))

@section('content')
    @php
        $pengguna = auth()->user();
        $bolehKartu = $pengguna?->hasPermission('stock.view');
    @endphp
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <div class="text-muted small">{{ __('Isi bin') }} · {{ $bin->warehouse?->code }} — {{ $bin->warehouse?->name }}</div>
            <h1 class="display-5 fw-bold font-monospace mb-0" data-kode-pendek>{{ $pendek[$bin->id] ?? $bin->code }}</h1>
            <div class="text-muted font-monospace">{{ $bin->code }}</div>
            <div class="mt-2 d-flex flex-wrap gap-1">
                <span class="badge text-bg-light border">{{ $bin->bin_type->label() }}</span>
                @if ($bin->bin_status->value !== 'active')
                    <span class="badge text-bg-secondary">{{ $bin->bin_status->label() }}</span>
                @endif
                @foreach ($khusus as $k)
                    <span class="badge text-bg-warning" title="{{ $k->name }}">{{ __('Khusus') }} {{ $k->code }}</span>
                @endforeach
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @if ($pengguna?->hasPermission('label.print'))
                <a class="btn btn-outline-secondary" href="{{ route('labels.index', ['type' => 'label_bin', 'q' => $utama->code]) }}"><i class="bi bi-printer"></i> {{ __('Cetak label bin') }}</a>
            @endif
            @if ($pengguna?->hasPermission('warehouse.view'))
                <a class="btn btn-outline-secondary" href="{{ route('warehouses.layout', $bin->warehouse_id) }}"><i class="bi bi-grid-3x3"></i> {{ __('Denah') }}</a>
            @endif
        </div>
    </div>

    @if ($utama->id !== $bin->id)
        <div class="alert alert-info">
            {{ __('Bin ini digabung ke bin utama') }}
            <a class="fw-semibold font-monospace" href="{{ route('bins.show', $utama->code) }}">{{ $pendek[$utama->id] ?? $utama->code }}</a>;
            {{ __('stoknya dicatat di bin utama. Isi di bawah adalah isi bin utama.') }}
        </div>
    @elseif ($tergabung->isNotEmpty())
        <div class="alert alert-light border">
            {{ __('Bin gabungan') }} ({{ $utama->merge_direction?->label() }}, {{ $utama->merge_type?->label() }}):
            @foreach ($tergabung as $t)
                <a class="font-monospace" href="{{ route('bins.show', $t->code) }}">{{ $pendek[$t->id] ?? $t->code }}</a>@if (! $loop->last), @endif
            @endforeach
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between">
            <strong>{{ __('Isi') }}</strong>
            <span class="small text-muted">
                {{ __('Total') }} {{ \App\Domain\Master\Support\QtyFormat::number($total) }}
                @if ($kapasitas !== null) / {{ __('kapasitas') }} {{ \App\Domain\Master\Support\QtyFormat::number($kapasitas) }} @endif
            </span>
        </div>
        <div class="list-group list-group-flush">
            @forelse ($baris as $b)
                <div class="list-group-item">
                    <div class="d-flex justify-content-between gap-2">
                        <div>
                            @if ($bolehKartu)
                                <a class="fw-semibold" href="{{ route('stock.card', $b['item_id']) }}">{{ $b['kode'] }}</a>
                            @else
                                <span class="fw-semibold">{{ $b['kode'] }}</span>
                            @endif
                            <span class="text-muted">{{ $b['nama'] }}</span>
                            @if ($b['lacak']) <div class="small">{{ $b['lacak'] }}</div> @endif
                            <div class="small text-muted">
                                @if ($b['masuk']) {{ __('Masuk terakhir') }} {{ $b['masuk'] }} @endif
                                @unless ($b['tersedia']) · <span class="text-danger">{{ $b['kondisi'] }}</span> @endunless
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="fw-semibold">{{ $b['qty'] }}</div>
                            @if ($b['kemasan']) <div class="small text-muted">{{ $b['kemasan'] }}</div> @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="list-group-item text-center text-muted py-4">{{ __('Bin ini kosong.') }}</div>
            @endforelse
        </div>
    </div>

    @if ($tempat->isNotEmpty())
        <div class="card mb-3">
            <div class="card-header"><strong>{{ __('Tempat simpan barang') }}</strong></div>
            <ul class="list-group list-group-flush">
                @foreach ($tempat as $t)
                    <li class="list-group-item d-flex justify-content-between">
                        <span><span class="fw-semibold">{{ $t['kode'] }}</span> <span class="text-muted">{{ $t['nama'] }}</span></span>
                        <span class="small">
                            @if ($t['seluruh_rak']) <span class="text-muted">{{ __('seluruh rak') }}</span> @endif
                            @if ($t['khusus']) <span class="badge text-bg-warning">{{ __('Khusus') }}</span> @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($label->isNotEmpty())
        <div class="card mb-3">
            <div class="card-header"><strong>{{ __('Label kemasan di bin ini') }}</strong> <span class="text-muted small">({{ $label->count() }})</span></div>
            <div class="card-body small">
                @foreach ($label->groupBy(fn ($l) => $l->item?->code) as $kode => $grup)
                    <div class="mb-1"><span class="fw-semibold">{{ $kode }}</span>:
                        <span class="font-monospace">{{ $grup->pluck('code')->implode(', ') }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
@endsection
