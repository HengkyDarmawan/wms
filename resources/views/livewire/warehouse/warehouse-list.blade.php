<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Gudang') }}</h1>
            <p class="text-muted mb-0">
                {{ __('Hierarki gudang. Gudang Site selalu terikat satu proyek, dan satu proyek boleh punya beberapa.') }}
            </p>
        </div>

        @can('create', \App\Domain\Warehouse\Models\Warehouse::class)
            <button class="btn btn-primary" type="button" wire:click="buat">
                <i class="bi bi-plus-lg"></i> {{ __('Tambah gudang') }}
            </button>
        @endcan
    </div>

    @if ($ruleError !== '')
        <div class="alert alert-danger" role="alert">{{ $ruleError }}</div>
    @endif

    @if ($showForm)
        <div class="card mb-3">
            <div class="card-header">
                <strong>{{ $editingId ? __('Ubah gudang') : __('Gudang baru') }}</strong>
                <span class="text-muted small ms-2">{{ __('Field bertanda * wajib diisi.') }}</span>
            </div>
            <div class="card-body row g-3">
                <div class="col-md-2">
                    <label class="form-label" for="gudang-kode">{{ __('Kode') }} <span class="wajib">*</span></label>
                    <input class="form-control @error('form.code') is-invalid @enderror" id="gudang-kode"
                           type="text" wire:model="form.code" @disabled($editingId !== null)>
                    @error('form.code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    @if ($editingId)
                        <div class="form-text">{{ __('Kode dipakai di nomor dokumen, jadi tidak bisa diubah.') }}</div>
                    @endif
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="gudang-nama">{{ __('Nama gudang') }} <span class="wajib">*</span></label>
                    <input class="form-control @error('form.name') is-invalid @enderror" id="gudang-nama"
                           type="text" wire:model="form.name">
                    @error('form.name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-3">
                    <label class="form-label" for="gudang-tipe">{{ __('Tipe gudang') }} <span class="wajib">*</span></label>
                    <select class="form-select @error('form.warehouse_type_id') is-invalid @enderror" id="gudang-tipe"
                            wire:model.live="form.warehouse_type_id">
                        <option value="">{{ __('Pilih tipe…') }}</option>
                        @foreach ($types as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </select>
                    @error('form.warehouse_type_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-3">
                    <label class="form-label" for="gudang-induk">{{ __('Gudang induk') }}</label>
                    <select class="form-select @error('form.parent_id') is-invalid @enderror" id="gudang-induk"
                            wire:model="form.parent_id">
                        <option value="">{{ __('Tidak ada induk') }}</option>
                        @foreach ($semua as $kandidat)
                            @continue($editingId === $kandidat->id)
                            <option value="{{ $kandidat->id }}">{{ $kandidat->code }} — {{ $kandidat->name }}</option>
                        @endforeach
                    </select>
                    @error('form.parent_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                @php($tipeTerpilih = $types->firstWhere('id', (int) ($form['warehouse_type_id'] ?: 0)))

                <div class="col-md-5">
                    <label class="form-label" for="gudang-proyek">
                        {{ __('Proyek') }}
                        @if ($tipeTerpilih && $tipeTerpilih->code === 'SITE') <span class="wajib">*</span> @endif
                    </label>
                    <select class="form-select @error('form.project_id') is-invalid @enderror" id="gudang-proyek"
                            wire:model="form.project_id"
                            @disabled(! $tipeTerpilih || $tipeTerpilih->code !== 'SITE')>
                        <option value="">{{ __('Tanpa proyek') }}</option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}">{{ $project->code }} — {{ $project->name }}</option>
                        @endforeach
                    </select>
                    @error('form.project_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <div class="form-text">{{ __('Hanya Gudang Site yang terikat proyek.') }}</div>
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="gudang-kepala">{{ __('Kepala gudang') }}</label>
                    <select class="form-select" id="gudang-kepala" wire:model="form.head_user_id">
                        <option value="">{{ __('Belum ditentukan') }}</option>
                        @foreach ($heads as $head)
                            <option value="{{ $head->id }}">{{ $head->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label" for="gudang-alamat">{{ __('Alamat') }}</label>
                    <textarea class="form-control" id="gudang-alamat" rows="2" wire:model="form.address"></textarea>
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
            <div class="card-header text-danger"><strong>{{ __('Nonaktifkan gudang') }}</strong></div>
            <div class="card-body row g-3">
                <div class="col-md-5">
                    <label class="form-label" for="gudang-alasan">{{ __('Alasan') }} <span class="wajib">*</span></label>
                    <select class="form-select @error('reasonCode') is-invalid @enderror" id="gudang-alasan"
                            wire:model="reasonCode">
                        <option value="">{{ __('Pilih alasan…') }}</option>
                        @foreach ($alasan as $kode => $label)
                            <option value="{{ $kode }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('reasonCode') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-7">
                    <label class="form-label" for="gudang-keterangan">{{ __('Keterangan') }}</label>
                    <input class="form-control" id="gudang-keterangan" type="text" wire:model="reasonNotes"
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
                <label class="form-label" for="cari-gudang">{{ __('Cari gudang') }}</label>
                <input class="form-control" id="cari-gudang" type="search"
                       wire:model.live.debounce.400ms="search" placeholder="{{ __('Nama atau kode gudang…') }}">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="filter-tipe-gudang">{{ __('Tipe') }}</label>
                <select class="form-select" id="filter-tipe-gudang" wire:model.live="typeFilter">
                    <option value="">{{ __('Semua tipe') }}</option>
                    @foreach ($types as $type)
                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="filter-status-gudang">{{ __('Status') }}</label>
                <select class="form-select" id="filter-status-gudang" wire:model.live="statusFilter">
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
                        <th>{{ __('Gudang') }}</th>
                        <th>{{ __('Tipe') }}</th>
                        <th>{{ __('Proyek') }}</th>
                        <th>{{ __('Kepala gudang') }}</th>
                        <th class="text-end">{{ __('Zona') }}</th>
                        <th class="text-end">{{ __('Bin') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-end">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pohon as $baris)
                        @php($gudang = $baris['gudang'])
                        <tr wire:key="gudang-{{ $gudang->id }}">
                            <td>
                                <span style="padding-left: {{ $baris['level'] * 20 }}px">
                                    @if ($baris['level'] > 0)<i class="bi bi-arrow-return-right text-muted"></i>@endif
                                    <a class="fw-semibold text-decoration-none"
                                       href="{{ route('warehouses.show', $gudang) }}">{{ $gudang->name }}</a>
                                </span>
                                <div class="small text-muted" style="padding-left: {{ $baris['level'] * 20 }}px">
                                    {{ $gudang->code }}
                                </div>
                            </td>
                            <td>{{ $gudang->type?->name ?? '—' }}</td>
                            <td>{{ $gudang->project?->code ?? '—' }}</td>
                            <td>{{ $gudang->head?->name ?? '—' }}</td>
                            <td class="text-end">{{ $gudang->zones_count }}</td>
                            <td class="text-end">{{ $gudang->bins_count }}</td>
                            <td>
                                <span class="badge text-bg-{{ $gudang->is_active ? 'success' : 'secondary' }}">
                                    {{ $gudang->is_active ? __('Aktif') : __('Nonaktif') }}
                                </span>
                            </td>
                            <td class="text-end">
                                @can('update', $gudang)
                                    <button class="btn btn-sm btn-outline-secondary" type="button"
                                            wire:click="ubah({{ $gudang->id }})">{{ __('Ubah') }}</button>
                                @endcan

                                @if ($gudang->is_active)
                                    @can('deactivate', $gudang)
                                        <button class="btn btn-sm btn-outline-danger" type="button"
                                                wire:click="mintaNonaktif({{ $gudang->id }})">
                                            {{ __('Nonaktifkan') }}
                                        </button>
                                    @endcan
                                @else
                                    @can('update', $gudang)
                                        <button class="btn btn-sm btn-outline-success" type="button"
                                                wire:click="aktifkan({{ $gudang->id }})">{{ __('Aktifkan') }}</button>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">{{ __('Belum ada gudang.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
