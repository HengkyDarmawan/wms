@extends('layouts.app')

@section('title', __('Portal Klien'))

@section('content')
    <div class="mb-4">
        <h1 class="h3 mb-1">{{ __('Portal Klien') }}</h1>
        <p class="text-muted mb-0">{{ $user->name }} &middot; {{ $company?->name }}</p>
    </div>

    <div class="row g-3 mb-4">
        @foreach ([
            ['label' => __('Proyek aktif'), 'nilai' => $proyek->where('status', \App\Domain\Master\Enums\ProjectStatus::Active)->count(), 'ikon' => 'bi-building', 'href' => '#proyek', 'hint' => __('dari :n proyek Anda', ['n' => $proyek->count()])],
            ['label' => __('Permintaan berjalan'), 'nilai' => $angka['req'], 'ikon' => 'bi-clipboard-check', 'href' => route('portal.requests.index'), 'hint' => __('belum selesai/ditutup')],
            ['label' => __('Perlu konfirmasi terima'), 'nilai' => $angka['konfirmasi'], 'ikon' => 'bi-box-seam', 'href' => route('portal.requests.index'), 'hint' => __('bukti terima menunggu tanggapan Anda')],
            ['label' => __('Retur berjalan'), 'nilai' => $angka['retur'], 'ikon' => 'bi-arrow-return-left', 'href' => route('portal.returns.index'), 'hint' => __('belum dipilah')],
        ] as $k)
            <div class="col-6 col-xl-3">
                <a class="card h-100 text-decoration-none border-0 shadow-sm" href="{{ $k['href'] }}">
                    <div class="card-body d-flex align-items-start gap-3">
                        <i class="bi {{ $k['ikon'] }} fs-3 text-primary" aria-hidden="true"></i>
                        <div>
                            <div class="fs-4 fw-semibold">{{ $k['nilai'] }}</div>
                            <div class="fw-semibold">{{ $k['label'] }}</div>
                            <div class="small text-muted">{{ $k['hint'] }}</div>
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    <div class="card mb-4" id="proyek">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>{{ __('Proyek Anda') }}</strong>
            <a class="btn btn-sm btn-primary" href="{{ route('portal.requests.index') }}"><i class="bi bi-list-check"></i> {{ __('Permintaan material') }}</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>{{ __('Kode') }}</th><th>{{ __('Nama') }}</th><th>{{ __('Periode') }}</th><th>{{ __('Status') }}</th></tr></thead>
                <tbody>
                    @forelse ($proyek as $p)
                        <tr>
                            <td class="text-nowrap">{{ $p->code }}</td>
                            <td>{{ $p->name }}<div class="small text-muted">{{ $p->address }}</div></td>
                            <td class="small text-nowrap">{{ $p->start_date?->format('d/m/Y') ?? '—' }} &rarr; {{ $p->target_end_date?->format('d/m/Y') ?? '—' }}</td>
                            <td><span class="badge {{ $p->status === \App\Domain\Master\Enums\ProjectStatus::Active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $p->status->label() }}</span></td>
                        </tr>
                    @empty
                        <tr><td class="text-center text-muted py-4" colspan="4">{{ __('Belum ada proyek yang terhubung ke akun Anda.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- BR-PRJ-05: Stok On-site per proyek aktif — Di Gudang Site & Terkirim ke Klien (sumber sama dengan form retur & hub proyek). --}}
    @foreach ($stokProyek as $proyekId => $grup)
        @php($p = $proyek->firstWhere('id', $proyekId))
        <div class="card mb-3">
            <div class="card-header"><strong>{{ __('Stok on-site') }} — {{ $p->code }} {{ $p->name }}</strong></div>
            <div class="card-body pb-0">
                @foreach ([\App\Domain\Return\Enums\ReturnSource::SiteStock, \App\Domain\Return\Enums\ReturnSource::DeliveredToClient] as $sumber)
                    @php($baris = $grup->get($sumber->value, collect()))
                    <h2 class="h6 text-uppercase text-muted mb-2">{{ $sumber->label() }} <span class="fw-normal">({{ $baris->count() }} {{ __('baris') }})</span></h2>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th>{{ __('Item') }}</th><th>{{ __('Lot / serial / potongan') }}</th><th>{{ $sumber === \App\Domain\Return\Enums\ReturnSource::DeliveredToClient ? __('Surat jalan') : __('Bin') }}</th><th class="text-end">{{ __('Jumlah') }}</th></tr></thead>
                            <tbody>
                                @forelse ($baris->take(30) as $c)
                                    <tr>
                                        <td>{{ $c['item_code'] }} <div class="small text-muted">{{ $c['item_name'] }}</div></td>
                                        <td class="small">{{ $c['tracking'] ?: '—' }}</td>
                                        <td class="small">{{ $c['shipment_number'] ?? $c['bin_code'] ?? '—' }}</td>
                                        <td class="text-end">{{ rtrim(rtrim(number_format((float) $c['max'], 4, ',', '.'), '0'), ',') }} <span class="small text-muted">{{ $c['uom'] ?? '' }}</span></td>
                                    </tr>
                                @empty
                                    <tr><td class="text-center text-muted py-3" colspan="4">{{ __('Kosong.') }}</td></tr>
                                @endforelse
                                @if ($baris->count() > 30)
                                    <tr><td class="text-center text-muted small" colspan="4">{{ __('… dan :n baris lagi; ajukan retur untuk melihat semuanya.', ['n' => $baris->count() - 30]) }}</td></tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
@endsection
