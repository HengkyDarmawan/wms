<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ $role ? __('Ubah role') : __('Tambah role') }}</h1>
            <p class="text-muted mb-0">
                {{ __('Field bertanda') }} <span class="wajib">*</span> {{ __('wajib diisi.') }}
                @if ($role?->is_builtin)
                    {{ __('Role bawaan: kode dan sifatnya dikunci, permission tetap bisa diubah.') }}
                @endif
            </p>
        </div>
        <a class="btn btn-outline-secondary" href="{{ route('roles.index') }}">{{ __('Kembali ke daftar') }}</a>
    </div>

    <form wire:submit="save">
        <div class="row g-3">
            <div class="col-12 col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <h2 class="h6 text-uppercase text-muted mb-3">{{ __('Identitas role') }}</h2>

                        <div class="mb-3">
                            <label class="form-label" for="code">{{ __('Kode role') }} <span class="wajib">*</span></label>
                            <input class="form-control @error('code') is-invalid @enderror" id="code" type="text"
                                   wire:model="code" maxlength="40" @disabled($role !== null)
                                   placeholder="kepala_gudang_regional">
                            <div class="form-text">{{ __('Huruf kecil, angka, dan garis bawah. Tidak bisa diubah setelah dibuat.') }}</div>
                            @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="name">{{ __('Nama role') }} <span class="wajib">*</span></label>
                            <input class="form-control @error('name') is-invalid @enderror" id="name" type="text"
                                   wire:model="name" maxlength="80" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" role="switch" id="isClientRole"
                                   wire:model="isClientRole" @disabled($role?->is_builtin)>
                            <label class="form-check-label" for="isClientRole">{{ __('Role untuk user klien') }}</label>
                            <div class="form-text">
                                {{ __('Role klien tidak bisa digabung dengan role internal dan tidak boleh bercakupan Semua.') }}
                            </div>
                        </div>

                        <hr>

                        <label class="form-label" for="copyFrom">{{ __('Salin permission dari role lain') }}</label>
                        <div class="input-group">
                            <select class="form-select" id="copyFrom" wire:model="copyFrom">
                                <option value="">{{ __('— Pilih role —') }}</option>
                                @foreach ($roleLain as $lain)
                                    <option value="{{ $lain->id }}">
                                        {{ $lain->name }}{{ $lain->is_builtin ? ' ('.__('bawaan').')' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            <button class="btn btn-outline-secondary" type="button" wire:click="salin">
                                {{ __('Salin') }}
                            </button>
                        </div>
                        <div class="form-text">{{ __('Menimpa pilihan permission saat ini.') }}</div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h2 class="h6 text-uppercase text-muted mb-0">{{ __('Permission') }}</h2>
                            <span class="badge text-bg-light">
                                {{ __(':n dipilih', ['n' => count($selected)]) }}
                            </span>
                        </div>

                        @error('selected')<div class="alert alert-danger py-2 small">{{ $message }}</div>@enderror

                        <div class="row g-3">
                            @foreach ($permissionsPerModul as $modul => $daftar)
                                @php($semuaDipilih = collect($daftar)->pluck('name')->diff($selected)->isEmpty())
                                <div class="col-12 col-md-6" wire:key="modul-{{ $modul }}">
                                    <div class="border rounded p-2 h-100">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="fw-semibold text-uppercase small">{{ $modul }}</span>
                                            <button class="btn btn-link btn-sm p-0 nx-link" type="button"
                                                    wire:click="pilihModul('{{ $modul }}', {{ $semuaDipilih ? 'false' : 'true' }})">
                                                {{ $semuaDipilih ? __('Kosongkan') : __('Pilih semua') }}
                                            </button>
                                        </div>

                                        @foreach ($daftar as $permission)
                                            <div class="form-check" wire:key="perm-{{ $permission->id }}">
                                                <input class="form-check-input" type="checkbox"
                                                       id="perm-{{ $permission->id }}"
                                                       value="{{ $permission->name }}" wire:model.live="selected">
                                                <label class="form-check-label small" for="perm-{{ $permission->id }}">
                                                    {{ $permission->label ?? $permission->name }}
                                                    <code class="ms-1 text-muted">{{ $permission->name }}</code>
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2 mt-3">
            <button class="btn btn-primary" type="submit">
                <span wire:loading.remove wire:target="save">{{ __('Simpan') }}</span>
                <span wire:loading wire:target="save">{{ __('Menyimpan…') }}</span>
            </button>
            <a class="btn btn-outline-secondary" href="{{ route('roles.index') }}">{{ __('Batal') }}</a>
        </div>
    </form>
</div>
