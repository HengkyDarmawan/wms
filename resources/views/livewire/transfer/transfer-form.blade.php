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
                {{-- A-106: asal tidak dibatasi cakupan; daftar sama dengan tujuan (dimuat sekaligus). --}}
                <x-pilih model="form.from_warehouse_id" id="trf-asal" live wajib :label="__('Gudang asal')" :kosong="__('Pilih gudang…')"
                         :options="$opsiGudang" />
            </div>
            <div class="col-md-4">
                <x-pilih model="form.to_warehouse_id" id="trf-tujuan" live wajib :label="__('Gudang tujuan')" :kosong="__('Pilih gudang…')"
                         :options="$opsiGudang" />
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
                                <x-pilih model="rows.{{ $i }}.item_id" id="trf-item-{{ $i }}" server live kecil :kunci="(string) count($rows)"
                                         :aria="__('Item')" :kosong="__('Pilih item…')" :options="$opsiItem[$i] ?? []" />
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
