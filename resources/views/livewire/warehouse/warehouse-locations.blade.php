{{--
    Tab Zona & rak (A-410): Zona → Rak → Tingkat → Bin dengan popup. Popup ditutup (×, Batal,
    klik di luar, Esc) tanpa mengosongkan isiannya; isian dikosongkan setelah tersimpan
    atau lewat tombol Kosongkan.
--}}
<div x-on:keydown.escape.window="$wire.popup !== '' && $wire.tutup()" data-zona-rak>
    @php
        $petak = fn ($b) => \Illuminate\Support\Str::afterLast((string) $b->code, '-');
        $arahLabel = ['h' => __('memanjang ke samping'), 'v' => __('memanjang ke belakang')];
        // Bin utama gabungan = bin yang dirujuk occupied_by_bin_id (gabung hanya di rak yang sama).
        $utamaIds = $perLevel->flatten(1)->pluck('occupied_by_bin_id')->filter()->map(fn ($v) => (int) $v)->unique()->flip()->all();
    @endphp

    @if ($ruleError !== '' && $popup === '')
        <div class="alert alert-danger" role="alert">{{ $ruleError }}</div>
    @endif
    @if ($pesanKode !== '')
        <div class="alert alert-warning d-flex align-items-center gap-2" role="status" data-pesan-kode>
            <i class="bi bi-printer"></i> <span class="me-auto">{{ $pesanKode }}</span>
            <button class="btn-close" type="button" wire:click="$set('pesanKode', '')" aria-label="{{ __('Tutup') }}"></button>
        </div>
    @endif

    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <div class="text-muted small me-auto">
            {{ __('Urutan: Zona → Rak → Tingkat (L1 paling bawah) → Bin. Hapus hanya untuk yang belum pernah dipakai; selain itu Nonaktifkan.') }}
        </div>
        @if ($bolehAtur)
            {{-- A-414: pemandu di layar. --}}
            <x-tur kunci="zona-rak" :langkah="[
                ['el' => '', 'judul' => __('Zona & rak'), 'isi' => __('Susunan: Zona (area), lalu Rak, lalu Tingkat (L1 paling bawah), lalu Bin (petak tempat barang). Bin dibuat otomatis dari jumlah tingkat kali bin per tingkat.')],
                ['el' => '[data-tambah-zona]', 'judul' => __('1. Tambah zona'), 'isi' => __('Isi kode singkat (mis. A) dan nama (mis. Material besi), lalu Simpan.')],
                ['el' => '[data-tambah-rak]', 'judul' => __('2. Tambah rak'), 'isi' => __('Di bawah tiap zona. Isi kode rak (mis. R01), arah, jumlah tingkat, dan bin per tingkat. Contoh 3 tingkat x 4 bin = 12 bin.')],
                ['el' => '[data-lihat-bin]', 'judul' => __('3. Lihat bin'), 'isi' => __('Melihat petak-petak rak. Di sini bin bisa digabung (untuk barang besar), dipisah, atau dihapus bila belum dipakai.')],
                ['el' => '', 'judul' => __('Ubah, geser, hapus'), 'isi' => __('Panah naik/turun mengubah urutan. Ubah = ganti nama/kode. Hapus hanya bila belum pernah dipakai; selain itu Nonaktifkan.')],
            ]" />
            <button class="btn btn-primary" type="button" wire:click="bukaZona" data-tambah-zona><i class="bi bi-plus-lg"></i> {{ __('Tambah zona') }}</button>
        @endif
    </div>

    @forelse ($zones as $zona)
        @php $iz = $info['zona'][$zona->id]; @endphp
        <div class="card mb-3 {{ $zona->is_active ? '' : 'opacity-75' }}" wire:key="zona-{{ $zona->id }}" data-zona="{{ $zona->code }}">
            <div class="card-header d-flex flex-wrap align-items-center gap-2">
                <div class="me-auto">
                    <strong>{{ __('Zona') }} {{ $zona->code }}</strong> <span class="text-muted">— {{ $zona->name }}</span>
                    <span class="small text-muted ms-2">{{ $iz['rak'] }} {{ __('rak') }} · {{ $iz['bin'] }} {{ __('bin') }}</span>
                    @unless ($zona->is_active) <span class="badge text-bg-secondary">{{ __('Nonaktif') }}</span> @endunless
                </div>
                @if ($bolehAtur)
                    <div class="btn-group btn-group-sm" role="group" aria-label="{{ __('Urutan zona') }}">
                        <button class="btn btn-outline-secondary" type="button" wire:click="geser('zona', {{ $zona->id }}, -1)" @disabled($loop->first) title="{{ __('Naik') }}"><i class="bi bi-arrow-up"></i></button>
                        <button class="btn btn-outline-secondary" type="button" wire:click="geser('zona', {{ $zona->id }}, 1)" @disabled($loop->last) title="{{ __('Turun') }}"><i class="bi bi-arrow-down"></i></button>
                    </div>
                    <button class="btn btn-sm btn-outline-primary" type="button" wire:click="bukaUbah('zona', {{ $zona->id }})"><i class="bi bi-pencil"></i> {{ __('Ubah') }}</button>
                    @if (! $iz['dipakai'])
                        <button class="btn btn-sm btn-outline-danger" type="button" wire:click="hapus('zona', {{ $zona->id }})"
                                wire:confirm="{{ __('Hapus zona :k beserta semua rak, tingkat, dan bin di dalamnya? Belum pernah dipakai, jadi dihapus permanen.', ['k' => $zona->code]) }}"><i class="bi bi-trash"></i> {{ __('Hapus') }}</button>
                    @elseif ($zona->is_active)
                        <button class="btn btn-sm btn-outline-warning" type="button" wire:click="bukaNonaktif('zona', {{ $zona->id }})">{{ __('Nonaktifkan') }}</button>
                    @endif
                @endif
            </div>

            <div class="list-group list-group-flush">
                @forelse ($zona->racks as $rak)
                    @php $ir = $info['rak'][$rak->id]; @endphp
                    <div class="list-group-item {{ $rak->is_active ? '' : 'opacity-75' }}" wire:key="rak-{{ $rak->id }}" data-rak="{{ $rak->code }}">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                            <div class="me-auto">
                                <span class="fw-semibold">{{ $rak->is_area ? __('Area') : __('Rak') }} {{ $rak->code }}</span>
                                @if ($rak->name) <span class="text-muted">— {{ $rak->name }}</span> @endif
                                <span class="small text-muted ms-1">
                                    @unless ($rak->is_area) {{ $arahLabel[$rak->orientation ?: 'h'] ?? '' }} · @endunless
                                    {{ $ir['tingkat'] }} {{ __('tingkat') }} · {{ $ir['bin'] }} {{ __('bin') }}
                                </span>
                                @unless ($rak->is_active) <span class="badge text-bg-secondary">{{ __('Nonaktif') }}</span> @endunless
                            </div>
                            @unless ($rak->is_area)
                                <button class="btn btn-sm btn-outline-info" type="button" wire:click="lihatBin({{ $rak->id }})" data-lihat-bin><i class="bi bi-grid-3x3"></i> {{ __('Lihat bin') }}</button>
                            @endunless
                            @if ($bolehAtur)
                                <div class="btn-group btn-group-sm" role="group" aria-label="{{ __('Urutan rak') }}">
                                    <button class="btn btn-outline-secondary" type="button" wire:click="geser('rak', {{ $rak->id }}, -1)" @disabled($loop->first) title="{{ __('Naik') }}"><i class="bi bi-arrow-up"></i></button>
                                    <button class="btn btn-outline-secondary" type="button" wire:click="geser('rak', {{ $rak->id }}, 1)" @disabled($loop->last) title="{{ __('Turun') }}"><i class="bi bi-arrow-down"></i></button>
                                </div>
                                <button class="btn btn-sm btn-outline-primary" type="button" wire:click="bukaUbah('rak', {{ $rak->id }})"><i class="bi bi-pencil"></i></button>
                                @if (! $ir['dipakai'])
                                    <button class="btn btn-sm btn-outline-danger" type="button" wire:click="hapus('rak', {{ $rak->id }})" title="{{ __('Hapus') }}"
                                            wire:confirm="{{ __('Hapus rak :k beserta semua tingkat & binnya? Belum pernah dipakai, jadi dihapus permanen. Copot label fisiknya dari rak.', ['k' => $rak->code]) }}"><i class="bi bi-trash"></i></button>
                                @elseif ($rak->is_active)
                                    <button class="btn btn-sm btn-outline-warning" type="button" wire:click="bukaNonaktif('rak', {{ $rak->id }})">{{ __('Nonaktifkan') }}</button>
                                @endif
                            @endif
                        </div>

                        @unless ($rak->is_area)
                            <div class="ms-md-3">
                                @foreach ($urutTingkat($rak->levels) as $level)
                                    @php $il = $info['tingkat'][$level->id]; $isi = $perLevel->get($level->id, collect()); @endphp
                                    <div class="d-flex flex-wrap align-items-center gap-1 py-1 border-top" wire:key="tingkat-{{ $level->id }}" data-tingkat="{{ $level->code }}">
                                        <span class="fw-semibold font-monospace" style="width: 2.5rem">{{ $level->code }}</span>
                                        @forelse ($isi as $b)
                                            <span class="badge {{ $b->occupied_by_bin_id ? 'text-bg-light border text-muted' : (isset($utamaIds[(int) $b->id]) ? 'text-bg-primary' : 'text-bg-light border') }}"
                                                  title="{{ $b->code }}{{ $b->occupied_by_bin_id ? ' · '.__('tergabung') : '' }}">
                                                {{ $petak($b) }}@if ($b->occupied_by_bin_id) ⇠@endif
                                            </span>
                                        @empty
                                            <span class="small text-muted">{{ __('belum ada bin') }}</span>
                                        @endforelse
                                        @unless ($level->is_active) <span class="badge text-bg-secondary">{{ __('Nonaktif') }}</span> @endunless
                                        @if ($bolehAtur)
                                            <span class="ms-auto d-flex gap-1">
                                                <button class="btn btn-sm btn-outline-primary py-0" type="button" wire:click="mintaBuatBin({{ $level->id }})"><i class="bi bi-plus"></i> {{ __('Bin') }}</button>
                                                <button class="btn btn-sm btn-outline-primary py-0" type="button" wire:click="bukaUbah('tingkat', {{ $level->id }})" title="{{ __('Ubah') }}"><i class="bi bi-pencil"></i></button>
                                                @if (! $il['dipakai'])
                                                    <button class="btn btn-sm btn-outline-danger py-0" type="button" wire:click="hapus('tingkat', {{ $level->id }})" title="{{ __('Hapus') }}"
                                                            wire:confirm="{{ __('Hapus tingkat :k beserta :n bin? Belum pernah dipakai, jadi dihapus permanen.', ['k' => $level->code, 'n' => $il['bin']]) }}"><i class="bi bi-trash"></i></button>
                                                @elseif ($level->is_active)
                                                    <button class="btn btn-sm btn-outline-warning py-0" type="button" wire:click="bukaNonaktif('tingkat', {{ $level->id }})">{{ __('Nonaktifkan') }}</button>
                                                @endif
                                            </span>
                                        @endif
                                    </div>
                                @endforeach
                                @if ($bolehAtur && $rak->is_active)
                                    <button class="btn btn-sm btn-link px-0" type="button" wire:click="bukaTingkat({{ $rak->id }})"><i class="bi bi-plus"></i> {{ __('Tambah tingkat') }}</button>
                                @endif
                            </div>
                        @endunless
                    </div>
                @empty
                    <div class="list-group-item text-muted">{{ __('Belum ada rak di zona ini.') }}</div>
                @endforelse
            </div>
            @if ($bolehAtur && $zona->is_active)
                <div class="card-footer">
                    <button class="btn btn-sm btn-outline-primary" type="button" wire:click="bukaRak({{ $zona->id }})" data-tambah-rak><i class="bi bi-plus-lg"></i> {{ __('Tambah rak di zona :k', ['k' => $zona->code]) }}</button>
                </div>
            @endif
        </div>
    @empty
        <div class="card"><div class="card-body text-muted">{{ __('Belum ada zona. Mulai dengan tombol Tambah zona.') }}</div></div>
    @endforelse

    {{-- ===================== Popup ===================== --}}
    @if ($popup !== '')
        <div class="modal d-block" tabindex="-1" role="dialog" aria-modal="true" style="background: rgba(0,0,0,.45)" wire:click.self="tutup" data-popup="{{ $popup }}">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable {{ $popup === 'lihat' ? 'modal-xl' : '' }}" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title h5">
                            @switch($popup)
                                @case('zona') {{ __('Tambah zona') }} @break
                                @case('rak') {{ __('Tambah rak di zona :k', ['k' => $zonaTarget?->code]) }} @break
                                @case('tingkat') {{ __('Tambah tingkat di rak :k', ['k' => $rakTarget?->code]) }} @break
                                @case('bin') {{ __('Buat bin di :r · :l', ['r' => $levelTarget?->rack?->code, 'l' => $levelTarget?->code]) }} @break
                                @case('ubah') {{ __('Ubah :j :k', ['j' => $targetJenis, 'k' => $target?->code]) }} @break
                                @case('nonaktif') {{ __('Nonaktifkan :j :k', ['j' => $targetJenis, 'k' => $target?->code]) }} @break
                                @case('lihat') {{ __('Bin di rak :k', ['k' => $rakLihat?->code]) }} @break
                            @endswitch
                        </h2>
                        <button class="btn-close" type="button" wire:click="tutup" aria-label="{{ __('Tutup') }}"></button>
                    </div>
                    <div class="modal-body">
                        @if ($ruleError !== '')
                            <div class="alert alert-danger py-2" role="alert">{{ $ruleError }}</div>
                        @endif

                        @if ($popup === 'zona')
                            <div class="row g-3">
                                <div class="col-4">
                                    <label class="form-label" for="zona-kode">{{ __('Kode zona') }} <span class="wajib">*</span></label>
                                    <input class="form-control @error('zonaForm.code') is-invalid @enderror" id="zona-kode" type="text" maxlength="10" wire:model="zonaForm.code" placeholder="A">
                                    @error('zonaForm.code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-8">
                                    <label class="form-label" for="zona-nama">{{ __('Nama zona') }} <span class="wajib">*</span></label>
                                    <input class="form-control @error('zonaForm.name') is-invalid @enderror" id="zona-nama" type="text" maxlength="60" wire:model="zonaForm.name" placeholder="{{ __('Material besi') }}">
                                    @error('zonaForm.name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="form-text">{{ __('Zona = bagian gudang, mis. A untuk material besi. Kode jadi bagian kode bin.') }}</div>
                        @elseif ($popup === 'rak')
                            <div class="row g-3">
                                <div class="col-4">
                                    <label class="form-label" for="rak-kode">{{ __('Kode rak') }} <span class="wajib">*</span></label>
                                    <input class="form-control @error('rakForm.code') is-invalid @enderror" id="rak-kode" type="text" maxlength="10" wire:model="rakForm.code" placeholder="R01">
                                    @error('rakForm.code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-8">
                                    <label class="form-label" for="rak-nama">{{ __('Nama rak') }}</label>
                                    <input class="form-control" id="rak-nama" type="text" maxlength="60" wire:model="rakForm.name" placeholder="{{ __('opsional, mis. Rak besi beton') }}">
                                </div>
                                <div class="col-12">
                                    <span class="form-label d-block">{{ __('Arah rak') }}</span>
                                    <div class="btn-group" role="group">
                                        <input class="btn-check" type="radio" id="rak-arah-h" value="h" wire:model="rakForm.orientation">
                                        <label class="btn btn-outline-primary" for="rak-arah-h">▭ {{ __('Memanjang ke samping') }}</label>
                                        <input class="btn-check" type="radio" id="rak-arah-v" value="v" wire:model="rakForm.orientation">
                                        <label class="btn btn-outline-primary" for="rak-arah-v">▯ {{ __('Memanjang ke belakang') }}</label>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <label class="form-label" for="rak-tingkat">{{ __('Jumlah tingkat') }} <span class="wajib">*</span></label>
                                    <input class="form-control @error('rakForm.levels') is-invalid @enderror" id="rak-tingkat" type="number" min="1" max="20" wire:model.blur="rakForm.levels">
                                    @error('rakForm.levels') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-4">
                                    <label class="form-label" for="rak-bin">{{ __('Bin per tingkat') }}</label>
                                    <input class="form-control @error('rakForm.bins_per_level') is-invalid @enderror" id="rak-bin" type="number" min="0" max="50" wire:model.blur="rakForm.bins_per_level">
                                    @error('rakForm.bins_per_level') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-4">
                                    <label class="form-label" for="rak-kapasitas">{{ __('Kapasitas per bin') }}</label>
                                    <input class="form-control" id="rak-kapasitas" type="text" inputmode="decimal" wire:model="rakForm.capacity_qty" placeholder="{{ __('kosong = bebas') }}">
                                </div>
                            </div>
                            <div class="alert alert-info py-2 mt-3 mb-0 small" data-ringkas-rak>
                                {{ __('Akan dibuat: tingkat L1 sampai L:n (L1 paling bawah), masing-masing :b bin (B01…) — total :t bin.', ['n' => max(1, (int) $rakForm['levels']), 'b' => max(0, (int) $rakForm['bins_per_level']), 't' => max(1, (int) $rakForm['levels']) * max(0, (int) $rakForm['bins_per_level'])]) }}
                            </div>
                        @elseif ($popup === 'tingkat')
                            <div class="row g-3">
                                <div class="col-6">
                                    <label class="form-label" for="tingkat-kode">{{ __('Kode tingkat') }}</label>
                                    <input class="form-control @error('tingkatForm.code') is-invalid @enderror" id="tingkat-kode" type="text" maxlength="10" wire:model="tingkatForm.code" placeholder="{{ __('kosong = otomatis (L berikutnya)') }}">
                                    @error('tingkatForm.code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-6">
                                    <label class="form-label" for="tingkat-bin">{{ __('Jumlah bin') }}</label>
                                    <input class="form-control @error('tingkatForm.bins') is-invalid @enderror" id="tingkat-bin" type="number" min="0" max="50" wire:model="tingkatForm.bins">
                                    @error('tingkatForm.bins') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="form-text">{{ __('Tingkat baru ditaruh di atas tingkat yang sudah ada (L1 paling bawah).') }}</div>
                        @elseif ($popup === 'bin')
                            <div class="row g-3">
                                <div class="col-4">
                                    <label class="form-label" for="gen-jumlah">{{ __('Jumlah bin') }} <span class="wajib">*</span></label>
                                    <input class="form-control @error('generator.jumlah') is-invalid @enderror" id="gen-jumlah" type="number" min="1" max="200" wire:model="generator.jumlah">
                                    @error('generator.jumlah') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-4">
                                    <label class="form-label" for="gen-prefix">{{ __('Awalan kode') }}</label>
                                    <input class="form-control" id="gen-prefix" type="text" maxlength="5" wire:model="generator.prefix" placeholder="B">
                                </div>
                                <div class="col-4">
                                    <label class="form-label" for="gen-kapasitas">{{ __('Kapasitas per bin') }}</label>
                                    <input class="form-control @error('generator.capacity_qty') is-invalid @enderror" id="gen-kapasitas" type="text" wire:model="generator.capacity_qty" placeholder="{{ __('kosong = bebas') }}">
                                    @error('generator.capacity_qty') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-12">
                                    <x-pilih model="generator.storage_category_id" id="gen-kategori" dialog :label="__('Kategori penyimpanan')"
                                             :kosong="__('Tidak ditentukan')" :options="$storageCategories->map(fn ($k) => ['value' => $k->id, 'text' => $k->name])->all()" />
                                </div>
                            </div>
                            <div class="form-text">{{ __('Nomor melanjutkan bin yang sudah ada di tingkat ini, mis. B06, B07, …') }}</div>
                        @elseif ($popup === 'ubah' && $target)
                            <div class="row g-3">
                                <div class="col-4">
                                    <label class="form-label" for="ubah-kode">{{ __('Kode') }}</label>
                                    <input class="form-control @error('ubahForm.code') is-invalid @enderror" id="ubah-kode" type="text" maxlength="10" wire:model="ubahForm.code">
                                    @error('ubahForm.code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                @if ($targetJenis !== 'tingkat')
                                    <div class="col-8">
                                        <label class="form-label" for="ubah-nama">{{ __('Nama') }} @if ($targetJenis === 'zona') <span class="wajib">*</span> @endif</label>
                                        <input class="form-control @error('ubahForm.name') is-invalid @enderror" id="ubah-nama" type="text" maxlength="60" wire:model="ubahForm.name">
                                        @error('ubahForm.name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                @endif
                                @if ($targetJenis === 'rak' && ! $target->is_area)
                                    <div class="col-12">
                                        <span class="form-label d-block">{{ __('Arah rak') }}</span>
                                        <div class="btn-group" role="group">
                                            <input class="btn-check" type="radio" id="ubah-arah-h" value="h" wire:model="ubahForm.orientation">
                                            <label class="btn btn-outline-primary" for="ubah-arah-h">▭ {{ __('Memanjang ke samping') }}</label>
                                            <input class="btn-check" type="radio" id="ubah-arah-v" value="v" wire:model="ubahForm.orientation">
                                            <label class="btn btn-outline-primary" for="ubah-arah-v">▯ {{ __('Memanjang ke belakang') }}</label>
                                        </div>
                                    </div>
                                @endif
                            </div>
                            <div class="form-text">{{ __('Kode hanya bisa diubah selama bin di bawahnya belum pernah dipakai; kode bin ikut berubah.') }}</div>
                        @elseif ($popup === 'nonaktif' && $target)
                            <p class="text-muted">{{ __('Sudah pernah dipakai, jadi tidak dihapus — datanya tetap tersimpan. Bin di dalamnya ikut nonaktif dan harus kosong.') }}</p>
                            <label class="form-label" for="nonaktif-alasan">{{ __('Alasan') }} <span class="wajib">*</span></label>
                            <select class="form-select @error('nonaktif.reason') is-invalid @enderror" id="nonaktif-alasan" wire:model="nonaktifAlasan">
                                <option value="">{{ __('Pilih alasan…') }}</option>
                                @foreach ($alasan as $kode => $teks) <option value="{{ $kode }}">{{ $teks }}</option> @endforeach
                            </select>
                            @error('nonaktif.reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        @elseif ($popup === 'lihat' && $rakLihat)
                            <p class="small text-muted mb-2">{{ __('Tampak depan rak (L1 paling bawah). Ketuk kotak untuk memilih. Gabung: pilih 2+ bin bersebelahan di satu tingkat, atau bernomor sama di tingkat bertumpuk.') }}</p>
                            <div class="d-flex flex-column gap-1 mb-3" data-tampak-depan>
                                @foreach ($urutTingkat($rakLihat->levels) as $level)
                                    <div class="d-flex align-items-stretch gap-1">
                                        <span class="fw-semibold font-monospace text-end pe-1" style="width: 2.5rem">{{ $level->code }}</span>
                                        @forelse ($perLevel->get($level->id, collect()) as $b)
                                            @php
                                                $pilih = in_array((string) $b->id, $binPilih, true);
                                                $anggota = $b->occupied_by_bin_id !== null;
                                                $utama = ! $anggota && isset($utamaIds[(int) $b->id]);
                                            @endphp
                                            <button class="btn btn-sm flex-fill {{ $pilih ? 'btn-primary' : ($utama ? 'btn-info' : ($anggota ? 'btn-outline-info' : 'btn-outline-secondary')) }}"
                                                    type="button" wire:click="pilihBin({{ $b->id }})" style="min-width: 3.5rem"
                                                    title="{{ $b->code }}{{ isset($dipakai[(int) $b->id]) ? ' · '.__('pernah dipakai') : '' }}" data-petak="{{ $level->code }}-{{ $petak($b) }}">
                                                {{ $petak($b) }}
                                                @if ($utama) <i class="bi bi-link-45deg"></i> @endif
                                                @if ($anggota) <i class="bi bi-arrow-return-left"></i> @endif
                                            </button>
                                        @empty
                                            <span class="small text-muted align-self-center">{{ __('belum ada bin') }}</span>
                                        @endforelse
                                    </div>
                                @endforeach
                            </div>
                            <div class="small text-muted mb-3">
                                <span class="badge text-bg-info">B03 <i class="bi bi-link-45deg"></i></span> {{ __('bin utama gabungan') }} ·
                                <span class="badge border text-info">B04 <i class="bi bi-arrow-return-left"></i></span> {{ __('ikut tergabung') }} ·
                                <span class="badge text-bg-primary">B05</span> {{ __('terpilih') }}
                            </div>

                            @php
                                $terpilih = $perLevel->flatten(1)->whereIn('id', array_map('intval', $binPilih));
                                $satu = $terpilih->count() === 1 ? $terpilih->first() : null;
                                $satuGabung = $satu && ($satu->occupied_by_bin_id !== null || isset($utamaIds[(int) $satu->id]));
                            @endphp
                            @if ($bolehAtur)
                                <div class="border rounded p-2" data-aksi-bin>
                                    <div class="fw-semibold mb-2">{{ __('Terpilih: :n bin', ['n' => count($binPilih)]) }}
                                        @if ($terpilih->isNotEmpty()) <span class="text-muted fw-normal">({{ $terpilih->map(fn ($b) => $b->code)->implode(', ') }})</span> @endif
                                    </div>
                                    @if (count($binPilih) >= 2)
                                        <div class="row g-2 align-items-end">
                                            <div class="col-md-3">
                                                <label class="form-label small mb-0" for="gabung-sifat">{{ __('Sifat') }}</label>
                                                <select class="form-select form-select-sm" id="gabung-sifat" wire:model="gabungForm.sifat">
                                                    <option value="temporary">{{ __('Sementara (pisah otomatis saat kosong)') }}</option>
                                                    <option value="permanent">{{ __('Permanen') }}</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small mb-0" for="gabung-alasan">{{ __('Alasan') }} <span class="wajib">*</span></label>
                                                <input class="form-control form-control-sm @error('gabungForm.alasan') is-invalid @enderror" id="gabung-alasan" type="text" maxlength="255" wire:model="gabungForm.alasan" placeholder="{{ __('mis. genset besar memakan 2 petak') }}">
                                                @error('gabungForm.alasan') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            </div>
                                            <div class="col-md-3"><button class="btn btn-sm btn-primary w-100" type="button" wire:click="gabung" data-gabung><i class="bi bi-link-45deg"></i> {{ __('Gabung') }}</button></div>
                                        </div>
                                    @elseif ($satuGabung)
                                        <div class="d-flex flex-wrap gap-2 align-items-end">
                                            <div class="flex-grow-1">
                                                <label class="form-label small mb-0" for="pisah-alasan">{{ __('Alasan pisah (wajib bila permanen)') }}</label>
                                                <input class="form-control form-control-sm" id="pisah-alasan" type="text" maxlength="255" wire:model="pisahAlasan">
                                            </div>
                                            <button class="btn btn-sm btn-outline-primary" type="button" wire:click="pisah({{ $satu->id }})" data-pisah><i class="bi bi-scissors"></i> {{ __('Pisah jadi bin kecil lagi') }}</button>
                                        </div>
                                    @elseif ($satu && ! isset($dipakai[(int) $satu->id]))
                                        <button class="btn btn-sm btn-outline-danger" type="button" wire:click="hapusBin({{ $satu->id }})" data-hapus-bin-daftar
                                                wire:confirm="{{ __('Hapus bin :k? Belum pernah dipakai, jadi dihapus permanen. Pastikan label fisik bin ini dicopot dari rak.', ['k' => $satu->code]) }}"><i class="bi bi-trash"></i> {{ __('Hapus bin') }}</button>
                                        <span class="small text-warning ms-2"><i class="bi bi-exclamation-triangle"></i> {{ __('Pastikan label fisik bin ini dicopot dari rak.') }}</span>
                                    @elseif ($satu)
                                        <span class="small text-muted">{{ __('Bin ini pernah dipakai — tidak bisa dihapus. Nonaktifkan lewat tab Bin atau Denah.') }}</span>
                                    @else
                                        <span class="small text-muted">{{ __('Ketuk satu bin untuk hapus/pisah, atau dua bin atau lebih untuk gabung.') }}</span>
                                    @endif
                                </div>
                            @endif
                        @endif
                    </div>
                    <div class="modal-footer">
                        @if (in_array($popup, ['zona', 'rak', 'tingkat', 'bin'], true))
                            <button class="btn btn-link text-muted me-auto" type="button"
                                    wire:click="kosongkan('{{ ['zona' => 'zonaForm', 'rak' => 'rakForm', 'tingkat' => 'tingkatForm', 'bin' => 'generator'][$popup] }}')">{{ __('Kosongkan') }}</button>
                        @endif
                        <button class="btn btn-outline-secondary" type="button" wire:click="tutup">{{ $popup === 'lihat' ? __('Tutup') : __('Batal') }}</button>
                        @switch($popup)
                            @case('zona') <button class="btn btn-primary" type="button" wire:click="simpanZona">{{ __('Simpan zona') }}</button> @break
                            @case('rak') <button class="btn btn-primary" type="button" wire:click="simpanRak">{{ __('Simpan rak') }}</button> @break
                            @case('tingkat') <button class="btn btn-primary" type="button" wire:click="simpanTingkat">{{ __('Simpan tingkat') }}</button> @break
                            @case('bin') <button class="btn btn-primary" type="button" wire:click="buatBinMassal">{{ __('Buat bin') }}</button> @break
                            @case('ubah') <button class="btn btn-primary" type="button" wire:click="simpanUbah">{{ __('Simpan perubahan') }}</button> @break
                            @case('nonaktif') <button class="btn btn-warning" type="button" wire:click="simpanNonaktif">{{ __('Nonaktifkan') }}</button> @break
                        @endswitch
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
