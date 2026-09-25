<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Harga beli vendor') }}</h1>
            <p class="text-muted mb-0">{{ __('Harga per satuan dasar item dari tiap vendor. Harga dipakai sebagai isian bawaan PO dan boleh diubah di PO. Harga lama tetap tersimpan sebagai riwayat.') }}</p>
        </div>
        @can('create', App\Domain\Purchasing\Models\VendorPrice::class)
            <button class="btn btn-primary" type="button" wire:click="buat"><i class="bi bi-plus-lg"></i> {{ __('Harga baru') }}</button>
        @endcan
    </div>

    @if ($ruleError !== '')
        <div class="alert alert-danger" role="alert">
            {{ $ruleError }}
            @if ($ruleCode !== '') <span class="badge text-bg-dark ms-1">{{ $ruleCode }}</span> @endif
        </div>
    @endif

    @if ($formTerbuka)
        <div class="card border-primary mb-3">
            <div class="card-header"><strong>{{ __('Harga beli baru') }}</strong></div>
            <div class="card-body row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="hv-vendor">{{ __('Vendor') }} <span class="wajib">*</span></label>
                    <select class="form-select @error('form.vendor_id') is-invalid @enderror" id="hv-vendor" wire:model="form.vendor_id">
                        <option value="">{{ __('Pilih vendor…') }}</option>
                        @foreach ($vendors as $v)
                            <option value="{{ $v->id }}">{{ $v->name }}</option>
                        @endforeach
                    </select>
                    @error('form.vendor_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="hv-item">{{ __('Item') }} <span class="wajib">*</span></label>
                    <select class="form-select @error('form.item_id') is-invalid @enderror" id="hv-item" wire:model="form.item_id">
                        <option value="">{{ __('Pilih item…') }}</option>
                        @foreach ($items as $i)
                            <option value="{{ $i->id }}">{{ $i->code }} — {{ $i->name }}</option>
                        @endforeach
                    </select>
                    @error('form.item_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="hv-harga">{{ __('Harga satuan (Rp)') }} <span class="wajib">*</span></label>
                    <input class="form-control @error('form.unit_price') is-invalid @enderror" id="hv-harga" type="number" step="0.01" min="0" wire:model="form.unit_price">
                    @error('form.unit_price') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="hv-berlaku">{{ __('Berlaku mulai') }} <span class="wajib">*</span></label>
                    <input class="form-control @error('form.valid_from') is-invalid @enderror" id="hv-berlaku" type="date" wire:model="form.valid_from">
                    @error('form.valid_from') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-12">
                    <label class="form-label" for="hv-catatan">{{ __('Keterangan') }}</label>
                    <input class="form-control" id="hv-catatan" type="text" maxlength="255" wire:model="form.notes" placeholder="{{ __('Opsional, mis. nomor penawaran') }}">
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <button class="btn btn-primary" type="button" wire:click="simpan">{{ __('Simpan') }}</button>
                <button class="btn btn-outline-secondary" type="button" wire:click="batalForm">{{ __('Batal') }}</button>
            </div>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body row g-3 align-items-end">
            <div class="col-lg-4">
                <label class="form-label" for="cari-hv">{{ __('Cari') }}</label>
                <input class="form-control" id="cari-hv" type="search" wire:model.live.debounce.400ms="search" placeholder="{{ __('Kode/nama item atau vendor') }}">
            </div>
            <div class="col-lg-4">
                <label class="form-label" for="filter-vendor-hv">{{ __('Vendor') }}</label>
                <select class="form-select" id="filter-vendor-hv" wire:model.live="vendorFilter">
                    <option value="">{{ __('Semua') }}</option>
                    @foreach ($vendors as $v)
                        <option value="{{ $v->id }}">{{ $v->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-4">
                <div class="form-check">
                    <input class="form-check-input" id="hv-semua" type="checkbox" wire:model.live="semua">
                    <label class="form-check-label" for="hv-semua">{{ __('Tampilkan juga harga nonaktif (riwayat)') }}</label>
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
                        <th scope="col">{{ __('Vendor') }}</th>
                        <th class="text-end" scope="col">{{ __('Harga satuan') }}</th>
                        <th scope="col">{{ __('Berlaku mulai') }}</th>
                        <th scope="col">{{ __('Keterangan') }}</th>
                        <th scope="col">{{ __('Status') }}</th>
                        <th scope="col"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($prices as $h)
                        <tr wire:key="hv-{{ $h->id }}">
                            <td>{{ $h->item?->code }} <div class="small text-muted">{{ $h->item?->name }}</div></td>
                            <td>{{ $h->vendor?->name }}</td>
                            <td class="text-end text-nowrap">{{ \App\Domain\Purchasing\Support\Money::format($h->unit_price) }} <span class="small text-muted">/ {{ $h->item?->baseUom?->code }}</span></td>
                            <td>{{ $h->valid_from?->format('d/m/Y') }}</td>
                            <td class="small">{{ $h->notes }} <div class="text-muted">{{ $h->creator?->name }}</div></td>
                            <td>
                                @if ($h->is_active)
                                    <span class="badge text-bg-success">{{ __('Aktif') }}</span>
                                @else
                                    <span class="badge text-bg-secondary">{{ __('Nonaktif') }}</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if ($h->is_active)
                                    @can('update', $h)
                                        <button class="btn btn-sm btn-outline-danger" type="button" wire:click="nonaktifkan({{ $h->id }})" wire:confirm="{{ __('Nonaktifkan harga ini?') }}">{{ __('Nonaktifkan') }}</button>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-muted py-4" colspan="7">{{ __('Belum ada harga beli vendor.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($prices->hasPages())
            <div class="card-footer">{{ $prices->links() }}</div>
        @endif
    </div>
</div>
