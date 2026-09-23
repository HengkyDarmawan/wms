<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ $laporan->title() }}</h1>
            <p class="text-muted mb-0">{{ $laporan->description() }}</p>
        </div>

        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="{{ route('reports.index') }}">{{ __('Semua laporan') }}</a>
            <a class="btn btn-primary"
               href="{{ route('reports.export', ['report' => $laporan->key(), 'filters' => $filters]) }}">
                <i class="bi bi-download"></i> {{ __('Ekspor Excel') }}
            </a>
        </div>
    </div>

    @if ($penyaring !== [])
        <div class="card mb-3">
            <div class="card-body row g-3">
                @foreach ($penyaring as $kunci => $definisi)
                    <div class="col-md-3">
                        <label class="form-label" for="filter-{{ $kunci }}">{{ $definisi['label'] }}</label>
                        @if (isset($definisi['options']))
                            <select class="form-select" id="filter-{{ $kunci }}"
                                    wire:model.live="filters.{{ $kunci }}">
                                <option value="">{{ __('Semua') }}</option>
                                @foreach ($definisi['options'] as $nilai => $label)
                                    <option value="{{ $nilai }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        @else
                            <input class="form-control" id="filter-{{ $kunci }}" type="search"
                                   wire:model.live.debounce.400ms="filters.{{ $kunci }}">
                        @endif
                    </div>
                @endforeach

                <div class="col-md-3 d-flex align-items-end">
                    <button class="btn btn-outline-secondary" type="button" wire:click="bersihkanFilter">
                        {{ __('Bersihkan penyaring') }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="small text-muted">
                {{ __(':jumlah baris', ['jumlah' => number_format($jumlahTotal, 0, ',', '.')]) }}
            </span>
            @if ($jumlahTotal > $baris->count())
                <span class="small text-muted">
                    {{ __('Menampilkan :n pertama. Ekspor Excel memuat seluruh baris.', ['n' => $baris->count()]) }}
                </span>
            @endif
        </div>

        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        @foreach ($kolom as $judul)
                            <th>{{ $judul }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($baris as $isi)
                        <tr>
                            @foreach (array_keys($kolom) as $kunci)
                                <td>{{ $isi[$kunci] ?? '' }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($kolom) }}" class="text-center text-muted py-4">
                                {{ __('Tidak ada baris yang cocok.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
