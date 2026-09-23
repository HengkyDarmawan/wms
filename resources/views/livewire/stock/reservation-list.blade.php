<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Reservasi') }}</h1>
            <p class="text-muted mb-0">
                {{ __('Jumlah yang dijanjikan untuk sebuah dokumen. Reservasi lunak yang melewati :hari hari ditandai menggantung.', ['hari' => $ambang]) }}
            </p>
        </div>
    </div>

    @if ($ruleError !== '')
        <div class="alert alert-danger" role="alert">
            {{ $ruleError }}
            @if ($ruleCode !== '')
                <span class="badge text-bg-dark ms-1">{{ $ruleCode }}</span>
            @endif
        </div>
    @endif

    @if ($actingId)
        <div class="card border-warning mb-3">
            <div class="card-header"><strong>{{ __('Lepas reservasi') }}</strong></div>
            <div class="card-body row g-3">
                <div class="col-md-5">
                    <label class="form-label" for="lepas-alasan">{{ __('Alasan') }} <span class="wajib">*</span></label>
                    <select class="form-select @error('reasonCode') is-invalid @enderror" id="lepas-alasan"
                            wire:model="reasonCode">
                        <option value="">{{ __('Pilih alasan…') }}</option>
                        @foreach ($alasan as $kode => $label)
                            <option value="{{ $kode }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('reasonCode') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-7">
                    <label class="form-label" for="lepas-keterangan">{{ __('Keterangan') }}</label>
                    <input class="form-control" id="lepas-keterangan" type="text" wire:model="reasonNotes"
                           placeholder="{{ __('Opsional') }}">
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <button class="btn btn-warning" type="button" wire:click="lepas">{{ __('Lepas') }}</button>
                <button class="btn btn-outline-secondary" type="button" wire:click="batalLepas">{{ __('Batal') }}</button>
            </div>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body row g-3 align-items-end">
            <div class="col-lg-3">
                <label class="form-label" for="cari-reservasi">{{ __('Cari item') }}</label>
                <input class="form-control" id="cari-reservasi" type="search"
                       wire:model.live.debounce.400ms="search" placeholder="{{ __('Kode atau nama item') }}">
            </div>
            <div class="col-lg-3">
                <label class="form-label" for="filter-gudang-reservasi">{{ __('Gudang') }}</label>
                <select class="form-select" id="filter-gudang-reservasi" wire:model.live="warehouseFilter">
                    <option value="">{{ __('Semua gudang') }}</option>
                    @foreach ($warehouses as $gudang)
                        <option value="{{ $gudang->id }}">{{ $gudang->code }} — {{ $gudang->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2">
                <label class="form-label" for="filter-level">{{ __('Tingkat') }}</label>
                <select class="form-select" id="filter-level" wire:model.live="levelFilter">
                    <option value="">{{ __('Semua') }}</option>
                    @foreach ($levels as $nilai => $label)
                        <option value="{{ $nilai }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2">
                <label class="form-label" for="filter-status-reservasi">{{ __('Status') }}</label>
                <select class="form-select" id="filter-status-reservasi" wire:model.live="statusFilter">
                    <option value="">{{ __('Semua') }}</option>
                    @foreach ($statuses as $nilai => $label)
                        <option value="{{ $nilai }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2">
                <div class="form-check">
                    <input class="form-check-input" id="filter-menggantung" type="checkbox"
                           wire:model.live="hanyaMenggantung">
                    <label class="form-check-label" for="filter-menggantung">{{ __('Hanya menggantung') }}</label>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Item') }}</th>
                        <th scope="col">{{ __('Gudang / Bin') }}</th>
                        <th class="text-end" scope="col">{{ __('Jumlah') }}</th>
                        <th scope="col">{{ __('Tingkat') }}</th>
                        <th scope="col">{{ __('Dokumen') }}</th>
                        <th scope="col">{{ __('Umur') }}</th>
                        <th scope="col">{{ __('Status') }}</th>
                        <th class="text-end" scope="col">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reservasi as $r)
                        @php $menggantung = $r->status->value === 'active' && $r->level->value === 'soft' && $r->ageInDays() >= $ambang; @endphp
                        <tr @class(['table-warning' => $menggantung])>
                            <td>
                                <a href="{{ route('stock.card', $r->item_id) }}">{{ $r->item?->code }}</a>
                                <div class="small text-muted">{{ $r->item?->name }}</div>
                            </td>
                            <td>
                                {{ $r->warehouse?->code ?? '—' }}
                                <div class="small text-muted">{{ $r->bin?->code ?? __('belum ditunjuk') }}</div>
                            </td>
                            <td class="text-end">{{ number_format((float) $r->qty_base, 2, ',', '.') }}</td>
                            <td>
                                <span class="badge {{ $r->level->value === 'hard' ? 'text-bg-primary' : 'text-bg-secondary' }}">
                                    {{ $r->level->label() }}
                                </span>
                            </td>
                            <td class="small">
                                {{ $r->document_type }}
                                <div class="text-muted">#{{ $r->document_id }}</div>
                            </td>
                            <td>
                                {{ $r->ageInDays() }} {{ __('hari') }}
                                @if ($menggantung)
                                    <span class="badge text-bg-warning">{{ __('Menggantung') }}</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge text-bg-light">{{ $r->status->label() }}</span>
                                @if ($r->released_reason)
                                    <div class="small text-muted">{{ $r->released_reason }}</div>
                                @endif
                            </td>
                            <td class="text-end">
                                @can('release', $r)
                                    @if ($r->status->value === 'active')
                                        <button class="btn btn-sm btn-outline-warning" type="button"
                                                wire:click="mintaLepas({{ $r->id }})">
                                            {{ __('Lepas') }}
                                        </button>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-muted py-4" colspan="8">
                                {{ __('Tidak ada reservasi yang cocok dengan penyaring ini.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($reservasi->hasPages())
            <div class="card-footer">{{ $reservasi->links() }}</div>
        @endif
    </div>
</div>
