<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ $asal ? __('ADJ pembalik untuk').' '.$asal->number : __('Penyesuaian baru') }}</h1>
            <p class="text-muted mb-0">{{ __('Stok baru bergerak setelah disetujui (minimal satu lapis approval, A-09). Jumlah dalam satuan dasar item.') }}</p>
        </div>
        <a class="btn btn-outline-secondary" href="{{ route('adjustments.index') }}">{{ __('Kembali') }}</a>
    </div>

    @if ($ruleError !== '')
        <div class="alert alert-danger" role="alert">
            {{ $ruleError }}
            @if ($ruleCode !== '') <span class="badge text-bg-dark ms-1">{{ $ruleCode }}</span> @endif
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body row g-3">
            <div class="col-md-4">
                <label class="form-label" for="adj-gudang">{{ __('Gudang') }} <span class="wajib">*</span></label>
                <select class="form-select @error('form.warehouse_id') is-invalid @enderror" id="adj-gudang" wire:model.live="form.warehouse_id" @disabled($asal)>
                    <option value="">{{ __('Pilih gudang…') }}</option>
                    @foreach ($warehouses as $g)
                        <option value="{{ $g->id }}">{{ $g->code }} — {{ $g->name }}</option>
                    @endforeach
                </select>
                @error('form.warehouse_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="adj-alasan">{{ __('Alasan') }} <span class="wajib">*</span></label>
                <select class="form-select @error('form.reason') is-invalid @enderror @error('form.reason_code_id') is-invalid @enderror" id="adj-alasan" wire:model="form.reason">
                    <option value="">{{ __('Pilih alasan…') }}</option>
                    @foreach ($alasan as $kode => $label)
                        <option value="{{ $kode }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('form.reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                @error('form.reason_code_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="adj-ket">{{ __('Keterangan') }}</label>
                <input class="form-control" id="adj-ket" type="text" wire:model="form.notes" placeholder="{{ __('Opsional') }}">
            </div>
        </div>
    </div>

    @if ($asal)
        <div class="card mb-3">
            <div class="card-header"><strong>{{ __('Baris yang dibalik') }}</strong></div>
            <ul class="list-group list-group-flush small">
                @foreach ($asal->lines as $l)
                    <li class="list-group-item">
                        {{ $l->bin?->code }} · {{ $l->item?->code }} {{ $l->trackingLabel() }} ·
                        {{ number_format((float) $l->qty_delta, 2, ',', '.') }} → {{ number_format(-1 * (float) $l->qty_delta, 2, ',', '.') }}
                    </li>
                @endforeach
            </ul>
        </div>
    @else
        <div class="card mb-3">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Arah') }} <span class="wajib">*</span></th>
                            <th scope="col">{{ __('Bin') }} <span class="wajib">*</span></th>
                            <th scope="col">{{ __('Item') }} <span class="wajib">*</span></th>
                            <th scope="col">{{ __('Kondisi') }}</th>
                            <th scope="col">{{ __('Jumlah') }} <span class="wajib">*</span></th>
                            <th scope="col">{{ __('Lot / serial / potongan') }}</th>
                            <th scope="col"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $i => $r)
                            <tr wire:key="adj-baris-{{ $i }}">
                                <td>
                                    <select class="form-select form-select-sm" wire:model.live="rows.{{ $i }}.direction">
                                        <option value="in">{{ __('+ Tambah') }}</option>
                                        <option value="out">{{ __('− Kurangi') }}</option>
                                    </select>
                                </td>
                                <td>
                                    <select class="form-select form-select-sm" wire:model="rows.{{ $i }}.bin_id">
                                        <option value="">{{ __('Pilih bin…') }}</option>
                                        @foreach ($bins as $b)
                                            <option value="{{ $b->id }}">{{ $b->code }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <select class="form-select form-select-sm" wire:model.live="rows.{{ $i }}.item_id">
                                        <option value="">{{ __('Pilih item…') }}</option>
                                        @foreach ($items as $it)
                                            <option value="{{ $it->id }}">{{ $it->code }} — {{ $it->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <select class="form-select form-select-sm" wire:model="rows.{{ $i }}.stock_status">
                                        @foreach ($statuses as $nilai => $label)
                                            <option value="{{ $nilai }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td style="max-width: 8rem">
                                    <input class="form-control form-control-sm" type="number" step="0.0001" min="0" wire:model="rows.{{ $i }}.qty">
                                </td>
                                <td style="min-width: 14rem">
                                    @php $mode = $items->firstWhere('id', (int) ($r['item_id'] ?? 0))?->tracking_mode?->value; @endphp
                                    @if ($mode === 'lot')
                                        <input class="form-control form-control-sm mb-1" type="text" wire:model="rows.{{ $i }}.lot_no" placeholder="{{ __('Nomor lot *') }}">
                                        @if (($r['direction'] ?? 'in') === 'in')
                                            <input class="form-control form-control-sm" type="date" wire:model="rows.{{ $i }}.expiry_date" title="{{ __('Kedaluwarsa (lot baru)') }}">
                                        @endif
                                    @elseif ($mode === 'serial')
                                        <input class="form-control form-control-sm" type="text" wire:model="rows.{{ $i }}.serial_no" placeholder="{{ __('Nomor serial *') }}">
                                    @elseif ($mode === 'piece')
                                        @if (($r['direction'] ?? 'in') === 'out')
                                            <input class="form-control form-control-sm" type="text" wire:model="rows.{{ $i }}.piece_no" placeholder="{{ __('Nomor potongan *') }}">
                                        @else
                                            <input class="form-control form-control-sm" type="number" step="0.0001" min="0" wire:model="rows.{{ $i }}.piece_length" placeholder="{{ __('Panjang potongan *') }}">
                                        @endif
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                                <td><button class="btn btn-sm btn-outline-danger" type="button" wire:click="hapusBaris({{ $i }})" aria-label="{{ __('Hapus baris') }}">×</button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @foreach (['bin_id', 'item_id', 'direction', 'qty', 'lot_no', 'serial_no', 'piece_no', 'piece_length', 'expiry_date'] as $f)
                @error('form.'.$f) <div class="text-danger small px-3">{{ $message }}</div> @enderror
            @endforeach
            <div class="card-footer">
                <button class="btn btn-sm btn-outline-primary" type="button" wire:click="tambahBaris">{{ __('+ Baris') }}</button>
            </div>
        </div>
    @endif

    <button class="btn btn-primary" type="button" wire:click="simpan">{{ $asal ? __('Ajukan ADJ pembalik') : __('Ajukan penyesuaian') }}</button>
</div>
