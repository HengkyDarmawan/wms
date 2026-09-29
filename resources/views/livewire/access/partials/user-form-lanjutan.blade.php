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
                    <x-pilih model="orgUnitId" id="orgUnitId" live :label="__('Unit organisasi')"
                             :kosong="__('— Tidak diisi —')" :options="$units" />
                    <div class="form-text">{{ __('Disarankan otomatis dari peran yang dipilih.') }}</div>
                </div>

                <div class="col-md-4">
                    {{-- A-385: hanya jabatan aktif di unit terpilih; tanpa unit = berkelompok per unit. --}}
                    <x-pilih model="positionId" id="positionId" live :label="__('Jabatan')"
                             :kosong="__('— Tidak diisi —')" :options="$positions" />
                    <div class="form-text">
                        {{ $orgUnitId === null ? __('Memilih jabatan ikut mengisi unitnya.') : __('Hanya jabatan di unit ini.') }}
                    </div>
                </div>

                <div class="col-md-4">
                    {{-- A-358, A-386: nama + badge jabatan + unit; Unit ini → Unit induk → Unit lain; cari ke server. --}}
                    <x-pilih model="managerId" id="managerId" server :kunci="(string) $orgUnitId" :kelompok="$kelompokAtasan"
                             :label="__('Atasan langsung (bila beda dari jabatan)')"
                             :kosong="__('— Ikuti jabatan —')" :options="$managers"
                             :placeholder="__('Cari nama, jabatan, atau unit…')" />
                    {{-- A-345: kosong = pemegang jabatan atasan (peta jabatan); isi hanya bila berbeda. --}}
                    <div class="form-text">
                        @if ($atasanDariJabatan !== [])
                            {{ __('Kosongkan untuk mengikuti jabatan: :nama.', ['nama' => implode(', ', $atasanDariJabatan)]) }}
                        @else
                            {{ __('Jabatan ini belum punya atasan di Struktur organisasi; isi bila perlu.') }}
                        @endif
                    </div>
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
                                <x-pilih model="assignments.{{ $index }}.role_id" id="role-{{ $index }}" kecil wajib
                                         :label="__('Peran')" :kosong="__('— pilih peran —')"
                                         :options="$roles->map(fn ($r) => ['value' => $r->id, 'text' => $r->name])->all()" />
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
                                    {{-- A-384: proyek dicari ke server; kunci = jenis cakupan supaya kotak dibuat ulang saat diganti. --}}
                                    <x-pilih model="assignments.{{ $index }}.scope_id" id="scopeid-{{ $index }}" kecil server kunci="project"
                                             :label="__('Proyek')" :kosong="__('— pilih proyek —')" :options="$proyekBaris[$index] ?? []" />
                                @elseif (($assignment['scope_type'] ?? '') === 'warehouse')
                                    <x-pilih model="assignments.{{ $index }}.scope_id" id="scopeid-{{ $index }}" kecil
                                             :label="__('Gudang')" :kosong="__('— pilih gudang —')"
                                             :options="$warehouses->map(fn ($w) => ['value' => $w->id, 'text' => $w->code.' — '.$w->name])->all()" />
                                @else
                                    {{-- Cakupan `all` tidak memakai id sama sekali. --}}
                                    <label class="form-label small" for="scopeid-{{ $index }}">{{ __('Cakupan') }}</label>
                                    <input class="form-control form-control-sm" id="scopeid-{{ $index }}" type="text"
                                           value="{{ __('Seluruh company') }}" disabled>
                                    @error('assignments.'.$index.'.scope_id')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                @endif
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
