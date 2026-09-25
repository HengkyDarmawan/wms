<div class="card border-primary mb-3">
    <div class="card-header"><strong>{{ __('Catat pemesanan') }}</strong> <span class="small text-muted">{{ __('satu catatan per vendor/toko; baris boleh dipecah ke beberapa vendor') }}</span></div>
    <div class="card-body row g-3">
        <div class="col-md-4">
            <label class="form-label" for="po-vendor">{{ __('Vendor') }}</label>
            <select class="form-select @error('order.vendor_id') is-invalid @enderror" id="po-vendor" wire:model="order.vendor_id">
                <option value="">{{ __('Pilih vendor…') }}</option>
                @foreach ($vendors as $v)
                    <option value="{{ $v->id }}">{{ $v->name }} ({{ $vendorTypes[$v->vendor_type?->value] ?? '' }}){{ in_array((int) $v->id, $suggested, true) ? ' — '.__('vendor tetap item') : '' }}</option>
                @endforeach
            </select>
            @error('order.vendor_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-8">
            <div class="small text-muted mb-1">{{ __('Atau vendor baru sementara (dilengkapi Admin Master Data nanti):') }}</div>
            <div class="row g-2">
                <div class="col-md-5">
                    <input class="form-control" type="text" wire:model="order.new_vendor_name" placeholder="{{ __('Nama vendor/toko baru') }}" aria-label="{{ __('Nama vendor baru') }}">
                </div>
                <div class="col-md-4">
                    <select class="form-select @error('order.new_vendor_type') is-invalid @enderror" wire:model="order.new_vendor_type" aria-label="{{ __('Jenis vendor baru') }}">
                        <option value="">{{ __('Jenis…') }}</option>
                        @foreach ($vendorTypes as $nilai => $label)
                            <option value="{{ $nilai }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('order.new_vendor_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <input class="form-control" type="text" wire:model="order.new_vendor_phone" placeholder="{{ __('Telepon') }}" aria-label="{{ __('Telepon vendor baru') }}">
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="po-no">{{ __('Nomor PO') }}</label>
            <input class="form-control" id="po-no" type="text" wire:model="order.external_po_no">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="po-mp">{{ __('Nomor pesanan toko online') }}</label>
            <input class="form-control" id="po-mp" type="text" wire:model="order.marketplace_order_no">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="po-resi">{{ __('Nomor resi') }}</label>
            <input class="form-control" id="po-resi" type="text" wire:model="order.tracking_no">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="po-eta">{{ __('Perkiraan datang') }}</label>
            <input class="form-control @error('order.eta_date') is-invalid @enderror" id="po-eta" type="date" wire:model="order.eta_date">
            @error('order.eta_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6">
            <label class="form-label" for="po-catv">{{ __('Alasan memilih vendor ini') }}</label>
            <input class="form-control" id="po-catv" type="text" wire:model="order.vendor_note" placeholder="{{ __('Opsional, bila bukan vendor tetap item') }}">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="po-ket">{{ __('Keterangan') }}</label>
            <input class="form-control" id="po-ket" type="text" wire:model="order.notes">
        </div>
        <div class="col-12">
            @error('order.lines') <div class="alert alert-danger py-2">{{ $message }}</div> @enderror
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Item') }}</th>
                        <th class="text-end" scope="col">{{ __('Belum dipesan') }}</th>
                        <th scope="col">{{ __('Dipesan ke vendor ini') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lines as $l)
                        <tr wire:key="po-line-{{ $l->id }}">
                            <td>{{ $l->item?->code }} <span class="small text-muted">{{ $l->item?->name }}</span></td>
                            <td class="text-end">{{ number_format($l->unorderedQty(), 2, ',', '.') }}</td>
                            <td><input class="form-control form-control-sm" type="number" step="any" min="0" wire:model="orderQty.{{ $l->id }}" aria-label="{{ __('Jumlah dipesan') }} {{ $l->item?->code }}" @disabled($l->unorderedQty() <= 0)></td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        </div>
    </div>
    <div class="card-footer d-flex gap-2">
        <button class="btn btn-primary" type="button" wire:click="catatPesanan" wire:loading.attr="disabled">{{ __('Simpan catatan') }}</button>
        <button class="btn btn-outline-secondary" type="button" wire:click="tutupDialog">{{ __('Tutup') }}</button>
    </div>
</div>
