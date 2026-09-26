<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Bin') }}</h1>
            <p class="text-muted mb-0">
                {{ __('Lokasi terkecil tempat stok disimpan. Bin dibuat dari detail gudang, bukan dari layar ini.') }}
            </p>
        </div>
        @can('label.print')
            <a class="btn btn-outline-secondary"
               href="{{ route('labels.index', ['type' => 'label_bin', 'warehouse' => $warehouseFilter ?: null]) }}">
                <i class="bi bi-upc-scan"></i> {{ __('Cetak label') }}
            </a>
        @endcan
    </div>

    @if ($ruleError !== '')
        <div class="alert alert-danger" role="alert">{{ $ruleError }}</div>
    @endif

    @if ($actingId)
        <div class="card border-warning mb-3">
            <div class="card-header">
                <strong>{{ $aksi === 'bekukan' ? __('Bekukan bin') : __('Nonaktifkan bin') }}</strong>
            </div>
            <div class="card-body row g-3">
                <div class="col-md-5">
                    <label class="form-label" for="bin-alasan">{{ __('Alasan') }} <span class="wajib">*</span></label>
                    <select class="form-select @error('reasonCode') is-invalid @enderror" id="bin-alasan"
                            wire:model="reasonCode">
                        <option value="">{{ __('Pilih alasan…') }}</option>
                        @foreach ($alasan as $kode => $label)
                            <option value="{{ $kode }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('reasonCode') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-7">
                    <label class="form-label" for="bin-keterangan">{{ __('Keterangan') }}</label>
                    <input class="form-control" id="bin-keterangan" type="text" wire:model="reasonNotes"
                           placeholder="{{ __('Opsional') }}">
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <button class="btn btn-warning" type="button" wire:click="jalankanAksi">
                    {{ $aksi === 'bekukan' ? __('Bekukan') : __('Nonaktifkan') }}
                </button>
                <button class="btn btn-outline-secondary" type="button" wire:click="batalAksi">{{ __('Batal') }}</button>
            </div>
        </div>
    @endif

    @if ($ubahId)
        {{-- A-254: ubah kategori & kapasitas bin; kode terkunci (BR-WH-01). --}}
        <div class="card border-primary mb-3">
            <div class="card-header"><strong>{{ __('Ubah bin') }}</strong></div>
            <div class="card-body row g-2">
                <div class="col-md-4">
                    <label class="form-label" for="ubah-kategori">{{ __('Kategori penyimpanan') }}</label>
                    <select class="form-select" id="ubah-kategori" wire:model="formBin.storage_category_id">
                        <option value="">—</option>
                        @foreach ($storageCategories as $k) <option value="{{ $k->id }}">{{ $k->name }}</option> @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="ubah-mode">{{ __('Jika melebihi kapasitas') }}</label>
                    <select class="form-select" id="ubah-mode" wire:model="formBin.capacity_mode">
                        <option value="">{{ __('Ikut kategori penyimpanan') }}</option>
                        <option value="warn">{{ __('Peringatan saja') }}</option>
                        <option value="block">{{ __('Blokir (hitung total isi bin)') }}</option>
                    </select>
                </div>
                @foreach (['capacity_qty' => __('Kapasitas jumlah'), 'capacity_weight' => __('Berat maks. (kg)'), 'capacity_volume' => __('Volume maks. (m³)'), 'capacity_length' => __('Panjang maks.')] as $k => $t)
                    <div class="col-md-3">
                        <label class="form-label" for="ubah-{{ $k }}">{{ $t }}</label>
                        <input class="form-control @error('formBin.'.$k) is-invalid @enderror" id="ubah-{{ $k }}" type="number" step="0.0001" min="0" wire:model="formBin.{{ $k }}" placeholder="{{ __('opsional') }}">
                        @error('formBin.'.$k) <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                @endforeach
            </div>
            <div class="card-footer d-flex gap-2">
                <button class="btn btn-primary" type="button" wire:click="simpanBin">{{ __('Simpan') }}</button>
                <button class="btn btn-outline-secondary" type="button" wire:click="batalUbah">{{ __('Batal') }}</button>
            </div>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body row g-3">
            <div class="col-lg-3">
                <label class="form-label" for="cari-bin">{{ __('Cari kode bin') }}</label>
                <input class="form-control" id="cari-bin" type="search"
                       wire:model.live.debounce.400ms="search" placeholder="CKG-A-R01-L1-B01">
            </div>
            <div class="col-lg-3">
                <label class="form-label" for="filter-gudang-bin">{{ __('Gudang') }}</label>
                <select class="form-select" id="filter-gudang-bin" wire:model.live="warehouseFilter">
                    <option value="">{{ __('Semua gudang') }}</option>
                    @foreach ($warehouses as $gudang)
                        <option value="{{ $gudang->id }}">{{ $gudang->code }} — {{ $gudang->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2">
                <label class="form-label" for="filter-jenis-bin">{{ __('Jenis') }}</label>
                <select class="form-select" id="filter-jenis-bin" wire:model.live="typeFilter">
                    <option value="">{{ __('Semua') }}</option>
                    @foreach ($binTypes as $nilai => $label)
                        <option value="{{ $nilai }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2">
                <label class="form-label" for="filter-status-bin">{{ __('Status') }}</label>
                <select class="form-select" id="filter-status-bin" wire:model.live="statusFilter">
                    <option value="">{{ __('Semua') }}</option>
                    @foreach ($binStatuses as $nilai => $label)
                        <option value="{{ $nilai }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2">
                <label class="form-label" for="filter-penanda-hitung">{{ __('Penanda hitung') }}</label>
                <select class="form-select" id="filter-penanda-hitung" wire:model.live="flagFilter">
                    <option value="">{{ __('Semua') }}</option>
                    <option value="ya">{{ __('Perlu dihitung') }}</option>
                    <option value="tidak">{{ __('Tidak ditandai') }}</option>
                </select>
            </div>
            <div class="col-lg-2">
                <label class="form-label" for="filter-zona-bin">{{ __('Zona') }}</label>
                <input class="form-control" id="filter-zona-bin" type="search" wire:model.live.debounce.400ms="zoneFilter" placeholder="A">
            </div>
            <div class="col-lg-2">
                <label class="form-label" for="filter-rak-bin">{{ __('Rak') }}</label>
                <input class="form-control" id="filter-rak-bin" type="search" wire:model.live.debounce.400ms="rackFilter" placeholder="R01">
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Kode bin') }}</th>
                        <th>{{ __('Gudang') }}</th>
                        <th>{{ __('Zona · Rak · Level') }}</th>
                        <th>{{ __('Jenis') }}</th>
                        <th>{{ __('Kategori penyimpanan') }}</th>
                        <th class="text-end">{{ __('Kapasitas') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-end">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($bins as $bin)
                        <tr wire:key="bin-{{ $bin->id }}">
                            <td>
                                <span class="fw-semibold">{{ $bin->code }}</span>
                                @if ($bin->is_virtual)
                                    <span class="badge text-bg-info">{{ __('Virtual') }}</span>
                                @endif
                                @if ($bin->count_flag)
                                    <span class="badge text-bg-warning">{{ __('Perlu dihitung') }}</span>
                                @endif
                                @if ($bin->project)
                                    <div class="small text-muted">{{ $bin->project->code }}</div>
                                @endif
                            </td>
                            <td>{{ $bin->warehouse?->code ?? '—' }}</td>
                            <td class="small">
                                @if ($bin->rackLevel)
                                    {{ $bin->rackLevel->rack?->zone?->code }} · {{ $bin->rackLevel->rack?->code }} · {{ $bin->rackLevel->code }}
                                    @if ($bin->rackLevel->rack?->is_area) <span class="badge text-bg-secondary">{{ __('area') }}</span> @endif
                                @else
                                    —
                                @endif
                                @if ($bin->occupiedBy) <div class="text-muted">{{ __('ikut terpakai oleh') }} {{ $bin->occupiedBy->code }}</div> @endif
                            </td>
                            <td>{{ $bin->bin_type->label() }}</td>
                            <td>
                                {{ $bin->storageCategory?->name ?? '—' }}
                                @if ($bin->blocksOnOverCapacity())
                                    <span class="badge text-bg-danger">{{ __('Blokir') }}</span>
                                @endif
                            </td>
                            <td class="text-end small">
                                {{ collect(['' => $bin->capacity_qty, ' kg' => $bin->capacity_weight, ' m³' => $bin->capacity_volume, ' ('.__('panjang').')' => $bin->capacity_length])->filter(fn ($v) => $v !== null)->map(fn ($v, $s) => (float) $v.$s)->implode(' · ') ?: '—' }}
                            </td>
                            <td>
                                <span class="badge text-bg-{{ $bin->bin_status->badge() }}">
                                    {{ $bin->bin_status->label() }}
                                </span>
                            </td>
                            <td class="text-end">
                                @can('manage', $bin)
                                    @if ($bin->bin_status->value === 'frozen')
                                        <button class="btn btn-sm btn-outline-success" type="button"
                                                wire:click="cairkan({{ $bin->id }})">{{ __('Cairkan') }}</button>
                                    @elseif ($bin->bin_status->value === 'inactive')
                                        <button class="btn btn-sm btn-outline-success" type="button"
                                                wire:click="aktifkan({{ $bin->id }})">{{ __('Aktifkan') }}</button>
                                    @else
                                        <button class="btn btn-sm btn-outline-warning" type="button"
                                                wire:click="minta({{ $bin->id }}, 'bekukan')">{{ __('Bekukan') }}</button>
                                        @unless ($bin->isSystemBin())
                                            <button class="btn btn-sm btn-outline-danger" type="button"
                                                    wire:click="minta({{ $bin->id }}, 'nonaktifkan')">
                                                {{ __('Nonaktifkan') }}
                                            </button>
                                        @endunless
                                    @endif

                                    @unless ($bin->is_virtual)
                                        <button class="btn btn-sm btn-outline-primary" type="button" wire:click="mintaUbah({{ $bin->id }})">{{ __('Ubah') }}</button>
                                    @endunless
                                    <button class="btn btn-sm btn-outline-secondary" type="button"
                                            wire:click="ubahPenandaHitung({{ $bin->id }}, {{ $bin->count_flag ? 'false' : 'true' }})">
                                        {{ $bin->count_flag ? __('Lepas penanda') : __('Tandai hitung') }}
                                    </button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">{{ __('Belum ada bin yang cocok.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($bins->hasPages())
            <div class="card-footer">{{ $bins->links() }}</div>
        @endif
    </div>
</div>
