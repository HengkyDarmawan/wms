<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Cetak label') }}</h1>
            <p class="text-muted mb-0">
                {{ __('Label berisi kode, barcode Code128, dan QR untuk ditempel di bin atau barang.') }}
            </p>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label" for="lbl-jenis">{{ __('Jenis label') }} <span class="wajib">*</span></label>
                <select class="form-select" id="lbl-jenis" wire:model.live="type">
                    @foreach ($jenisLabel as $j)
                        <option value="{{ $j->value }}">{{ $j->label() }}</option>
                    @endforeach
                </select>
            </div>
            @if ($type === 'label_bin')
                <div class="col-md-3">
                    <label class="form-label" for="lbl-gudang">{{ __('Gudang') }}</label>
                    <select class="form-select" id="lbl-gudang" wire:model.live="warehouseFilter">
                        <option value="">{{ __('Semua gudang') }}</option>
                        @foreach ($gudang as $g)
                            <option value="{{ $g->id }}">{{ $g->code }} — {{ $g->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="col-md">
                <label class="form-label" for="lbl-cari">{{ __('Cari') }}</label>
                <input class="form-control" id="lbl-cari" type="search" wire:model.live.debounce.400ms="search"
                       placeholder="{{ __('Kode, nama, atau nomor') }}">
            </div>
        </div>
    </div>

    @if (! $boleh)
        <div class="alert alert-warning" role="alert">
            {{ __('Anda tidak punya izin melihat data untuk jenis label ini.') }}
        </div>
    @else
        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span>
                    <strong>{{ count($selected) }}</strong> {{ __('dipilih') }}
                    @if (count($selected) > 0)
                        <button class="btn btn-link btn-sm p-0 ms-2" type="button" wire:click="clearSelection">{{ __('Kosongkan') }}</button>
                    @endif
                </span>
                <button class="btn btn-sm btn-outline-secondary" type="button"
                        wire:click="selectPage({{ json_encode($rows->pluck('id')->all()) }})">
                    {{ __('Pilih semua di halaman ini') }}
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th style="width: 2.5rem;"></th>
                            <th>{{ __('Kode') }}</th>
                            <th>{{ __('Keterangan') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr wire:key="lbl-{{ $type }}-{{ $row->id }}">
                                <td>
                                    <input class="form-check-input" type="checkbox" value="{{ $row->id }}"
                                           id="lbl-{{ $row->id }}" wire:model.live="selected">
                                </td>
                                @switch($type)
                                    @case('label_bin')
                                        <td><label for="lbl-{{ $row->id }}" class="font-monospace">{{ $row->code }}</label></td>
                                        <td>{{ $row->warehouse?->code }} · {{ $row->bin_type?->label() }}</td>
                                        @break
                                    @case('label_item')
                                        <td><label for="lbl-{{ $row->id }}" class="font-monospace">{{ $row->code }}</label></td>
                                        <td>{{ $row->name }} <span class="text-muted">{{ $row->barcode }}</span></td>
                                        @break
                                    @case('label_lot')
                                        <td><label for="lbl-{{ $row->id }}" class="font-monospace">{{ $row->lot_no }}</label></td>
                                        <td>{{ $row->item?->code }} {{ $row->item?->name }}
                                            @if ($row->expiry_date) · {{ __('kedaluwarsa') }} {{ $row->expiry_date->format('d/m/Y') }} @endif</td>
                                        @break
                                    @default
                                        <td><label for="lbl-{{ $row->id }}" class="font-monospace">{{ $row->piece_no }}</label></td>
                                        <td>{{ $row->item?->code }} {{ $row->item?->name }} · {{ \App\Domain\Template\Support\PrintFormat::qty($row->length) }}</td>
                                @endswitch
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted py-4">{{ __('Tidak ada data.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer">{{ $rows->links() }}</div>
        </div>

        <div class="card mt-3">
            <div class="card-body row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label" for="lbl-kertas">{{ __('Kertas') }} <span class="wajib">*</span></label>
                    <select class="form-select" id="lbl-kertas" wire:model.live="paper">
                        @foreach ($kertas as $k)
                            <option value="{{ $k->value }}">{{ $k->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="lbl-salinan">{{ __('Salinan') }} <span class="wajib">*</span></label>
                    <input class="form-control" id="lbl-salinan" type="number" min="1" max="10" wire:model.live="copies">
                </div>
                <div class="col-md">
                    @if ($jumlahLabel > $maks)
                        <div class="text-danger small mb-2">{{ __('Paling banyak :maks label per cetak.', ['maks' => $maks]) }}</div>
                    @endif
                    <a class="btn btn-primary {{ $jumlahLabel === 0 || $jumlahLabel > $maks ? 'disabled' : '' }}"
                       href="{{ $printUrl }}" target="_blank" rel="noopener"
                       @if ($jumlahLabel === 0 || $jumlahLabel > $maks) aria-disabled="true" tabindex="-1" @endif>
                        <i class="bi bi-printer"></i> {{ __('Cetak :n label', ['n' => $jumlahLabel]) }}
                    </a>
                </div>
            </div>
        </div>
    @endif
</div>
