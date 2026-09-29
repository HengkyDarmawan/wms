{{-- Isian kondisi aturan (BR-APR-07): hanya kondisi yang bermakna untuk jenis dokumen ($allowed). Dipakai mode sederhana & lanjutan. --}}
            @if (in_array('warehouse_ids', $allowed, true))
                <div class="col-md-4">
                    <label class="form-label" for="kondisi-gudang">{{ __('Gudang') }}</label>
                    <x-pilih-tag live model="conditions.warehouse_ids" id="kondisi-gudang" :options="$warehouses->mapWithKeys(fn ($w) => [$w->id => $w->code.' — '.$w->name])->all()" />
                </div>
            @endif
            @if (in_array('project_ids', $allowed, true))
                <div class="col-md-4">
                    <label class="form-label" for="kondisi-proyek">{{ __('Proyek') }}</label>
                    <x-pilih-tag live model="conditions.project_ids" id="kondisi-proyek" :options="$projects->mapWithKeys(fn ($p) => [$p->id => $p->code.' — '.$p->name])->all()" />
                </div>
            @endif
            @if (in_array('category_ids', $allowed, true))
                <div class="col-md-4">
                    <label class="form-label" for="kondisi-kategori">{{ __('Kategori barang (termasuk sub-kategori)') }}</label>
                    <x-pilih-tag live model="conditions.category_ids" id="kondisi-kategori" :options="$categories->mapWithKeys(fn ($c) => [$c->id => $c->code.' — '.$c->name])->all()" />
                </div>
            @endif
            @if (in_array('ownership_models', $allowed, true))
                <div class="col-md-4">
                    <label class="form-label" for="kondisi-kepemilikan">{{ __('Jenis barang (aset/habis pakai)') }}</label>
                    <x-pilih-tag live model="conditions.ownership_models" id="kondisi-kepemilikan" :options="$ownerships" />
                </div>
            @endif
            @if (in_array('vendor_types', $kondisiUsang, true))
                <div class="col-12">
                    <div class="alert alert-warning py-2 mb-0" role="alert">
                        {{ __('Aturan ini memakai kondisi Jenis vendor yang tidak lagi dinilai untuk jenis dokumen ini — vendor baru dipilih di PO. Buat aturan PO untuk jenis vendor; bila aturan ini disimpan, kondisi itu dihapus dan aturan berlaku menurut kondisi lainnya.') }}
                    </div>
                </div>
            @endif
            @if (in_array('vendor_types', $allowed, true))
                <div class="col-md-4">
                    <label class="form-label" for="kondisi-vendor">{{ __('Jenis vendor') }}</label>
                    <x-pilih-tag live model="conditions.vendor_types" id="kondisi-vendor" :options="$vendorTypes" />
                </div>
            @endif
            @if (in_array('line_count_min', $allowed, true))
                <div class="col-md-3">
                    <label class="form-label" for="kondisi-baris">{{ __('Jumlah baris ≥') }}</label>
                    <input class="form-control" id="kondisi-baris" type="number" min="1" wire:model.live.blur="conditions.line_count_min">
                </div>
            @endif
            @if (in_array('order_value_min', $allowed, true))
                <div class="col-md-3">
                    <label class="form-label" for="kondisi-nilai">{{ __('Nilai PO ≥ (Rp)') }}</label>
                    <input class="form-control" id="kondisi-nilai" type="number" min="0" step="1000" wire:model.live.blur="conditions.order_value_min">
                    <div class="form-text">{{ __('Hanya untuk Purchase Order.') }}</div> {{-- D-28 --}}
                </div>
            @endif
            @if (in_array('line_qty_min', $allowed, true))
                <div class="col-md-3">
                    <label class="form-label" for="kondisi-qty">{{ __('Jumlah per baris ≥ (satuan dasar)') }}</label>
                    <input class="form-control" id="kondisi-qty" type="number" min="0" step="0.0001" wire:model.live.blur="conditions.line_qty_min">
                </div>
            @endif
            @if (in_array('from_client', $allowed, true))
                <div class="col-md-3">
                    <label class="form-label" for="kondisi-klien">{{ __('Permintaan dari klien') }}</label>
                    <select class="form-select" id="kondisi-klien" wire:model.live="conditions.from_client">
                        <option value="">{{ __('Abaikan') }}</option>
                        <option value="1">{{ __('Ya') }}</option>
                        <option value="0">{{ __('Tidak') }}</option>
                    </select>
                </div>
            @endif
            @if (in_array('purchase_request_origins', $allowed, true))
                <div class="col-md-4">
                    <label class="form-label" for="kondisi-asal">{{ __('Asal PRQ') }}</label>
                    <x-pilih-tag live model="conditions.purchase_request_origins" id="kondisi-asal" :options="$origins" />
                </div>
            @endif
            @if (in_array('count_types', $allowed, true))
                <div class="col-md-4">
                    <label class="form-label" for="kondisi-opname">{{ __('Jenis opname') }}</label>
                    <x-pilih-tag live model="conditions.count_types" id="kondisi-opname" :options="$countTypes" />
                </div>
            @endif
