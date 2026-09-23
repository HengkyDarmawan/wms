<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Data referensi') }}</h1>
            <p class="text-muted mb-0">
                {{ __('Alasan baku, kategori penyimpanan, kendaraan, dan ekspedisi.') }}
            </p>
        </div>

        <button class="btn btn-primary" type="button" wire:click="buat">
            <i class="bi bi-plus-lg"></i> {{ __('Tambah') }}
        </button>
    </div>

    @if ($ruleError !== '')
        <div class="alert alert-danger" role="alert">{{ $ruleError }}</div>
    @endif

    <ul class="nav nav-tabs mb-3">
        @foreach ([
            'alasan' => __('Alasan'),
            'penyimpanan' => __('Kategori penyimpanan'),
            'kendaraan' => __('Kendaraan'),
            'ekspedisi' => __('Ekspedisi'),
        ] as $kunci => $label)
            <li class="nav-item">
                <button class="nav-link {{ $tab === $kunci ? 'active' : '' }}" type="button"
                        wire:click="pilihTab('{{ $kunci }}')">{{ $label }}</button>
            </li>
        @endforeach
    </ul>

    @if ($showForm)
        <div class="card mb-3">
            <div class="card-header">
                <strong>{{ $editingId ? __('Ubah data') : __('Data baru') }}</strong>
                <span class="text-muted small ms-2">{{ __('Field bertanda * wajib diisi.') }}</span>
            </div>
            <div class="card-body row g-3">
                @if ($tab === 'alasan')
                    <div class="col-md-4">
                        <label class="form-label" for="ref-konteks">{{ __('Konteks') }} <span class="wajib">*</span></label>
                        <select class="form-select" id="ref-konteks" wire:model="form.context"
                                @disabled($editingId !== null)>
                            @foreach ($konteks as $nilai => $label)
                                <option value="{{ $nilai }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="ref-kode">{{ __('Kode') }} <span class="wajib">*</span></label>
                        <input class="form-control @error('form.code') is-invalid @enderror" id="ref-kode"
                               type="text" wire:model="form.code" @disabled($editingId !== null)>
                        @error('form.code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-5">
                        <label class="form-label" for="ref-label">{{ __('Label') }} <span class="wajib">*</span></label>
                        <input class="form-control @error('form.label') is-invalid @enderror" id="ref-label"
                               type="text" wire:model="form.label">
                        @error('form.label') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                @elseif ($tab === 'penyimpanan')
                    <div class="col-md-3">
                        <label class="form-label" for="ref-kode-simpan">{{ __('Kode') }} <span class="wajib">*</span></label>
                        <input class="form-control @error('form.code') is-invalid @enderror" id="ref-kode-simpan"
                               type="text" wire:model="form.code" @disabled($editingId !== null)>
                        @error('form.code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-5">
                        <label class="form-label" for="ref-nama-simpan">{{ __('Nama') }} <span class="wajib">*</span></label>
                        <input class="form-control @error('form.name') is-invalid @enderror" id="ref-nama-simpan"
                               type="text" wire:model="form.name">
                        @error('form.name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="ref-kapasitas">{{ __('Mode kapasitas') }} <span class="wajib">*</span></label>
                        <select class="form-select" id="ref-kapasitas" wire:model="form.capacity_mode">
                            @foreach ($capacityModes as $nilai => $label)
                                <option value="{{ $nilai }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                @elseif ($tab === 'kendaraan')
                    <div class="col-md-3">
                        <label class="form-label" for="ref-plat">{{ __('Nomor polisi') }} <span class="wajib">*</span></label>
                        <input class="form-control @error('form.plate_no') is-invalid @enderror" id="ref-plat"
                               type="text" wire:model="form.plate_no">
                        @error('form.plate_no') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="ref-jenis-kendaraan">{{ __('Jenis kendaraan') }}</label>
                        <input class="form-control" id="ref-jenis-kendaraan" type="text" wire:model="form.type"
                               placeholder="{{ __('mis. Pick-up, Truk engkel') }}">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label" for="ref-driver">{{ __('Driver bawaan') }}</label>
                        <select class="form-select" id="ref-driver" wire:model="form.default_driver_id">
                            <option value="">{{ __('Belum ditentukan') }}</option>
                            @foreach ($drivers as $driver)
                                <option value="{{ $driver->id }}">{{ $driver->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <div class="col-md-6">
                        <label class="form-label" for="ref-nama-ekspedisi">{{ __('Nama ekspedisi') }} <span class="wajib">*</span></label>
                        <input class="form-control @error('form.name') is-invalid @enderror" id="ref-nama-ekspedisi"
                               type="text" wire:model="form.name">
                        @error('form.name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="ref-telepon">{{ __('Telepon') }}</label>
                        <input class="form-control" id="ref-telepon" type="text" wire:model="form.phone">
                    </div>
                @endif
            </div>
            <div class="card-footer d-flex gap-2">
                <button class="btn btn-primary" type="button" wire:click="simpan">{{ __('Simpan') }}</button>
                <button class="btn btn-outline-secondary" type="button" wire:click="batalForm">{{ __('Batal') }}</button>
            </div>
        </div>
    @endif

    @if ($tab === 'alasan')
        <div class="card mb-3">
            <div class="card-body">
                <label class="form-label" for="filter-konteks">{{ __('Konteks') }}</label>
                <select class="form-select" id="filter-konteks" wire:model.live="contextFilter">
                    <option value="">{{ __('Semua konteks') }}</option>
                    @foreach ($konteks as $nilai => $label)
                        <option value="{{ $nilai }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        @if ($tab === 'alasan')
                            <th>{{ __('Konteks') }}</th>
                            <th>{{ __('Kode') }}</th>
                            <th>{{ __('Label') }}</th>
                        @elseif ($tab === 'penyimpanan')
                            <th>{{ __('Kode') }}</th>
                            <th>{{ __('Nama') }}</th>
                            <th>{{ __('Mode kapasitas') }}</th>
                        @elseif ($tab === 'kendaraan')
                            <th>{{ __('Nomor polisi') }}</th>
                            <th>{{ __('Jenis') }}</th>
                            <th>{{ __('Driver bawaan') }}</th>
                        @else
                            <th>{{ __('Nama') }}</th>
                            <th>{{ __('Telepon') }}</th>
                            <th></th>
                        @endif
                        <th>{{ __('Status') }}</th>
                        <th class="text-end">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($baris as $item)
                        <tr wire:key="ref-{{ $tab }}-{{ $item->id }}">
                            @if ($tab === 'alasan')
                                <td>{{ $item->context->label() }}</td>
                                <td class="fw-semibold">{{ $item->code }}</td>
                                <td>{{ $item->label }}</td>
                            @elseif ($tab === 'penyimpanan')
                                <td class="fw-semibold">{{ $item->code }}</td>
                                <td>{{ $item->name }}</td>
                                <td>{{ $item->capacity_mode->label() }}</td>
                            @elseif ($tab === 'kendaraan')
                                <td class="fw-semibold">{{ $item->plate_no }}</td>
                                <td>{{ $item->type ?: '—' }}</td>
                                <td>{{ $item->defaultDriver?->name ?? '—' }}</td>
                            @else
                                <td class="fw-semibold">{{ $item->name }}</td>
                                <td>{{ $item->phone ?: '—' }}</td>
                                <td></td>
                            @endif

                            <td>
                                <span class="badge text-bg-{{ $item->is_active ? 'success' : 'secondary' }}">
                                    {{ $item->is_active ? __('Aktif') : __('Nonaktif') }}
                                </span>
                            </td>
                            <td class="text-end">
                                @can('update', $item)
                                    <button class="btn btn-sm btn-outline-secondary" type="button"
                                            wire:click="ubah({{ $item->id }})">{{ __('Ubah') }}</button>
                                @endcan
                                @if ($item->is_active)
                                    @can('deactivate', $item)
                                        <button class="btn btn-sm btn-outline-danger" type="button"
                                                wire:click="ubahAktif({{ $item->id }}, false)">
                                            {{ __('Nonaktifkan') }}
                                        </button>
                                    @endcan
                                @else
                                    @can('update', $item)
                                        <button class="btn btn-sm btn-outline-success" type="button"
                                                wire:click="ubahAktif({{ $item->id }}, true)">
                                            {{ __('Aktifkan') }}
                                        </button>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">{{ __('Belum ada data.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
