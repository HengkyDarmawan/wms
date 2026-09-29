<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ $draf ? __('Tinjau draf PRQ') : __('PRQ manual') }}</h1>
            <p class="text-muted mb-0">
                {{ $draf
                    ? __('Draf dari titik pesan ulang: sesuaikan jumlah dan tanggal, simpan, lalu ajukan dari halaman detail.')
                    : __('Minta pembelian untuk gudang tujuan. PRQ manual langsung diajukan; approval mengikuti aturan. Jumlah dalam satuan dasar, tanpa harga.') }}
            </p>
        </div>
        <a class="btn btn-outline-secondary" href="{{ $draf ? route('purchase-requests.show', $purchaseRequestId) : route('purchase-requests.index') }}">{{ __('Kembali') }}</a>
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
                <x-pilih model="form.warehouse_id" id="prq-gudang" wajib :label="__('Gudang tujuan')" :disabled="$draf"
                         :kosong="__('Pilih gudang…')" :options="$warehouses->map(fn ($g) => ['value' => $g->id, 'text' => $g->code.' — '.$g->name])->all()" />
            </div>
            <div class="col-md-4">
                {{-- A-393: proyek dalam cakupan, dicari ke server. --}}
                <x-pilih model="form.project_id" id="prq-proyek" server :label="__('Proyek')" :disabled="$draf"
                         :kosong="__('Tanpa proyek (stok gudang)')" :options="$opsiProyek" />
            </div>
            <div class="col-md-4">
                <label class="form-label" for="prq-ket">{{ __('Keterangan') }}</label>
                <input class="form-control" id="prq-ket" type="text" wire:model="form.notes" placeholder="{{ __('Opsional') }}">
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>{{ __('Baris barang') }}</strong>
            <button class="btn btn-sm btn-outline-primary" type="button" wire:click="tambahBaris">{{ __('Tambah baris') }}</button>
        </div>
        <div class="card-body">
            @error('form.lines') <div class="alert alert-danger py-2">{{ $message }}</div> @enderror
            @foreach ($rows as $i => $r)
                <div class="row g-2 align-items-end mb-2" wire:key="prq-row-{{ $i }}">
                    <div class="col-md-5">
                        <x-pilih model="rows.{{ $i }}.item_id" id="prq-item-{{ $i }}" server kecil wajib :label="__('Item')"
                                 :kunci="(string) count($rows)" :kosong="__('Pilih item…')" :options="$opsiItem[$i] ?? []" />
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small" for="prq-qty-{{ $i }}">{{ __('Jumlah') }} <span class="wajib">*</span></label>
                        <input class="form-control form-control-sm" id="prq-qty-{{ $i }}" type="number" step="any" min="0" wire:model="rows.{{ $i }}.qty_base">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small" for="prq-tgl-{{ $i }}">{{ __('Dibutuhkan') }}</label>
                        <input class="form-control form-control-sm" id="prq-tgl-{{ $i }}" type="date" wire:model="rows.{{ $i }}.required_date">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small" for="prq-ketb-{{ $i }}">{{ __('Keterangan') }}</label>
                        <input class="form-control form-control-sm" id="prq-ketb-{{ $i }}" type="text" wire:model="rows.{{ $i }}.notes">
                    </div>
                    <div class="col-md-1">
                        <button class="btn btn-sm btn-outline-danger w-100" type="button" wire:click="hapusBaris({{ $i }})" aria-label="{{ __('Hapus baris') }}">&times;</button>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="card-footer">
            <button class="btn btn-primary" type="button" wire:click="simpan" wire:loading.attr="disabled">
                {{ $draf ? __('Simpan draf') : __('Ajukan PRQ') }}
            </button>
        </div>
    </div>
</div>
