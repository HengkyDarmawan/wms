{{--
    Kartu Tempat simpan di detail item (A-365): per gudang dalam cakupan, berurutan, bertanda
    Khusus. Ubah (izin bin.manage) = daftar satu gudang, disimpan sekali.
--}}
<div class="card h-100" data-tempat-simpan>
    <div class="card-header d-flex align-items-center gap-2">
        <strong class="me-auto">{{ __('Tempat simpan') }}</strong>
    </div>
    <div class="card-body small">
        @forelse ($tempat as $gudangId => $daftar)
            @php
                $g = $daftar->first()->warehouse;
            @endphp
            @if ($gudangUbah !== (int) $gudangId)
                <div class="mb-3" data-tempat-gudang="{{ $g?->code }}">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="fw-semibold">{{ $g?->code }} — {{ $g?->name }}</span>
                        @if ($bolehUbah)
                            <button class="btn btn-sm btn-outline-primary py-0 ms-auto" type="button" wire:click="ubah({{ (int) $gudangId }})">{{ __('Ubah') }}</button>
                        @endif
                    </div>
                    <ol class="mb-0 ps-3">
                        @foreach ($daftar as $t)
                            <li>
                                {{ $t->label() }}
                                @if ($t->is_dedicated)
                                    <span class="badge text-bg-warning" title="{{ __('Barang lain ditolak di tempat ini') }}">{{ __('Khusus') }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endif
        @empty
            @if ($gudangUbah === 0)
                <div class="text-muted mb-2">{{ __('Belum punya tempat simpan. Saran put-away memakai aturan lama (bin yang sudah berisi barang ini, lalu bin kosong).') }}</div>
            @endif
        @endforelse

        @if ($gudangUbah > 0 && $gudangUbahModel)
            <div class="border rounded p-2 mb-2" data-ubah-tempat>
                <div class="fw-semibold mb-1">{{ $gudangUbahModel->code }} — {{ $gudangUbahModel->name }}</div>
                <div class="text-muted mb-2">{{ __('Urutan = urutan saran. "Seluruh rak": bin yang sudah berisi barang ini dulu, lalu bin kosong dari tingkat bawah, kiri ke kanan.') }}</div>
                @forelse ($baris as $i => $b)
                    <div class="d-flex align-items-center gap-1 mb-1" wire:key="tempat-{{ $b['tempat'] }}">
                        <span class="text-muted" style="width: 1.5rem">{{ $i + 1 }}.</span>
                        <span class="me-auto">{{ $b['label'] }}</span>
                        <div class="form-check form-check-inline mb-0 me-1">
                            <input class="form-check-input" type="checkbox" id="khusus-{{ $i }}" wire:model="baris.{{ $i }}.khusus">
                            <label class="form-check-label" for="khusus-{{ $i }}">{{ __('Khusus') }}</label>
                        </div>
                        <button class="btn btn-sm btn-outline-secondary py-0" type="button" wire:click="geser({{ $i }}, -1)" @disabled($i === 0) aria-label="{{ __('Naik') }}"><i class="bi bi-arrow-up"></i></button>
                        <button class="btn btn-sm btn-outline-secondary py-0" type="button" wire:click="geser({{ $i }}, 1)" @disabled($i === count($baris) - 1) aria-label="{{ __('Turun') }}"><i class="bi bi-arrow-down"></i></button>
                        <button class="btn btn-sm btn-outline-danger py-0" type="button" wire:click="hapus({{ $i }})" aria-label="{{ __('Lepas') }}"><i class="bi bi-x-lg"></i></button>
                    </div>
                @empty
                    <div class="text-muted mb-1">{{ __('Daftar kosong — simpan untuk melepas semua tempat di gudang ini.') }}</div>
                @endforelse

                {{-- Tambah tempat bertahap: ① Zona → ② Rak/area → ③ Bin (opsional, banyak sekaligus). --}}
                <div class="border-top pt-2 mt-2" data-tambah-tempat>
                    <div class="fw-semibold mb-1">{{ __('Tambah tempat') }}</div>
                    <div class="row g-2">
                        <div class="col-12 col-md-4">
                            <label class="form-label mb-0" for="tempat-zona">① {{ __('Zona') }}</label>
                            <select class="form-select form-select-sm" id="tempat-zona" wire:model.live="zonaBaru">
                                <option value="">{{ __('— Pilih zona —') }}</option>
                                @foreach ($opsiZona as $id => $teks) <option value="{{ $id }}">{{ $teks }}</option> @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-8">
                            <label class="form-label mb-0" for="tempat-rak-ts-control">② {{ __('Rak atau area lantai') }}</label>
                            <x-pilih model="rakBaru" id="tempat-rak" live kecil :options="$opsiRak" :disabled="$zonaBaru === ''"
                                     :kosong="$zonaBaru === '' ? __('Pilih zona dulu…') : __('— Pilih rak atau area —')" />
                        </div>
                    </div>

                    @if ($rakBaru !== '' && ! $rakArea)
                        <div class="mt-2" data-pilih-bin>
                            <label class="form-label mb-0" for="tempat-bin">③ {{ __('Bin (opsional — boleh lebih dari satu)') }}</label>
                            <x-pilih-tag model="binBaru" id="tempat-bin" live :options="$opsiBin" :placeholder="__('Kosongkan = seluruh rak · klik/ketik mis. L1')" />
                            @if (count($tingkat) > 0)
                                <div class="d-flex flex-wrap align-items-center gap-1 mt-1">
                                    <span class="text-muted">{{ __('Pilih semua bin di:') }}</span>
                                    @foreach ($tingkat as $t)
                                        <button class="btn btn-sm btn-outline-secondary py-0" type="button" wire:click="pilihTingkat({{ $t['id'] }})">{{ $t['kode'] }}</button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif

                    <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" id="khusus-baru" wire:model="khususBaru">
                            <label class="form-check-label" for="khusus-baru" title="{{ __('Barang lain ditolak di tempat ini') }}">{{ __('Khusus barang ini') }}</label>
                        </div>
                        <span class="text-muted ms-auto" data-ringkas-tambah>
                            @if ($rakBaru === '')
                                {{ __('Pilih zona, lalu rak atau area.') }}
                            @elseif ($rakArea)
                                {{ __('Akan ditambah: seluruh area lantai ini.') }}
                            @elseif (count($binBaru) === 0)
                                {{ __('Akan ditambah: seluruh rak ini.') }}
                            @else
                                {{ __('Akan ditambah: :n bin.', ['n' => count($binBaru)]) }}
                            @endif
                        </span>
                        <button class="btn btn-sm btn-outline-primary" type="button" wire:click="tambah" @disabled($rakBaru === '')>{{ __('Tambah') }}</button>
                    </div>
                </div>
                @if ($galat !== '')
                    <div class="text-danger mt-2" data-galat-tempat>{{ $galat }}</div>
                @endif
                <div class="d-flex gap-2 mt-2">
                    <button class="btn btn-sm btn-primary" type="button" wire:click="simpan">{{ __('Simpan tempat simpan') }}</button>
                    <button class="btn btn-sm btn-outline-secondary" type="button" wire:click="batal">{{ __('Batal') }}</button>
                </div>
            </div>
        @endif

        @if ($bolehUbah && $gudangUbah === 0 && $gudangLain->isNotEmpty())
            <div class="d-flex gap-2 align-items-center">
                <x-pilih model="gudangBaru" id="tempat-gudang-baru" kecil class="flex-grow-1" :aria="__('Gudang')"
                         :kosong="__('Atur tempat di gudang…')"
                         :options="$gudangLain->map(fn ($g) => ['value' => $g->id, 'text' => $g->code.' — '.$g->name])->all()" />
                <button class="btn btn-sm btn-outline-primary text-nowrap" type="button" wire:click="tambahGudang">{{ __('Atur') }}</button>
            </div>
        @endif
        <div class="text-muted mt-2">{{ __('Khusus = barang lain ditolak saat put-away, pilah retur, penyesuaian tambah, dan saldo awal (opname tidak). Kepala Gudang boleh membuka dengan alasan.') }}</div>
    </div>
</div>
