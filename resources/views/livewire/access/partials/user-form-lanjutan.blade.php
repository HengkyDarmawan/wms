{{-- ④ Pengaturan lanjutan (A-331): terlipat, terbuka sendiri bila ada isinya. --}}
<div class="card mb-3">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <button class="btn btn-link p-0 text-decoration-none" type="button"
                wire:click="$toggle('lanjutanTerbuka')" aria-expanded="{{ $lanjutanTerbuka ? 'true' : 'false' }}">
            <i class="bi {{ $lanjutanTerbuka ? 'bi-chevron-down' : 'bi-chevron-right' }}"></i>
            <strong>{{ __('4. Pengaturan lanjutan') }}</strong>
        </button>
        <span class="text-muted small">{{ __('Organisasi, atasan, dan peran tambahan.') }}</span>
    </div>

    @if ($lanjutanTerbuka)
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="orgUnitId">{{ __('Unit organisasi') }}</label>
                    <select class="form-select" id="orgUnitId" wire:model.live="orgUnitId">
                        <option value="">{{ __('— Tidak diisi —') }}</option>
                        @foreach ($units as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">{{ __('Disarankan otomatis dari peran yang dipilih.') }}</div>
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="positionId">{{ __('Jabatan') }}</label>
                    <select class="form-select" id="positionId" wire:model="positionId">
                        <option value="">{{ __('— Tidak diisi —') }}</option>
                        @foreach ($positions as $position)
                            <option value="{{ $position->id }}">{{ $position->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="managerId">{{ __('Atasan langsung') }}</label>
                    <select class="form-select @error('managerId') is-invalid @enderror" id="managerId" wire:model="managerId">
                        <option value="">{{ __('— Tidak diisi —') }}</option>
                        @foreach ($managers as $manager)
                            <option value="{{ $manager->id }}">{{ $manager->name }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">{{ __('Dipakai approval "atasan langsung" dan pemisahan tugas.') }}</div>
                    @error('managerId')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            @unless ($penugasanSiteSaja)
                <hr>

                <h3 class="h6 text-uppercase text-muted">{{ __('Peran lain') }}</h3>
                <p class="small text-muted">
                    {{ __('Hanya bila orang ini memegang lebih dari satu peran. Cakupan "Semua" tidak untuk peran Klien.') }}
                </p>

                @error('assignments')<div class="alert alert-danger py-2 small">{{ $message }}</div>@enderror

                @foreach ($assignments as $index => $assignment)
                    <div class="border rounded p-2 mb-2" wire:key="assignment-{{ $index }}">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label small" for="role-{{ $index }}">
                                    {{ __('Peran') }} <span class="wajib">*</span>
                                </label>
                                <select class="form-select form-select-sm @error('assignments.'.$index.'.role_id') is-invalid @enderror"
                                        id="role-{{ $index }}" wire:model="assignments.{{ $index }}.role_id">
                                    <option value="">{{ __('— pilih peran —') }}</option>
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->id }}">{{ $role->name }}</option>
                                    @endforeach
                                </select>
                                @error('assignments.'.$index.'.role_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-3">
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

                            <div class="col-md-4">
                                @if (($assignment['scope_type'] ?? '') === 'project')
                                    <label class="form-label small" for="scopeid-{{ $index }}">{{ __('Proyek') }}</label>
                                    <select class="form-select form-select-sm @error('assignments.'.$index.'.scope_id') is-invalid @enderror"
                                            id="scopeid-{{ $index }}" wire:model="assignments.{{ $index }}.scope_id">
                                        <option value="">{{ __('— pilih proyek —') }}</option>
                                        @foreach ($projects as $project)
                                            <option value="{{ $project->id }}">{{ $project->name }}</option>
                                        @endforeach
                                    </select>
                                @elseif (($assignment['scope_type'] ?? '') === 'warehouse')
                                    <label class="form-label small" for="scopeid-{{ $index }}">{{ __('Gudang') }}</label>
                                    <select class="form-select form-select-sm @error('assignments.'.$index.'.scope_id') is-invalid @enderror"
                                            id="scopeid-{{ $index }}" wire:model="assignments.{{ $index }}.scope_id">
                                        <option value="">{{ __('— pilih gudang —') }}</option>
                                        @foreach ($warehouses as $warehouse)
                                            <option value="{{ $warehouse->id }}">{{ $warehouse->code }} — {{ $warehouse->name }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    {{-- Cakupan `all` tidak memakai id sama sekali. --}}
                                    <label class="form-label small" for="scopeid-{{ $index }}">{{ __('Cakupan') }}</label>
                                    <input class="form-control form-control-sm" id="scopeid-{{ $index }}" type="text"
                                           value="{{ __('Seluruh company') }}" disabled>
                                @endif
                                @error('assignments.'.$index.'.scope_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-1 text-end">
                                <button class="btn btn-sm btn-link text-danger p-0" type="button"
                                        wire:click="removeAssignment({{ $index }})">{{ __('Hapus') }}</button>
                            </div>
                        </div>

                        @if (($assignment['valid_from'] ?? null) || ($assignment['valid_until'] ?? null))
                            <div class="small text-warning-emphasis mt-1">
                                <i class="bi bi-exclamation-triangle"></i>
                                {{ __('Penugasan bertanggal lama (:dari s/d :sampai); masih ditegakkan dan tidak diubah dari sini.', [
                                    'dari' => $assignment['valid_from'] ?? '—',
                                    'sampai' => $assignment['valid_until'] ?? '—',
                                ]) }}
                            </div>
                        @endif
                    </div>
                @endforeach

                <button class="btn btn-sm btn-outline-secondary" type="button" wire:click="addAssignment">
                    <i class="bi bi-plus-lg"></i> {{ __('Tambah peran lain') }}
                </button>

                <p class="small text-muted mt-2 mb-0">
                    {{ __('Isian "Berlaku dari / sampai" sudah tidak ada di sini. Akun dan peran berlaku sampai dinonaktifkan; penempatan berbatas waktu diatur lewat Proyek › tab Tim site.') }}
                </p>
            @endunless
        </div>
    @endif
</div>
