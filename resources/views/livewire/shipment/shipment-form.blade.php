<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Surat jalan baru') }}</h1>
            <p class="text-muted mb-0">
                {{ __('Beberapa tugas picking boleh digabung selama gudang asal dan tujuannya sama.') }}
            </p>
        </div>
        <a class="btn btn-outline-secondary" href="{{ route('shipments.index') }}">{{ __('Kembali') }}</a>
    </div>

    @if ($ruleError !== '')
        <div class="alert alert-danger" role="alert">
            {{ $ruleError }}
            @if ($ruleCode !== '')
                <span class="badge text-bg-dark ms-1">{{ $ruleCode }}</span>
            @endif
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-header"><strong>{{ __('Gudang asal dan muatan') }}</strong></div>
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-md-5">
                    <label class="form-label" for="sj-gudang">{{ __('Gudang asal') }} <span class="wajib">*</span></label>
                    <select class="form-select" id="sj-gudang" wire:model.live="form.warehouse_id">
                        <option value="">{{ __('Pilih gudang…') }}</option>
                        @foreach ($warehouses as $gudang)
                            <option value="{{ $gudang->id }}">{{ $gudang->code }} — {{ $gudang->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            @if ($tasks->isEmpty())
                <p class="text-muted mb-0">
                    {{ __('Tidak ada tugas picking selesai yang masih punya barang di Loading Area gudang ini.') }}
                </p>
            @else
                @error('pickTaskIds') <div class="text-danger small mb-2">{{ $message }}</div> @enderror

                @foreach ($tasks as $t)
                    <div class="form-check border rounded p-3 mb-2" wire:key="pilih-pck-{{ $t->id }}">
                        <input class="form-check-input" id="pck-{{ $t->id }}" type="checkbox"
                               value="{{ $t->id }}" wire:model="pickTaskIds">
                        <label class="form-check-label w-100" for="pck-{{ $t->id }}">
                            <strong>{{ $t->number }}</strong>
                            <div class="small text-muted">
                                @foreach ($t->lines as $l)
                                    {{ $l->item?->code }} ({{ number_format($l->unshippedQty(), 2, ',', '.') }}){{ ! $loop->last ? ' · ' : '' }}
                                @endforeach
                            </div>
                        </label>
                    </div>
                @endforeach
            @endif
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><strong>{{ __('Tujuan') }}</strong></div>
        <div class="card-body row g-3">
            <div class="col-md-4">
                <label class="form-label" for="sj-tujuan">{{ __('Jenis tujuan') }} <span class="wajib">*</span></label>
                <select class="form-select @error('form.destination_type') is-invalid @enderror" id="sj-tujuan"
                        wire:model.live="form.destination_type">
                    <option value="">{{ __('Pilih…') }}</option>
                    @foreach ($destinations as $nilai => $label)
                        <option value="{{ $nilai }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('form.destination_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            @if ($form['destination_type'] === 'project_client')
                <div class="col-md-8">
                    <label class="form-label" for="sj-proyek">{{ __('Proyek tujuan') }} <span class="wajib">*</span></label>
                    <select class="form-select @error('form.destination_project_id') is-invalid @enderror" id="sj-proyek"
                            wire:model="form.destination_project_id">
                        <option value="">{{ __('Pilih proyek…') }}</option>
                        @foreach ($projects as $proyek)
                            <option value="{{ $proyek->id }}">{{ $proyek->code }} — {{ $proyek->name }}</option>
                        @endforeach
                    </select>
                    @error('form.destination_project_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            @elseif (in_array($form['destination_type'], ['site_warehouse', 'warehouse'], true))
                <div class="col-md-8">
                    <label class="form-label" for="sj-gudang-tujuan">
                        {{ __('Gudang tujuan') }} <span class="wajib">*</span>
                    </label>
                    <select class="form-select @error('form.destination_warehouse_id') is-invalid @enderror"
                            id="sj-gudang-tujuan" wire:model="form.destination_warehouse_id">
                        <option value="">{{ __('Pilih gudang…') }}</option>
                        @foreach ($gudangTujuan as $gudang)
                            <option value="{{ $gudang->id }}">{{ $gudang->code }} — {{ $gudang->name }}</option>
                        @endforeach
                    </select>
                    @error('form.destination_warehouse_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            @elseif ($form['destination_type'] === 'vendor')
                <div class="col-md-8">
                    <label class="form-label" for="sj-vendor">{{ __('Vendor tujuan') }} <span class="wajib">*</span></label>
                    <select class="form-select @error('form.destination_vendor_id') is-invalid @enderror" id="sj-vendor"
                            wire:model="form.destination_vendor_id">
                        <option value="">{{ __('Pilih vendor…') }}</option>
                        @foreach ($vendors as $vendor)
                            <option value="{{ $vendor->id }}">{{ $vendor->name }}</option>
                        @endforeach
                    </select>
                    @error('form.destination_vendor_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            @endif
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><strong>{{ __('Cara kirim') }}</strong></div>
        <div class="card-body row g-3">
            <div class="col-md-4">
                <label class="form-label" for="sj-cara">{{ __('Cara kirim') }} <span class="wajib">*</span></label>
                <select class="form-select @error('form.shipment_method') is-invalid @enderror" id="sj-cara"
                        wire:model.live="form.shipment_method">
                    <option value="">{{ __('Pilih…') }}</option>
                    @foreach ($methods as $nilai => $label)
                        <option value="{{ $nilai }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('form.shipment_method') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            @if ($form['shipment_method'] === 'own_fleet')
                <div class="col-md-4">
                    <label class="form-label" for="sj-kendaraan">{{ __('Kendaraan') }} <span class="wajib">*</span></label>
                    <select class="form-select @error('form.vehicle_id') is-invalid @enderror" id="sj-kendaraan"
                            wire:model="form.vehicle_id">
                        <option value="">{{ __('Pilih kendaraan…') }}</option>
                        @foreach ($vehicles as $k)
                            <option value="{{ $k->id }}">{{ $k->plate_no }}</option>
                        @endforeach
                    </select>
                    @error('form.vehicle_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="sj-driver">{{ __('Driver') }} <span class="wajib">*</span></label>
                    <select class="form-select @error('form.driver_id') is-invalid @enderror" id="sj-driver"
                            wire:model="form.driver_id">
                        <option value="">{{ __('Pilih driver…') }}</option>
                        @foreach ($drivers as $d)
                            <option value="{{ $d->id }}">{{ $d->name }}</option>
                        @endforeach
                    </select>
                    @error('form.driver_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            @elseif ($form['shipment_method'] === 'carrier')
                <div class="col-md-4">
                    <label class="form-label" for="sj-ekspedisi">{{ __('Ekspedisi') }} <span class="wajib">*</span></label>
                    <select class="form-select @error('form.carrier_id') is-invalid @enderror" id="sj-ekspedisi"
                            wire:model="form.carrier_id">
                        <option value="">{{ __('Pilih ekspedisi…') }}</option>
                        @foreach ($carriers as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                    @error('form.carrier_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="sj-resi">{{ __('Nomor resi') }} <span class="wajib">*</span></label>
                    <input class="form-control @error('form.tracking_no') is-invalid @enderror" id="sj-resi"
                           type="text" wire:model="form.tracking_no">
                    @error('form.tracking_no') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            @elseif ($form['shipment_method'] === 'self_delivered')
                <div class="col-md-8">
                    <label class="form-label" for="sj-pembawa">{{ __('Nama pembawa') }} <span class="wajib">*</span></label>
                    <input class="form-control @error('form.carried_by_name') is-invalid @enderror" id="sj-pembawa"
                           type="text" wire:model="form.carried_by_name">
                    @error('form.carried_by_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            @endif

            <div class="col-12">
                <label class="form-label" for="sj-catatan">{{ __('Catatan') }}</label>
                <input class="form-control" id="sj-catatan" type="text" wire:model="form.notes"
                       placeholder="{{ __('Opsional') }}">
            </div>
        </div>
    </div>

    <button class="btn btn-primary" type="button" wire:click="simpan">{{ __('Susun surat jalan') }}</button>
</div>
