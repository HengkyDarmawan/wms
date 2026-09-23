<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Struktur organisasi') }}</h1>
            <p class="text-muted mb-0">
                {{ __('Unit, jabatan, dan atasan langsung. Dipakai aturan approval "atasan langsung" dan "jabatan X di unit Y".') }}
            </p>
        </div>

        @can('create', \App\Domain\Access\Models\OrgUnit::class)
            <button class="btn btn-primary" type="button" wire:click="unitBaru">
                <i class="bi bi-plus-lg"></i> {{ __('Tambah unit') }}
            </button>
        @endcan
    </div>

    <div class="row g-3">
        {{-- ===== Pohon unit ===== --}}
        <div class="col-12 col-lg-4">
            <div class="card h-100">
                <div class="card-body">
                    <h2 class="h6 text-uppercase text-muted mb-3">{{ __('Unit') }}</h2>

                    @forelse ($pohon as $baris)
                        @php($unit = $baris['unit'])
                        <div class="d-flex align-items-center justify-content-between gap-2 py-1"
                             style="padding-left: {{ $baris['depth'] * 1.25 }}rem" wire:key="unit-{{ $unit->id }}">
                            <button class="btn btn-link p-0 text-start text-decoration-none {{ $selected?->id === $unit->id ? 'fw-bold' : '' }}"
                                    type="button" wire:click="pilihUnit({{ $unit->id }})">
                                <i class="bi bi-diagram-2 me-1 text-muted"></i>{{ $unit->name }}
                                @unless ($unit->is_active)
                                    <span class="badge text-bg-secondary ms-1">{{ __('Nonaktif') }}</span>
                                @endunless
                            </button>
                            <span class="small text-muted">{{ $unit->code }}</span>
                        </div>
                    @empty
                        <p class="text-muted mb-0">{{ __('Belum ada unit organisasi.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- ===== Detail unit terpilih ===== --}}
        <div class="col-12 col-lg-8">
            {{-- Form unit --}}
            @if ($formUnitTampil)
                <div class="card mb-3">
                    <div class="card-body">
                        <h2 class="h6 text-uppercase text-muted mb-3">
                            {{ $editingUnitId ? __('Ubah unit') : __('Unit baru') }}
                        </h2>

                        <form wire:submit="simpanUnit">
                            <div class="row g-2">
                                <div class="col-12 col-md-4">
                                    <label class="form-label" for="unitCode">
                                        {{ __('Kode') }} <span class="wajib">*</span>
                                    </label>
                                    <input class="form-control @error('unitCode') is-invalid @enderror" id="unitCode"
                                           type="text" wire:model="unitCode" maxlength="30" @disabled($editingUnitId)>
                                    @error('unitCode')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="col-12 col-md-4">
                                    <label class="form-label" for="unitName">
                                        {{ __('Nama') }} <span class="wajib">*</span>
                                    </label>
                                    <input class="form-control @error('unitName') is-invalid @enderror" id="unitName"
                                           type="text" wire:model="unitName" maxlength="100" required>
                                    @error('unitName')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="col-12 col-md-4">
                                    <label class="form-label" for="unitParentId">{{ __('Induk') }}</label>
                                    <select class="form-select @error('unitParentId') is-invalid @enderror"
                                            id="unitParentId" wire:model="unitParentId">
                                        <option value="">{{ __('— Tanpa induk —') }}</option>
                                        @foreach ($units as $calon)
                                            @continue($editingUnitId && $calon->id === $editingUnitId)
                                            <option value="{{ $calon->id }}">{{ $calon->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('unitParentId')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>

                            <div class="d-flex gap-2 mt-3">
                                <button class="btn btn-primary" type="submit">{{ __('Simpan unit') }}</button>
                                <button class="btn btn-outline-secondary" type="button" wire:click="tutupForm">
                                    {{ __('Batal') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif

            @if ($selected)
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                            <div>
                                <h2 class="h5 mb-1">{{ $selected->name }}</h2>
                                <p class="text-muted mb-0">
                                    {{ $selected->code }}
                                    @if ($selected->parent)
                                        · {{ __('di bawah') }} {{ $selected->parent->name }}
                                    @endif
                                </p>
                            </div>

                            @can('update', $selected)
                                <div class="d-flex gap-2">
                                    <button class="btn btn-sm btn-outline-secondary" type="button"
                                            wire:click="editUnit({{ $selected->id }})">{{ __('Ubah') }}</button>
                                    <button class="btn btn-sm btn-outline-secondary" type="button"
                                            wire:click="unitBaru({{ $selected->id }})">{{ __('Tambah sub-unit') }}</button>
                                    @if ($selected->is_active)
                                        <button class="btn btn-sm btn-outline-danger" type="button"
                                                wire:click="nonaktifkanUnit({{ $selected->id }})">{{ __('Nonaktifkan') }}</button>
                                    @else
                                        <button class="btn btn-sm btn-outline-success" type="button"
                                                wire:click="aktifkanUnit({{ $selected->id }})">{{ __('Aktifkan') }}</button>
                                    @endif
                                </div>
                            @endcan
                        </div>
                    </div>
                </div>

                {{-- Jabatan --}}
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h2 class="h6 text-uppercase text-muted mb-0">{{ __('Jabatan di unit ini') }}</h2>
                            @can('update', $selected)
                                <button class="btn btn-sm btn-outline-secondary" type="button" wire:click="jabatanBaru">
                                    <i class="bi bi-plus-lg"></i> {{ __('Tambah jabatan') }}
                                </button>
                            @endcan
                        </div>

                        @if ($formPositionTampil)
                            <form class="border rounded p-2 mb-3" wire:submit="simpanJabatan">
                                <div class="row g-2">
                                    <div class="col-12 col-md-4">
                                        <label class="form-label small" for="positionCode">
                                            {{ __('Kode') }} <span class="wajib">*</span>
                                        </label>
                                        <input class="form-control form-control-sm @error('positionCode') is-invalid @enderror"
                                               id="positionCode" type="text" wire:model="positionCode"
                                               maxlength="30" @disabled($editingPositionId)>
                                        @error('positionCode')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-12 col-md-5">
                                        <label class="form-label small" for="positionName">
                                            {{ __('Nama jabatan') }} <span class="wajib">*</span>
                                        </label>
                                        <input class="form-control form-control-sm @error('positionName') is-invalid @enderror"
                                               id="positionName" type="text" wire:model="positionName" maxlength="100" required>
                                        @error('positionName')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-12 col-md-3">
                                        <label class="form-label small" for="positionLevel">
                                            {{ __('Level') }} <span class="wajib">*</span>
                                        </label>
                                        <input class="form-control form-control-sm @error('positionLevel') is-invalid @enderror"
                                               id="positionLevel" type="number" min="1" max="99"
                                               wire:model="positionLevel" required>
                                        <div class="form-text">{{ __('1 = tertinggi') }}</div>
                                        @error('positionLevel')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>

                                <div class="d-flex gap-2 mt-2">
                                    <button class="btn btn-sm btn-primary" type="submit">{{ __('Simpan jabatan') }}</button>
                                    <button class="btn btn-sm btn-outline-secondary" type="button" wire:click="tutupForm">
                                        {{ __('Batal') }}
                                    </button>
                                </div>
                            </form>
                        @endif

                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>{{ __('Jabatan') }}</th>
                                        <th class="text-end">{{ __('Level') }}</th>
                                        <th>{{ __('Status') }}</th>
                                        <th class="text-end">{{ __('Aksi') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($positions as $position)
                                        <tr wire:key="pos-{{ $position->id }}">
                                            <td>
                                                <div class="fw-semibold">{{ $position->name }}</div>
                                                <div class="small text-muted">{{ $position->code }}</div>
                                            </td>
                                            <td class="text-end">{{ $position->level }}</td>
                                            <td>
                                                <span class="badge text-bg-{{ $position->is_active ? 'success' : 'secondary' }}">
                                                    {{ $position->is_active ? __('Aktif') : __('Nonaktif') }}
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                @can('update', $selected)
                                                    <button class="btn btn-sm btn-outline-secondary" type="button"
                                                            wire:click="editJabatan({{ $position->id }})">{{ __('Ubah') }}</button>
                                                    @if ($position->is_active)
                                                        <button class="btn btn-sm btn-outline-danger" type="button"
                                                                wire:click="nonaktifkanJabatan({{ $position->id }})">
                                                            {{ __('Nonaktifkan') }}
                                                        </button>
                                                    @else
                                                        <button class="btn btn-sm btn-outline-success" type="button"
                                                                wire:click="aktifkanJabatan({{ $position->id }})">
                                                            {{ __('Aktifkan') }}
                                                        </button>
                                                    @endif
                                                @endcan
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-3">
                                                {{ __('Belum ada jabatan di unit ini.') }}
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- Anggota --}}
                <div class="card">
                    <div class="card-body">
                        <h2 class="h6 text-uppercase text-muted mb-3">{{ __('User di unit ini') }}</h2>

                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>{{ __('Nama') }}</th>
                                        <th>{{ __('Jabatan') }}</th>
                                        <th>{{ __('Atasan langsung') }}</th>
                                        <th>{{ __('Status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($anggota as $orang)
                                        @php($statusOrang = $orang->status())
                                        <tr wire:key="anggota-{{ $orang->id }}">
                                            <td>
                                                @can('view', $orang)
                                                    <a class="nx-link" href="{{ route('users.show', $orang) }}">{{ $orang->name }}</a>
                                                @else
                                                    {{ $orang->name }}
                                                @endcan
                                            </td>
                                            <td class="small">{{ $orang->position?->name ?? '—' }}</td>
                                            <td class="small">
                                                {{ $orang->manager?->name ?? '—' }}
                                                @unless ($orang->manager)
                                                    <span class="badge text-bg-warning ms-1">{{ __('belum diisi') }}</span>
                                                @endunless
                                            </td>
                                            <td>
                                                <span class="badge text-bg-{{ $statusOrang->badge() }}">{{ $statusOrang->label() }}</span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-3">
                                                {{ __('Belum ada user di unit ini.') }}
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @else
                <div class="card">
                    <div class="card-body text-center text-muted py-5">
                        {{ __('Pilih unit di sebelah kiri, atau tambahkan unit pertama.') }}
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
