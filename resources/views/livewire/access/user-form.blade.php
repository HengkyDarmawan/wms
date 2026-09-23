<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ $user ? __('Ubah pengguna') : __('Tambah pengguna') }}</h1>
            <p class="text-muted mb-0">
                {{ __('Field bertanda') }} <span class="wajib">*</span> {{ __('wajib diisi.') }}
            </p>
        </div>
        <a class="btn btn-outline-secondary" href="{{ route('users.index') }}">{{ __('Kembali ke daftar') }}</a>
    </div>

    <form wire:submit="save">
        <div class="row g-3">
            {{-- ===== Data diri ===== --}}
            <div class="col-12 col-xl-5">
                <div class="card h-100">
                    <div class="card-body">
                        <h2 class="h6 text-uppercase text-muted mb-3">{{ __('Data diri') }}</h2>

                        <div class="mb-3">
                            <label class="form-label" for="name">{{ __('Nama') }} <span class="wajib">*</span></label>
                            <input class="form-control @error('name') is-invalid @enderror" id="name" type="text"
                                   wire:model="name" maxlength="100" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="email">{{ __('Email') }} <span class="wajib">*</span></label>
                            <input class="form-control @error('email') is-invalid @enderror" id="email" type="email"
                                   wire:model="email" maxlength="150" required @disabled($emailTerkunci)>
                            @if ($emailTerkunci)
                                <div class="form-text">{{ __('Email tidak bisa diubah setelah undangan diterima.') }}</div>
                            @endif
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="phone">{{ __('Nomor WhatsApp') }}</label>
                            <input class="form-control @error('phone') is-invalid @enderror" id="phone" type="text"
                                   wire:model="phone" maxlength="20" placeholder="+62812…">
                            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="clientId">{{ __('Klien (untuk user portal)') }}</label>
                            <input class="form-control @error('clientId') is-invalid @enderror" id="clientId"
                                   type="number" min="1" wire:model="clientId">
                            <div class="form-text">
                                {{ __('Isi hanya untuk user klien; wajib dipasangkan dengan role Klien. Daftar klien tersedia setelah modul Master.') }}
                            </div>
                            @error('clientId')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        @if (! $user)
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="sendInvitation"
                                       wire:model="sendInvitation">
                                <label class="form-check-label" for="sendInvitation">
                                    {{ __('Kirim undangan agar user mengatur password sendiri') }}
                                </label>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- ===== Organisasi ===== --}}
            <div class="col-12 col-xl-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h2 class="h6 text-uppercase text-muted mb-3">{{ __('Organisasi') }}</h2>

                        <div class="mb-3">
                            <label class="form-label" for="orgUnitId">{{ __('Unit') }}</label>
                            <select class="form-select" id="orgUnitId" wire:model.live="orgUnitId">
                                <option value="">{{ __('— Tidak diisi —') }}</option>
                                @foreach ($units as $unit)
                                    <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="positionId">{{ __('Jabatan') }}</label>
                            <select class="form-select" id="positionId" wire:model="positionId">
                                <option value="">{{ __('— Tidak diisi —') }}</option>
                                @foreach ($positions as $position)
                                    <option value="{{ $position->id }}">{{ $position->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-0">
                            <label class="form-label" for="managerId">{{ __('Atasan langsung') }}</label>
                            <select class="form-select @error('managerId') is-invalid @enderror" id="managerId"
                                    wire:model="managerId">
                                <option value="">{{ __('— Tidak diisi —') }}</option>
                                @foreach ($managers as $manager)
                                    <option value="{{ $manager->id }}">{{ $manager->name }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">{{ __('Dipakai approval "atasan langsung" dan pemisahan tugas.') }}</div>
                            @error('managerId')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- ===== Penugasan role ===== --}}
            <div class="col-12 col-xl-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h2 class="h6 text-uppercase text-muted mb-1">
                            {{ __('Penugasan role') }} <span class="wajib">*</span>
                        </h2>
                        <p class="small text-muted">
                            {{ __('Minimal satu penugasan yang berlaku. Cakupan "Semua" hanya untuk role internal.') }}
                        </p>

                        @error('assignments')<div class="alert alert-danger py-2 small">{{ $message }}</div>@enderror

                        @foreach ($assignments as $index => $assignment)
                            <div class="border rounded p-2 mb-2" wire:key="assignment-{{ $index }}">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="small text-muted">{{ __('Penugasan') }} #{{ $index + 1 }}</span>
                                    @if (count($assignments) > 1)
                                        <button class="btn btn-sm btn-link text-danger p-0" type="button"
                                                wire:click="removeAssignment({{ $index }})">
                                            {{ __('Hapus') }}
                                        </button>
                                    @endif
                                </div>

                                <div class="mb-2">
                                    <label class="form-label small" for="role-{{ $index }}">
                                        {{ __('Role') }} <span class="wajib">*</span>
                                    </label>
                                    <select class="form-select form-select-sm @error('assignments.'.$index.'.role_id') is-invalid @enderror"
                                            id="role-{{ $index }}" wire:model="assignments.{{ $index }}.role_id">
                                        <option value="">{{ __('— Pilih role —') }}</option>
                                        @foreach ($roles as $role)
                                            <option value="{{ $role->id }}">
                                                {{ $role->name }}{{ $role->is_client_role ? ' ('.__('klien').')' : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('assignments.'.$index.'.role_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="row g-2">
                                    <div class="col-7">
                                        <label class="form-label small" for="scope-{{ $index }}">
                                            {{ __('Cakupan') }} <span class="wajib">*</span>
                                        </label>
                                        <select class="form-select form-select-sm" id="scope-{{ $index }}"
                                                wire:model.live="assignments.{{ $index }}.scope_type">
                                            @foreach ($scopeTypes as $scope)
                                                <option value="{{ $scope->value }}">{{ $scope->label() }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-5">
                                        @if (($assignment['scope_type'] ?? '') === 'project')
                                            <label class="form-label small" for="scopeid-{{ $index }}">
                                                {{ __('Proyek') }} <span class="wajib">*</span>
                                            </label>
                                            <select class="form-select form-select-sm @error('assignments.'.$index.'.scope_id') is-invalid @enderror"
                                                    id="scopeid-{{ $index }}"
                                                    wire:model="assignments.{{ $index }}.scope_id">
                                                <option value="">{{ __('— Pilih proyek —') }}</option>
                                                @foreach ($projects as $project)
                                                    <option value="{{ $project->id }}">{{ $project->name }}</option>
                                                @endforeach
                                            </select>
                                        @elseif (($assignment['scope_type'] ?? '') === 'warehouse')
                                            <label class="form-label small" for="scopeid-{{ $index }}">
                                                {{ __('Gudang') }} <span class="wajib">*</span>
                                            </label>
                                            <select class="form-select form-select-sm @error('assignments.'.$index.'.scope_id') is-invalid @enderror"
                                                    id="scopeid-{{ $index }}"
                                                    wire:model="assignments.{{ $index }}.scope_id">
                                                <option value="">{{ __('— Pilih gudang —') }}</option>
                                                @foreach ($warehouses as $warehouse)
                                                    <option value="{{ $warehouse->id }}">
                                                        {{ $warehouse->code }} — {{ $warehouse->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        @else
                                            {{-- Cakupan `all` tidak memakai id sama sekali. --}}
                                            <label class="form-label small" for="scopeid-{{ $index }}">{{ __('Cakupan') }}</label>
                                            <input class="form-control form-control-sm" id="scopeid-{{ $index }}"
                                                   type="text" value="{{ __('Seluruh company') }}" disabled>
                                        @endif
                                        @error('assignments.'.$index.'.scope_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="row g-2 mt-1">
                                    <div class="col-6">
                                        <label class="form-label small" for="from-{{ $index }}">{{ __('Berlaku dari') }}</label>
                                        <input class="form-control form-control-sm" id="from-{{ $index }}" type="date"
                                               wire:model="assignments.{{ $index }}.valid_from">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small" for="until-{{ $index }}">{{ __('Sampai') }}</label>
                                        <input class="form-control form-control-sm @error('assignments.'.$index.'.valid_until') is-invalid @enderror"
                                               id="until-{{ $index }}" type="date"
                                               wire:model="assignments.{{ $index }}.valid_until">
                                        @error('assignments.'.$index.'.valid_until')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        @endforeach

                        <button class="btn btn-sm btn-outline-secondary w-100" type="button" wire:click="addAssignment">
                            <i class="bi bi-plus-lg"></i> {{ __('Tambah penugasan') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2 mt-3">
            <button class="btn btn-primary" type="submit">
                <span wire:loading.remove wire:target="save">{{ __('Simpan') }}</span>
                <span wire:loading wire:target="save">{{ __('Menyimpan…') }}</span>
            </button>
            <a class="btn btn-outline-secondary" href="{{ route('users.index') }}">{{ __('Batal') }}</a>
        </div>
    </form>
</div>
