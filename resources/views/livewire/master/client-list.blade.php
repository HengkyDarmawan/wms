<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Klien') }}</h1>
            <p class="text-muted mb-0">
                {{ __('Pemilik proyek. Klien tidak pernah dihapus, hanya dinonaktifkan.') }}
            </p>
        </div>

        @can('create', \App\Domain\Master\Models\Client::class)
            <button class="btn btn-primary" type="button" wire:click="buat">
                <i class="bi bi-plus-lg"></i> {{ __('Tambah klien') }}
            </button>
        @endcan
    </div>

    @if ($ruleError !== '')
        <div class="alert alert-danger" role="alert">{{ $ruleError }}</div>
    @endif

    @if ($showForm)
        <div class="card mb-3">
            <div class="card-header">
                <strong>{{ $editingId ? __('Ubah klien') : __('Klien baru') }}</strong>
                <span class="text-muted small ms-2">{{ __('Field bertanda * wajib diisi.') }}</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label" for="klien-kode">{{ __('Kode') }} <span class="wajib">*</span></label>
                        <input class="form-control @error('form.code') is-invalid @enderror" id="klien-kode"
                               type="text" wire:model="form.code" @disabled($editingId !== null)>
                        @error('form.code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        @if ($editingId)
                            <div class="form-text">{{ __('Kode tidak bisa diubah setelah klien dibuat.') }}</div>
                        @endif
                    </div>

                    <div class="col-md-5">
                        <label class="form-label" for="klien-nama">{{ __('Nama klien') }} <span class="wajib">*</span></label>
                        <input class="form-control @error('form.name') is-invalid @enderror" id="klien-nama"
                               type="text" wire:model="form.name">
                        @error('form.name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="klien-npwp">{{ __('NPWP') }}</label>
                        <input class="form-control" id="klien-npwp" type="text" wire:model="form.tax_id">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="klien-kontak">{{ __('Nama kontak') }}</label>
                        <input class="form-control" id="klien-kontak" type="text" wire:model="form.contact_name">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="klien-telepon">{{ __('Telepon') }}</label>
                        <input class="form-control" id="klien-telepon" type="text" wire:model="form.phone">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="klien-email">{{ __('Email') }}</label>
                        <input class="form-control @error('form.email') is-invalid @enderror" id="klien-email"
                               type="email" wire:model="form.email">
                        @error('form.email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="klien-alamat">{{ __('Alamat') }}</label>
                        <textarea class="form-control" id="klien-alamat" rows="2" wire:model="form.address"></textarea>
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
            <div class="card-header text-danger"><strong>{{ __('Nonaktifkan klien') }}</strong></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label" for="klien-alasan">{{ __('Alasan') }} <span class="wajib">*</span></label>
                        <select class="form-select @error('reasonCode') is-invalid @enderror" id="klien-alasan"
                                wire:model="reasonCode">
                            <option value="">{{ __('Pilih alasan…') }}</option>
                            @foreach ($alasan as $kode => $label)
                                <option value="{{ $kode }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('reasonCode') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-7">
                        <label class="form-label" for="klien-keterangan">{{ __('Keterangan') }}</label>
                        <input class="form-control" id="klien-keterangan" type="text" wire:model="reasonNotes"
                               placeholder="{{ __('Opsional') }}">
                    </div>
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
            <div class="col-md-8">
                <label class="form-label" for="cari-klien">{{ __('Cari klien') }}</label>
                <input class="form-control" id="cari-klien" type="search"
                       wire:model.live.debounce.400ms="search" placeholder="{{ __('Nama, kode, atau kontak…') }}">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="filter-status-klien">{{ __('Status') }}</label>
                <select class="form-select" id="filter-status-klien" wire:model.live="statusFilter">
                    <option value="">{{ __('Semua') }}</option>
                    <option value="aktif">{{ __('Aktif') }}</option>
                    <option value="nonaktif">{{ __('Nonaktif') }}</option>
                </select>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Klien') }}</th>
                        <th>{{ __('Kontak') }}</th>
                        <th class="text-end">{{ __('Proyek aktif') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-end">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($clients as $client)
                        <tr wire:key="klien-{{ $client->id }}">
                            <td>
                                <div class="fw-semibold">{{ $client->name }}</div>
                                <div class="small text-muted">{{ $client->code }}</div>
                            </td>
                            <td>
                                <div>{{ $client->contact_name ?: '—' }}</div>
                                <div class="small text-muted">{{ $client->phone ?: $client->email ?: '—' }}</div>
                            </td>
                            <td class="text-end">{{ $client->active_projects_count }}</td>
                            <td>
                                <span class="badge text-bg-{{ $client->is_active ? 'success' : 'secondary' }}">
                                    {{ $client->is_active ? __('Aktif') : __('Nonaktif') }}
                                </span>
                            </td>
                            <td class="text-end">
                                @can('update', $client)
                                    <button class="btn btn-sm btn-outline-secondary" type="button"
                                            wire:click="ubah({{ $client->id }})">{{ __('Ubah') }}</button>
                                @endcan

                                @if ($client->is_active)
                                    @can('deactivate', $client)
                                        <button class="btn btn-sm btn-outline-danger" type="button"
                                                wire:click="mintaNonaktif({{ $client->id }})">
                                            {{ __('Nonaktifkan') }}
                                        </button>
                                    @endcan
                                @else
                                    @can('update', $client)
                                        <button class="btn btn-sm btn-outline-success" type="button"
                                                wire:click="aktifkan({{ $client->id }})">{{ __('Aktifkan') }}</button>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">{{ __('Belum ada klien.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($clients->hasPages())
            <div class="card-footer">{{ $clients->links() }}</div>
        @endif
    </div>
</div>
