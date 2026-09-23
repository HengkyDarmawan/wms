<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Role') }}</h1>
            <p class="text-muted mb-0">
                {{ __('Role bawaan bisa diubah permission-nya, tetapi tidak bisa dihapus atau dinonaktifkan.') }}
            </p>
        </div>

        @can('create', \App\Domain\Access\Models\Role::class)
            <a class="btn btn-primary" href="{{ route('roles.create') }}">
                <i class="bi bi-plus-lg"></i> {{ __('Tambah role') }}
            </a>
        @endcan
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <label class="form-label" for="cari-role">{{ __('Cari role') }}</label>
            <input class="form-control" id="cari-role" type="search"
                   wire:model.live.debounce.400ms="search" placeholder="{{ __('Nama atau kode role…') }}">
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Role') }}</th>
                        <th>{{ __('Jenis') }}</th>
                        <th class="text-end">{{ __('Permission') }}</th>
                        <th class="text-end">{{ __('Penugasan') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-end">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($roles as $role)
                        <tr wire:key="role-{{ $role->id }}">
                            <td>
                                <div class="fw-semibold">{{ $role->name }}</div>
                                <div class="small text-muted">{{ $role->code }}</div>
                            </td>
                            <td>
                                @if ($role->is_builtin)
                                    <span class="badge text-bg-info">{{ __('Bawaan') }}</span>
                                @else
                                    <span class="badge text-bg-light">{{ __('Buatan company') }}</span>
                                @endif
                                @if ($role->is_client_role)
                                    <span class="badge text-bg-warning">{{ __('Klien') }}</span>
                                @endif
                            </td>
                            <td class="text-end">{{ $role->permissions_count }}</td>
                            <td class="text-end">{{ $role->assignments_count }}</td>
                            <td>
                                <span class="badge text-bg-{{ $role->is_active ? 'success' : 'secondary' }}">
                                    {{ $role->is_active ? __('Aktif') : __('Nonaktif') }}
                                </span>
                            </td>
                            <td class="text-end">
                                @can('update', $role)
                                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('roles.edit', $role) }}">
                                        {{ __('Ubah') }}
                                    </a>
                                @endcan
                                @if (! $role->is_builtin)
                                    @if ($role->is_active)
                                        @can('deactivate', $role)
                                            <button class="btn btn-sm btn-outline-danger" type="button"
                                                    wire:click="deactivate({{ $role->id }})">
                                                {{ __('Nonaktifkan') }}
                                            </button>
                                        @endcan
                                    @else
                                        @can('update', $role)
                                            <button class="btn btn-sm btn-outline-success" type="button"
                                                    wire:click="reactivate({{ $role->id }})">
                                                {{ __('Aktifkan') }}
                                            </button>
                                        @endcan
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">{{ __('Tidak ada role yang cocok.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
