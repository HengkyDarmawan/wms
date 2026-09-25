<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Vendor') }}</h1>
            <p class="text-muted mb-0">
                {{ __('Pemasok barang. Harga dan penawaran ada di modul Purchasing, bukan di sini.') }}
            </p>
        </div>

        @can('create', \App\Domain\Master\Models\Vendor::class)
            <div class="d-flex gap-2">
                <a class="btn btn-outline-primary" href="{{ route('imports.index') }}">
                    <i class="bi bi-file-earmark-spreadsheet"></i> {{ __('Impor Excel') }}
                </a>
                <button class="btn btn-primary" type="button" wire:click="buat">
                    <i class="bi bi-plus-lg"></i> {{ __('Tambah vendor') }}
                </button>
            </div>
        @endcan
    </div>

    @if ($ruleError !== '')
        <div class="alert alert-danger" role="alert">{{ $ruleError }}</div>
    @endif

    @if ($showForm)
        <div class="card mb-3">
            <div class="card-header">
                <strong>{{ $editingId ? __('Ubah vendor') : __('Vendor baru') }}</strong>
                <span class="text-muted small ms-2">{{ __('Field bertanda * wajib diisi.') }}</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label" for="vendor-kode">{{ __('Kode') }}</label>
                        <input class="form-control @error('form.code') is-invalid @enderror" id="vendor-kode"
                               type="text" wire:model="form.code" @disabled($editingId !== null)
                               placeholder="{{ __('Dibuat otomatis bila kosong') }}">
                        @error('form.code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-5">
                        <label class="form-label" for="vendor-nama">{{ __('Nama vendor') }} <span class="wajib">*</span></label>
                        <input class="form-control @error('form.name') is-invalid @enderror" id="vendor-nama"
                               type="text" wire:model="form.name">
                        @error('form.name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="vendor-jenis">{{ __('Jenis vendor') }} <span class="wajib">*</span></label>
                        <select class="form-select" id="vendor-jenis" wire:model="form.vendor_type">
                            @foreach ($jenis as $nilai => $label)
                                <option value="{{ $nilai }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="vendor-status">{{ __('Status') }} <span class="wajib">*</span></label>
                        <select class="form-select" id="vendor-status" wire:model="form.status">
                            @foreach ($statuses as $nilai => $label)
                                <option value="{{ $nilai }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">
                            {{ __('Vendor Sementara boleh dipakai memesan, tapi harus dilengkapi Admin.') }}
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="vendor-kontak">{{ __('Nama kontak') }}</label>
                        <input class="form-control" id="vendor-kontak" type="text" wire:model="form.contact_name">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="vendor-npwp">{{ __('NPWP') }}</label>
                        <input class="form-control" id="vendor-npwp" type="text" wire:model="form.tax_id">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="vendor-telepon">{{ __('Telepon') }}</label>
                        <input class="form-control @error('form.phone') is-invalid @enderror" id="vendor-telepon"
                               type="text" wire:model="form.phone">
                        @error('form.phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="vendor-email">{{ __('Email') }}</label>
                        <input class="form-control @error('form.email') is-invalid @enderror" id="vendor-email"
                               type="email" wire:model="form.email">
                        @error('form.email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="vendor-termin">{{ __('Termin pembayaran') }}</label>
                        <input class="form-control" id="vendor-termin" type="text" wire:model="form.payment_terms"
                               placeholder="{{ __('mis. 30 hari setelah terima barang') }}">
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="vendor-alamat">{{ __('Alamat') }}</label>
                        <textarea class="form-control" id="vendor-alamat" rows="2" wire:model="form.address"></textarea>
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <button class="btn btn-primary" type="button" wire:click="simpan">{{ __('Simpan') }}</button>
                <button class="btn btn-outline-secondary" type="button" wire:click="batalForm">{{ __('Batal') }}</button>
            </div>
        </div>
    @endif

    @if ($deactivatingId)
        <div class="card border-danger mb-3">
            <div class="card-header text-danger"><strong>{{ __('Nonaktifkan vendor') }}</strong></div>
            <div class="card-body row g-3">
                <div class="col-md-5">
                    <label class="form-label" for="vendor-alasan">{{ __('Alasan') }} <span class="wajib">*</span></label>
                    <select class="form-select @error('reasonCode') is-invalid @enderror" id="vendor-alasan"
                            wire:model="reasonCode">
                        <option value="">{{ __('Pilih alasan…') }}</option>
                        @foreach ($alasan as $kode => $label)
                            <option value="{{ $kode }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('reasonCode') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-7">
                    <label class="form-label" for="vendor-keterangan">{{ __('Keterangan') }}</label>
                    <input class="form-control" id="vendor-keterangan" type="text" wire:model="reasonNotes"
                           placeholder="{{ __('Opsional') }}">
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <button class="btn btn-danger" type="button" wire:click="nonaktifkan">{{ __('Nonaktifkan') }}</button>
                <button class="btn btn-outline-secondary" type="button" wire:click="batalNonaktif">{{ __('Batal') }}</button>
            </div>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body row g-3">
            <div class="col-md-5">
                <label class="form-label" for="cari-vendor">{{ __('Cari vendor') }}</label>
                <input class="form-control" id="cari-vendor" type="search"
                       wire:model.live.debounce.400ms="search" placeholder="{{ __('Nama, kode, atau kontak…') }}">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="filter-jenis-vendor">{{ __('Jenis') }}</label>
                <select class="form-select" id="filter-jenis-vendor" wire:model.live="typeFilter">
                    <option value="">{{ __('Semua jenis') }}</option>
                    @foreach ($jenis as $nilai => $label)
                        <option value="{{ $nilai }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="filter-status-vendor">{{ __('Status') }}</label>
                <select class="form-select" id="filter-status-vendor" wire:model.live="statusFilter">
                    <option value="">{{ __('Semua') }}</option>
                    @foreach ($statuses as $nilai => $label)
                        <option value="{{ $nilai }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Vendor') }}</th>
                        <th>{{ __('Jenis') }}</th>
                        <th>{{ __('Kontak') }}</th>
                        <th class="text-end">{{ __('Item dipasok') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-end">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($vendors as $vendor)
                        <tr wire:key="vendor-{{ $vendor->id }}">
                            <td>
                                <div class="fw-semibold">{{ $vendor->name }}</div>
                                <div class="small text-muted">{{ $vendor->code }}</div>
                            </td>
                            <td>{{ $vendor->vendor_type->label() }}</td>
                            <td>
                                <div>{{ $vendor->contact_name ?: '—' }}</div>
                                <div class="small text-muted">{{ $vendor->phone ?: $vendor->email ?: '—' }}</div>
                            </td>
                            <td class="text-end">{{ $vendor->item_vendors_count }}</td>
                            <td>
                                <span class="badge text-bg-{{ $vendor->statusBadge() }}">
                                    {{ $vendor->status->label() }}
                                </span>
                            </td>
                            <td class="text-end">
                                @can('update', $vendor)
                                    <button class="btn btn-sm btn-outline-secondary" type="button"
                                            wire:click="ubah({{ $vendor->id }})">{{ __('Ubah') }}</button>
                                @endcan

                                @if ($vendor->is_active)
                                    @can('deactivate', $vendor)
                                        <button class="btn btn-sm btn-outline-danger" type="button"
                                                wire:click="mintaNonaktif({{ $vendor->id }})">
                                            {{ __('Nonaktifkan') }}
                                        </button>
                                    @endcan
                                @else
                                    @can('update', $vendor)
                                        <button class="btn btn-sm btn-outline-success" type="button"
                                                wire:click="aktifkan({{ $vendor->id }})">{{ __('Aktifkan') }}</button>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">{{ __('Belum ada vendor.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($vendors->hasPages())
            <div class="card-footer">{{ $vendors->links() }}</div>
        @endif
    </div>
</div>
