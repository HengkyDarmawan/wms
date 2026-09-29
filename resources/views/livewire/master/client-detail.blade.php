<div>
    {{-- Halaman detail klien (A-327): kepala + tab Proyek · PIC · Akun portal. --}}
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ $client->name }}</h1>
            <p class="text-muted mb-0">
                {{ $client->code }}
                @if ($client->tax_id)
                    · {{ __('NPWP') }} {{ $client->tax_id }}
                @endif
                · <span class="badge text-bg-{{ $client->is_active ? 'success' : 'secondary' }}">
                    {{ $client->is_active ? __('Aktif') : __('Nonaktif') }}
                </span>
            </p>
            @if ($client->address)
                <p class="small text-muted mb-0">{{ $client->address }}</p>
            @endif
        </div>

        <div class="d-flex flex-wrap gap-2">
            @can('shipment.view')
                <a class="btn btn-outline-primary"
                   href="{{ route('reports.show', ['report' => 'rekap-pengiriman-klien', 'filters' => ['client_id' => $client->id]]) }}">
                    <i class="bi bi-list-columns"></i> {{ __('Rekap pengiriman') }}
                </a>
            @endcan
            @can('update', $client)
                <a class="btn btn-primary" href="{{ route('clients.index', ['ubah' => $client->id]) }}">
                    <i class="bi bi-pencil"></i> {{ __('Ubah') }}
                </a>
            @endcan
            <a class="btn btn-outline-secondary" href="{{ route('clients.index') }}">{{ __('Kembali') }}</a>
        </div>
    </div>

    @if ($ruleError !== '')
        <div class="alert alert-danger" role="alert">{{ $ruleError }}</div>
    @endif

    <div class="row g-3 mb-3">
        @foreach ([
            'proyek' => [__('Proyek aktif'), $ringkas['proyek'], 'bi-signpost-split'],
            'pic' => [__('PIC aktif'), $ringkas['pic'], 'bi-person-lines-fill'],
            'akun' => [__('Akun portal aktif'), $ringkas['akun'], 'bi-box-arrow-in-right'],
        ] as $kunci => $kartu)
            <div class="col-6 col-lg-4">
                <button class="card w-100 h-100 text-start border-0 shadow-sm" type="button"
                        wire:click="pilihTab('{{ $kunci }}')">
                    <div class="card-body">
                        <div class="small text-muted"><i class="bi {{ $kartu[2] }}"></i> {{ $kartu[0] }}</div>
                        <div class="h4 mb-0">{{ $kartu[1] }}</div>
                    </div>
                </button>
            </div>
        @endforeach
    </div>

    <ul class="nav nav-tabs mb-3">
        @foreach ($tabs as $kunci => $label)
            <li class="nav-item">
                <button class="nav-link {{ $tab === $kunci ? 'active' : '' }}" type="button"
                        wire:click="pilihTab('{{ $kunci }}')">{{ $label }}</button>
            </li>
        @endforeach
    </ul>

    @if ($tab === 'proyek')
        @include('livewire.master.partials.client-tab-proyek')
    @elseif ($tab === 'pic')
        @include('livewire.master.partials.client-tab-pic')
    @else
        @include('livewire.master.partials.client-tab-akun')
    @endif
</div>
