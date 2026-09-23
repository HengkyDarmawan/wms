<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Selisih pengiriman') }}</h1>
            <p class="text-muted mb-0">
                {{ __('Barang yang kurang atau rusak tetap tercatat milik gudang asal sampai selisihnya diselesaikan.') }}
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

    @if ($dipilih)
        <div class="card border-warning mb-3">
            <div class="card-header">
                <strong>{{ __('Selesaikan') }} {{ $dipilih->number }}</strong>
                <span class="text-muted small ms-2">{{ $dipilih->shipment?->number }}</span>
            </div>
            <div class="card-body">
                <p class="text-muted small">
                    {{ __('Disposisi menentukan ke mana barangnya pergi; keputusan klien menentukan apakah permintaannya masih perlu dipenuhi.') }}
                </p>

                @foreach ($dipilih->lines as $l)
                    <div class="border rounded p-3 mb-2" wire:key="dsc-baris-{{ $l->id }}">
                        <div class="mb-2">
                            <strong>{{ $l->shipmentLine?->pickTaskLine?->item?->code ?? '—' }}</strong>
                            <span class="badge {{ $l->isDamaged() ? 'text-bg-danger' : 'text-bg-secondary' }}">
                                {{ $l->discrepancy_type->label() }}
                            </span>
                            <span class="text-muted">
                                {{ number_format((float) $l->qty_base, 2, ',', '.') }}
                            </span>
                        </div>
                        <div class="row g-2">
                            <div class="col-md-3">
                                <label class="form-label small" for="disp-{{ $l->id }}">
                                    {{ __('Disposisi') }} <span class="wajib">*</span>
                                </label>
                                <select class="form-select form-select-sm" id="disp-{{ $l->id }}"
                                        wire:model.live="keputusan.{{ $l->id }}.disposition">
                                    <option value="">{{ __('Pilih…') }}</option>
                                    @foreach ($dispositions as $nilai => $label)
                                        <option value="{{ $nilai }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small" for="dec-{{ $l->id }}">{{ __('Keputusan klien') }}</label>
                                <select class="form-select form-select-sm" id="dec-{{ $l->id }}"
                                        wire:model="keputusan.{{ $l->id }}.client_decision">
                                    @foreach ($decisions as $nilai => $label)
                                        <option value="{{ $nilai }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small" for="rsn-{{ $l->id }}">
                                    {{ __('Alasan') }}
                                    @if (($keputusan[$l->id]['disposition'] ?? '') === 'adjusted')
                                        <span class="wajib">*</span>
                                    @endif
                                </label>
                                <select class="form-select form-select-sm" id="rsn-{{ $l->id }}"
                                        wire:model="keputusan.{{ $l->id }}.reason_code">
                                    <option value="">{{ __('—') }}</option>
                                    @foreach ($alasan as $kode => $label)
                                        <option value="{{ $kode }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small" for="clm-{{ $l->id }}">
                                    {{ __('Nomor klaim') }}
                                    @if (($keputusan[$l->id]['disposition'] ?? '') === 'claimed')
                                        <span class="wajib">*</span>
                                    @endif
                                </label>
                                <input class="form-control form-control-sm" id="clm-{{ $l->id }}" type="text"
                                       wire:model="keputusan.{{ $l->id }}.claim_ref">
                            </div>
                        </div>
                    </div>
                @endforeach

                <div class="mt-3">
                    <label class="form-label" for="dsc-catatan">{{ __('Catatan') }}</label>
                    <input class="form-control" id="dsc-catatan" type="text" wire:model="catatan"
                           placeholder="{{ __('Opsional') }}">
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <button class="btn btn-warning" type="button" wire:click="selesaikan">{{ __('Selesaikan') }}</button>
                <button class="btn btn-outline-secondary" type="button" wire:click="tutupDialog">{{ __('Tutup') }}</button>
            </div>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body row g-3 align-items-end">
            <div class="col-lg-5">
                <label class="form-label" for="cari-dsc">{{ __('Cari') }}</label>
                <input class="form-control" id="cari-dsc" type="search"
                       wire:model.live.debounce.400ms="search" placeholder="{{ __('Nomor DSC atau SJ') }}">
            </div>
            <div class="col-lg-4">
                <label class="form-label" for="filter-status-dsc">{{ __('Status') }}</label>
                <select class="form-select" id="filter-status-dsc" wire:model.live="statusFilter">
                    <option value="">{{ __('Semua') }}</option>
                    @foreach ($statuses as $nilai => $label)
                        <option value="{{ $nilai }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-3">
                <div class="form-check">
                    <input class="form-check-input" id="filter-menggantung-dsc" type="checkbox"
                           wire:model.live="hanyaMenggantung">
                    <label class="form-check-label" for="filter-menggantung-dsc">
                        {{ __('Menggantung lebih dari :n hari', ['n' => $ambang]) }}
                    </label>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Nomor') }}</th>
                        <th scope="col">{{ __('Surat jalan') }}</th>
                        <th scope="col">{{ __('Asal selisih') }}</th>
                        <th class="text-end" scope="col">{{ __('Baris') }}</th>
                        <th scope="col">{{ __('Umur') }}</th>
                        <th scope="col">{{ __('Status') }}</th>
                        <th class="text-end" scope="col">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $dsc)
                        @php $menggantung = $dsc->status->value === 'open' && $dsc->ageInDays() >= $ambang; @endphp
                        <tr @class(['table-warning' => $menggantung])>
                            <td>{{ $dsc->number }}</td>
                            <td>
                                @if ($dsc->shipment)
                                    <a href="{{ route('shipments.show', $dsc->shipment) }}">{{ $dsc->shipment->number }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="small">{{ $dsc->origin->label() }}</td>
                            <td class="text-end">{{ $dsc->lines_count }}</td>
                            <td>
                                {{ $dsc->ageInDays() }} {{ __('hari') }}
                                @if ($menggantung)
                                    <span class="badge text-bg-warning">{{ __('Menggantung') }}</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $dsc->status->badge() }}">{{ $dsc->status->label() }}</span>
                                @if ($dsc->resolver)
                                    <div class="small text-muted">{{ $dsc->resolver->name }}</div>
                                @endif
                            </td>
                            <td class="text-end">
                                @can('resolve', $dsc)
                                    <button class="btn btn-sm btn-outline-warning" type="button"
                                            wire:click="mintaSelesaikan({{ $dsc->id }})">
                                        {{ __('Selesaikan') }}
                                    </button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-muted py-4" colspan="7">
                                {{ __('Tidak ada selisih yang cocok dengan penyaring ini.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($items->hasPages())
            <div class="card-footer">{{ $items->links() }}</div>
        @endif
    </div>
</div>
