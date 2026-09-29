<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Gudang') }}</h1>
            <p class="text-muted mb-0">
                {{ __('Hierarki gudang. Gudang Site selalu terikat satu proyek, dan satu proyek boleh punya beberapa.') }}
            </p>
        </div>

        <div class="d-flex flex-wrap gap-2 align-items-center">
            {{-- A-323: pengalih Tabel | Denah (tersimpan di URL). --}}
            <div class="btn-group" role="group" aria-label="{{ __('Tampilan') }}">
                <button class="btn {{ $tampilan === 'tabel' ? 'btn-secondary' : 'btn-outline-secondary' }}" type="button" wire:click="$set('tampilan', 'tabel')" data-tampilan="tabel"><i class="bi bi-table"></i> {{ __('Tabel') }}</button>
                <button class="btn {{ $tampilan === 'denah' ? 'btn-secondary' : 'btn-outline-secondary' }}" type="button" wire:click="$set('tampilan', 'denah')" data-tampilan="denah"><i class="bi bi-map"></i> {{ __('Denah') }}</button>
            </div>
        @can('create', \App\Domain\Warehouse\Models\Warehouse::class)
            <div class="d-flex flex-wrap gap-2">
                {{-- A-272: impor banyak gudang sekaligus. --}}
                <a class="btn btn-outline-primary" href="{{ route('imports.index') }}#impor-warehouses">
                    <i class="bi bi-file-earmark-spreadsheet"></i> {{ __('Impor Excel') }}
                </a>
                <button class="btn btn-primary" type="button" wire:click="buat">
                    <i class="bi bi-plus-lg"></i> {{ __('Tambah gudang') }}
                </button>
            </div>
        @endcan
        </div>
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
                    <x-pilih model="form.parent_id" id="gudang-induk" :label="__('Gudang induk')" :kosong="__('Tidak ada induk')"
                             :options="$semua->reject(fn ($k) => $editingId === $k->id)->map(fn ($k) => ['value' => $k->id, 'text' => $k->code.' — '.$k->name])->values()->all()" />
                </div>

                @php($tipeTerpilih = $types->firstWhere('id', (int) ($form['warehouse_type_id'] ?: 0)))

                <div class="col-md-5">
                    <x-pilih model="form.project_id" id="gudang-proyek" server :label="__('Proyek')"
                             :wajib="$tipeTerpilih && $tipeTerpilih->code === 'SITE'" :kosong="__('Tanpa proyek')"
                             :disabled="! $tipeTerpilih || $tipeTerpilih->code !== 'SITE'" :options="$opsiProyek" />
                    <div class="form-text">{{ __('Hanya Gudang Site yang terikat proyek.') }}</div>
                </div>

                <div class="col-md-4">
                    <x-pilih model="form.head_user_id" id="gudang-kepala" server :label="__('Kepala gudang')"
                             :kosong="__('Belum ditentukan')" :options="$opsiKepala" />
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

    @if ($tampilan === 'denah')
        {{-- A-323: denah gudang terpilih langsung di halaman ini (hanya-lihat). --}}
        <div class="card mb-3" data-mode-denah>
            <div class="card-body d-flex flex-wrap align-items-end gap-2">
                <div>
                    <x-pilih model="gudangDenah" id="denah-gudang" live kecil :label="__('Gudang')" style="min-width: 18rem"
                             :options="$semua->where('is_active', true)->map(fn ($g) => ['value' => $g->id, 'text' => $g->code.' — '.$g->name])->values()->all()" />
                </div>
                @if ($denahGudang)
                    <a class="btn btn-sm btn-primary ms-auto" href="{{ route('warehouses.layout', $denahGudang) }}"><i class="bi bi-arrows-fullscreen"></i> {{ __('Buka denah penuh / Atur denah') }}</a>
                @endif
            </div>
        </div>
        @if ($denahGudang)
            @livewire('warehouse.warehouse-layout', ['warehouse' => $denahGudang, 'ringkas' => true], key('denah-'.$denahGudang->id))
        @else
            <div class="alert alert-info">{{ __('Belum ada gudang aktif.') }}</div>
        @endif
    @else
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
                            <td class="text-end text-nowrap">
                                {{-- A-323: tombol Denah per baris. --}}
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('warehouses.layout', $gudang) }}" data-denah="{{ $gudang->code }}"><i class="bi bi-map"></i> {{ __('Denah') }}</a>
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
    @endif
</div>
