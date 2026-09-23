<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Pengguna') }}</h1>
            <p class="text-muted mb-0">{{ __('Hak akses melekat pada penugasan role × cakupan, bukan pada user.') }}</p>
        </div>

        @can('create', \App\Domain\Access\Models\User::class)
            <a class="btn btn-primary" href="{{ route('users.create') }}">
                <i class="bi bi-plus-lg"></i> {{ __('Tambah pengguna') }}
            </a>
        @endcan
    </div>

    {{-- ===== Filter ===== --}}
    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-2">
                <div class="col-12 col-lg-4">
                    <label class="form-label" for="f-cari">{{ __('Cari nama atau email') }}</label>
                    <input class="form-control" id="f-cari" type="search" wire:model.live.debounce.400ms="search"
                           placeholder="{{ __('Ketik untuk mencari…') }}">
                </div>

                <div class="col-6 col-lg-2">
                    <label class="form-label" for="f-role">{{ __('Role') }}</label>
                    <select class="form-select" id="f-role" wire:model.live="roleFilter">
                        <option value="">{{ __('Semua') }}</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}">{{ $role->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-lg-2">
                    <label class="form-label" for="f-status">{{ __('Status') }}</label>
                    <select class="form-select" id="f-status" wire:model.live="statusFilter">
                        <option value="">{{ __('Semua') }}</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}">{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-lg-2">
                    <label class="form-label" for="f-unit">{{ __('Unit') }}</label>
                    <select class="form-select" id="f-unit" wire:model.live="unitFilter">
                        <option value="">{{ __('Semua') }}</option>
                        @foreach ($units as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-lg-2">
                    <label class="form-label" for="f-scope">{{ __('Cakupan') }}</label>
                    <div class="input-group">
                        <select class="form-select" id="f-scope" wire:model.live="scopeTypeFilter">
                            <option value="">{{ __('Semua') }}</option>
                            @foreach ($scopeTypes as $scope)
                                <option value="{{ $scope->value }}">{{ $scope->label() }}</option>
                            @endforeach
                        </select>
                        <input class="form-control" type="number" min="1" style="max-width:6rem"
                               wire:model.live.debounce.400ms="scopeIdFilter"
                               placeholder="ID" aria-label="{{ __('ID gudang atau proyek') }}"
                               @disabled($scopeTypeFilter === '' || $scopeTypeFilter === 'all')>
                    </div>
                </div>
            </div>

            <div class="mt-2">
                <button class="btn btn-link btn-sm p-0 nx-link" type="button" wire:click="resetFilters">
                    {{ __('Bersihkan filter') }}
                </button>
            </div>
        </div>
    </div>

    {{-- ===== Daftar ===== --}}
    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Nama') }}</th>
                        <th>{{ __('Role & cakupan') }}</th>
                        <th>{{ __('Unit / jabatan') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Login terakhir') }}</th>
                        <th class="text-end">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        @php($status = $user->status())
                        <tr wire:key="user-{{ $user->id }}">
                            <td>
                                <a class="fw-semibold nx-link" href="{{ route('users.show', $user) }}">{{ $user->name }}</a>
                                <div class="small text-muted">{{ $user->email }}</div>
                            </td>
                            <td>
                                @forelse ($user->roleAssignments->filter->isValid() as $assignment)
                                    <span class="badge text-bg-light me-1 mb-1">
                                        {{ $assignment->role?->name }} · {{ $assignment->describeScope() }}
                                    </span>
                                @empty
                                    <span class="small text-danger">{{ __('Belum ada penugasan') }}</span>
                                @endforelse
                            </td>
                            <td class="small">
                                {{ $user->orgUnit?->name ?? '—' }}
                                <div class="text-muted">{{ $user->position?->name }}</div>
                            </td>
                            <td><span class="badge text-bg-{{ $status->badge() }}">{{ $status->label() }}</span></td>
                            <td class="small text-muted">
                                {{ $user->last_login_at?->timezone(tenant()?->timezone ?? 'Asia/Jakarta')->format('d M Y H:i') ?? '—' }}
                            </td>
                            <td class="text-end">
                                <div class="dropdown">
                                    <button class="nx-icon-btn" type="button" data-bs-toggle="dropdown"
                                            aria-expanded="false" aria-label="{{ __('Aksi untuk :nama', ['nama' => $user->name]) }}">
                                        <i class="bi bi-three-dots"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item" href="{{ route('users.show', $user) }}">
                                            <i class="bi bi-eye"></i> {{ __('Lihat') }}
                                        </a>
                                        @can('update', $user)
                                            <a class="dropdown-item" href="{{ route('users.edit', $user) }}">
                                                <i class="bi bi-pencil"></i> {{ __('Ubah') }}
                                            </a>
                                        @endcan
                                        @can('invite', \App\Domain\Access\Models\User::class)
                                            @if ($status === \App\Domain\Access\Enums\UserStatus::Invited)
                                                <button class="dropdown-item" type="button"
                                                        wire:click="resendInvitation({{ $user->id }})">
                                                    <i class="bi bi-envelope"></i> {{ __('Undang ulang') }}
                                                </button>
                                            @endif
                                        @endcan
                                        @can('resetPassword', $user)
                                            <button class="dropdown-item" type="button"
                                                    wire:click="sendPasswordReset({{ $user->id }})">
                                                <i class="bi bi-key"></i> {{ __('Kirim tautan password') }}
                                            </button>
                                        @endcan
                                        @can('deactivate', $user)
                                            <div class="dropdown-divider"></div>
                                            @if ($user->is_active)
                                                <button class="dropdown-item text-danger" type="button"
                                                        wire:click="confirmDeactivate({{ $user->id }})">
                                                    <i class="bi bi-person-dash"></i> {{ __('Nonaktifkan') }}
                                                </button>
                                            @else
                                                <button class="dropdown-item" type="button"
                                                        wire:click="reactivate({{ $user->id }})">
                                                    <i class="bi bi-person-check"></i> {{ __('Aktifkan kembali') }}
                                                </button>
                                            @endif
                                        @endcan
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                {{ __('Tidak ada pengguna yang cocok dengan filter.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($users->hasPages())
            <div class="card-body border-top">{{ $users->links() }}</div>
        @endif
    </div>

    {{-- ===== Dialog nonaktifkan (BR-GEN-11: Alasan wajib, Keterangan opsional) ===== --}}
    @if ($deactivatingId)
        @php($target = \App\Domain\Access\Models\User::find($deactivatingId))
        <div class="modal d-block" tabindex="-1" role="dialog" style="background:rgba(0,0,0,.45)">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title h5">{{ __('Nonaktifkan pengguna') }}</h2>
                        <button class="btn-close" type="button" wire:click="cancelDeactivate"
                                aria-label="{{ __('Tutup') }}"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted">
                            {{ __('Pengguna :nama tidak dihapus; datanya tetap tersimpan untuk jejak dokumen.', ['nama' => $target?->name]) }}
                        </p>

                        <div class="mb-3">
                            <label class="form-label" for="alasan">{{ __('Alasan') }} <span class="wajib">*</span></label>
                            <input class="form-control @error('deactivateReason') is-invalid @enderror"
                                   id="alasan" type="text" wire:model="deactivateReason" maxlength="255">
                            @error('deactivateReason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-0">
                            <label class="form-label" for="keterangan">{{ __('Keterangan') }}</label>
                            <textarea class="form-control" id="keterangan" rows="2"
                                      wire:model="deactivateNotes" maxlength="500"></textarea>
                            <div class="form-text">{{ __('Opsional.') }}</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" type="button" wire:click="cancelDeactivate">
                            {{ __('Batal') }}
                        </button>
                        <button class="btn btn-danger" type="button" wire:click="deactivate">
                            {{ __('Nonaktifkan') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
