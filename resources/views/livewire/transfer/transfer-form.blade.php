<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Transfer baru') }}</h1>
            <p class="text-muted mb-0">{{ __('Stok gudang asal dicadangkan saat disetujui; tugas picking dibuat otomatis di gudang asal. Jumlah dalam satuan dasar item.') }}</p>
        </div>
        <a class="btn btn-outline-secondary" href="{{ route('transfers.index') }}">{{ __('Kembali') }}</a>
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
                <label class="form-label" for="trf-asal">{{ __('Gudang asal') }} <span class="wajib">*</span></label>
                <select class="form-select @error('form.from_warehouse_id') is-invalid @enderror" id="trf-asal" wire:model.live="form.from_warehouse_id">
                    <option value="">{{ __('Pilih gudang…') }}</option>
                    @foreach ($warehouses as $g)
                        <option value="{{ $g->id }}">{{ $g->code }} — {{ $g->name }}@if ($g->project) ({{ $g->project->code }})@endif</option>
                    @endforeach
                </select>
                @error('form.from_warehouse_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="trf-tujuan">{{ __('Gudang tujuan') }} <span class="wajib">*</span></label>
                <select class="form-select @error('form.to_warehouse_id') is-invalid @enderror" id="trf-tujuan" wire:model.live="form.to_warehouse_id">
                    <option value="">{{ __('Pilih gudang…') }}</option>
                    @foreach ($warehouses as $g)
                        <option value="{{ $g->id }}">{{ $g->code }} — {{ $g->name }}@if ($g->project) ({{ $g->project->code }})@endif</option>
                    @endforeach
                </select>
                @error('form.to_warehouse_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="trf-ket">{{ __('Keterangan') }}</label>
                <input class="form-control" id="trf-ket" type="text" wire:model="form.notes" placeholder="{{ __('Opsional') }}">
            </div>
            @if ($jenis)
                <div class="col-12 small text-muted">{{ __('Jenis transfer') }}: <strong>{{ $jenis->label() }}</strong></div>
            @endif
        </div>
    </div>

    <div class="card mb-3">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Item') }} <span class="wajib">*</span></th>
                        <th scope="col">{{ __('Jumlah') }} <span class="wajib">*</span></th>
                        <th class="text-end" scope="col">{{ __('Tersedia di asal') }}</th>
                        <th scope="col">{{ __('Keterangan') }}</th>
                        <th scope="col"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $i => $r)
                        <tr wire:key="trf-baris-{{ $i }}">
                            <td style="min-width: 16rem">
                                <select class="form-select form-select-sm" wire:model.live="rows.{{ $i }}.item_id">
                                    <option value="">{{ __('Pilih item…') }}</option>
                                    @foreach ($items as $it)
                                        <option value="{{ $it->id }}">{{ $it->code }} — {{ $it->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td style="max-width: 9rem">
                                <input class="form-control form-control-sm" type="number" step="0.0001" min="0" wire:model="rows.{{ $i }}.qty_base">
                            </td>
                            <td class="text-end small">{{ isset($tersedia[$i]) ? number_format($tersedia[$i], 2, ',', '.') : '—' }}</td>
                            <td><input class="form-control form-control-sm" type="text" wire:model="rows.{{ $i }}.notes" placeholder="{{ __('Opsional') }}"></td>
                            <td><button class="btn btn-sm btn-outline-danger" type="button" wire:click="hapusBaris({{ $i }})" aria-label="{{ __('Hapus baris') }}">×</button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @foreach (['item_id', 'qty_base'] as $f)
            @error('form.'.$f) <div class="text-danger small px-3">{{ $message }}</div> @enderror
        @endforeach
        <div class="card-footer">
            <button class="btn btn-sm btn-outline-primary" type="button" wire:click="tambahBaris">{{ __('+ Baris') }}</button>
        </div>
    </div>

    <button class="btn btn-primary" type="button" wire:click="simpan">{{ __('Ajukan transfer') }}</button>
</div>
