<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ $nomor ? __('Ubah draf').' '.$nomor : __('Purchase Order baru') }}</h1>
            <p class="text-muted mb-0">{{ __('Pilih vendor dan gudang tujuan, lalu isi jumlah dan harga satuan (per satuan dasar) untuk baris Purchase Request yang akan dipesan.') }}</p>
        </div>
        <a class="btn btn-outline-secondary" href="{{ route('purchase-orders.index') }}">{{ __('Kembali') }}</a>
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
                <label class="form-label" for="po-vendor">{{ __('Vendor') }} <span class="wajib">*</span></label>
                <select class="form-select @error('form.vendor_id') is-invalid @enderror" id="po-vendor" wire:model.live="form.vendor_id">
                    <option value="">{{ __('Pilih vendor…') }}</option>
                    @foreach ($vendors as $v)
                        <option value="{{ $v->id }}">{{ $v->name }} ({{ $v->vendor_type?->label() }})</option>
                    @endforeach
                </select>
                @error('form.vendor_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                @php($terpilih = $vendors->firstWhere('id', (int) $form['vendor_id']))
                @if ($terpilih?->payment_terms) <div class="form-text">{{ __('Termin') }}: {{ $terpilih->payment_terms }}</div> @endif
            </div>
            <div class="col-md-3">
                <label class="form-label" for="po-gudang">{{ __('Gudang tujuan') }} <span class="wajib">*</span></label>
                <select class="form-select @error('form.warehouse_id') is-invalid @enderror" id="po-gudang" wire:model.live="form.warehouse_id">
                    <option value="">{{ __('Pilih gudang…') }}</option>
                    @foreach ($warehouses as $w)
                        <option value="{{ $w->id }}">{{ $w->code }} — {{ $w->name }}</option>
                    @endforeach
                </select>
                @error('form.warehouse_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-2">
                <label class="form-label" for="po-eta">{{ __('Perkiraan datang') }}</label>
                <input class="form-control @error('form.eta_date') is-invalid @enderror" id="po-eta" type="date" wire:model="form.eta_date">
                @error('form.eta_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3">
                <label class="form-label" for="po-catatan">{{ __('Keterangan') }}</label>
                <input class="form-control" id="po-catatan" type="text" maxlength="255" wire:model="form.notes" placeholder="{{ __('Opsional') }}">
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <strong>{{ __('Baris Purchase Request yang bisa dipesan') }}</strong>
            @if ($terbuka->isNotEmpty())
                <button class="btn btn-sm btn-outline-primary" type="button" wire:click="pesanSemua">{{ __('Isi semua sisa') }}</button>
            @endif
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('PRQ') }}</th>
                        <th scope="col">{{ __('Item') }}</th>
                        <th class="text-end" scope="col">{{ __('Sisa') }}</th>
                        <th scope="col" style="width: 9rem">{{ __('Jumlah dipesan') }}</th>
                        <th scope="col" style="width: 11rem">{{ __('Harga satuan (Rp)') }}</th>
                        <th class="text-end" scope="col">{{ __('Nilai') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($terbuka as $r)
                        @php($l = $r['line'])
                        <tr wire:key="po-baris-{{ $l->id }}">
                            <td class="small">
                                {{ $l->purchaseRequest?->number }}
                                @if ($l->required_date) <div class="text-muted">{{ __('Dibutuhkan') }} {{ $l->required_date->format('d/m/Y') }}</div> @endif
                            </td>
                            <td>
                                {{ $l->item?->code }}
                                @if (in_array((int) $l->item_id, $tetap, true)) <span class="badge text-bg-info" title="{{ __('Vendor tetap item ini') }}">{{ __('vendor tetap') }}</span> @endif
                                <div class="small text-muted">{{ $l->item?->name }}</div>
                            </td>
                            <td class="text-end">{{ number_format($r['available'], 2, ',', '.') }} <span class="small text-muted">{{ $l->item?->baseUom?->code }}</span></td>
                            <td><input class="form-control form-control-sm" type="number" step="0.0001" min="0" wire:model.live.debounce.500ms="qty.{{ $l->id }}" aria-label="{{ __('Jumlah dipesan') }} {{ $l->item?->code }}"></td>
                            <td><input class="form-control form-control-sm" type="number" step="0.01" min="0" wire:model.live.debounce.500ms="price.{{ $l->id }}" aria-label="{{ __('Harga satuan') }} {{ $l->item?->code }}"></td>
                            <td class="text-end text-nowrap">{{ $this->nilai($l->id) > 0 ? \App\Domain\Purchasing\Support\Money::format($this->nilai($l->id)) : '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-muted py-4" colspan="6">
                                {{ $form['warehouse_id'] === '' ? __('Pilih gudang tujuan dulu.') : __('Tidak ada baris Purchase Request yang disetujui dan belum dipesan di gudang ini.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($terbuka->isNotEmpty())
                    <tfoot>
                        <tr>
                            <th class="text-end" colspan="5">{{ __('Nilai PO') }}</th>
                            <th class="text-end text-nowrap">{{ \App\Domain\Purchasing\Support\Money::format($total) }}</th>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
        @error('form.lines') <div class="card-footer text-danger small">{{ $message }}</div> @enderror
    </div>

    <div class="d-flex gap-2">
        <button class="btn btn-outline-primary" type="button" wire:click="simpan">{{ __('Simpan draf') }}</button>
        @can('po.submit')
            <button class="btn btn-primary" type="button" wire:click="simpanDanAjukan">{{ __('Simpan dan ajukan') }}</button>
        @endcan
    </div>
</div>
