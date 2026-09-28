{{--
    Panel rak (A-320, desain ulang): kepala "Rak R01 · Zona A — nama zona", ringkasan,
    tampak depan rak (baris = level, L1 paling bawah, warna sama dengan denah), isi bin
    yang diklik, tab Isi | Atur (Atur hanya di mode Atur denah). Variabel dari layar denah.
--}}
@php($semuaBin = collect($rak['levels'])->flatMap(fn ($l) => $l['bins']))
@php($terisi = $semuaBin->filter(fn ($b) => $b['total'] > 0)->count())
@php($kolomMaks = max(1, (int) collect($rak['levels'])->max(fn ($l) => count($l['bins']))))
<div class="card mb-3 border-primary" data-panel-rak>
    <div class="card-header d-block">
        <div class="d-flex justify-content-between align-items-start gap-2">
            <div>
                <div class="fw-semibold fs-6 text-body" style="text-transform: none; letter-spacing: normal">{{ __('Rak') }} {{ $rak['code'] }} · {{ __('Zona') }} {{ $rak['zona'] }} — {{ $rak['zona_nama'] }}
                    @if ($rak['is_area']) <span class="badge text-bg-secondary">{{ __('area') }}</span> @endif</div>
                @if ($rak['name']) <div class="small text-muted">{{ __('Nama rak') }}: {{ $rak['name'] }}</div> @endif
            </div>
            <button class="btn-close" type="button" wire:click="tutupRak" aria-label="{{ __('Tutup') }}"></button>
        </div>
        <div class="mt-2 small">
            <span class="badge rounded-pill text-bg-light border me-1"><i class="bi bi-layers"></i> {{ count($rak['levels']) }} {{ __('level') }} × {{ $kolomMaks }} {{ __('bin') }}</span>
            <span class="badge rounded-pill text-bg-light border me-1"><i class="bi bi-circle-fill" style="color: #40c057"></i> {{ $terisi }} {{ __('terisi') }} · {{ $semuaBin->count() - $terisi }} {{ __('kosong') }}</span>
            <span class="badge rounded-pill text-bg-light border me-1"><i class="bi bi-arrows-angle-expand"></i> {{ $angka($rak['len']) }} × {{ $angka($rak['wid']) }} m @if ($rak['height_m']) · {{ __('tinggi') }} {{ $angka($rak['height_m']) }} m @endif</span>
            <span class="badge rounded-pill text-bg-light border"><i class="bi bi-geo"></i> {{ $rak['otomatis'] ? __('posisi ditata otomatis') : 'X '.$angka($rak['x']).' m · Y '.$angka($rak['y']).' m' }}</span>
        </div>
        <ul class="nav nav-tabs card-header-tabs mt-2">
            <li class="nav-item"><button class="nav-link {{ $tabRak === 'isi' ? 'active' : '' }}" type="button" wire:click="pilihTab('isi')">{{ __('Isi') }}</button></li>
            <li class="nav-item">
                <button class="nav-link {{ $tabRak === 'atur' ? 'active' : '' }}" type="button" wire:click="pilihTab('atur')" @disabled(! $edit)
                        title="{{ $edit ? '' : __('Aktif di mode Atur denah') }}">{{ __('Atur') }} @unless ($edit) <i class="bi bi-lock small"></i> @endunless</button>
            </li>
        </ul>
    </div>

    @if ($tabRak === 'isi' || ! $edit)
        <div class="card-body small">
            <div class="text-muted mb-1">{{ __('Tampak depan (L1 paling bawah) — klik petak bin') }}</div>
            <div class="d-grid gap-1" data-tampak-depan>
                @foreach ($rak['levels'] as $lv)
                    <div class="d-grid gap-1 align-items-stretch" style="grid-template-columns: 2.2rem repeat({{ $kolomMaks }}, minmax(0, 1fr))" wire:key="depan-{{ $lv['id'] }}">
                        <div class="fw-semibold text-muted d-flex align-items-center justify-content-end pe-1">{{ $lv['code'] }}</div>
                        @forelse ($lv['bins'] as $b)
                            @php($warnaBin = $b['nonaktif'] ? '#dee2e6' : ($mode === 'umur' ? $warnaUmur($b['umur']) : $warnaStatus[$b['status']]))
                            <button type="button" wire:click="pilihBin({{ $b['id'] }})" wire:key="petak-{{ $b['id'] }}" data-bin="{{ $b['code'] }}"
                                    class="btn btn-sm text-start border {{ $binId === $b['id'] ? 'border-primary border-2' : '' }}" style="background: {{ $warnaBin }}; line-height: 1.15">
                                <span class="fw-semibold">{{ $lv['code'] }}-{{ $b['short'] }}</span><br>
                                <span class="text-muted">{{ $b['isi'] === [] ? __('kosong') : \Illuminate\Support\Str::limit(collect($b['isi'])->pluck('item_code')->unique()->implode(', '), 22) }}</span>
                            </button>
                        @empty
                            <div class="text-muted fst-italic">{{ __('belum ada bin') }}</div>
                        @endforelse
                    </div>
                @endforeach
                <div class="rounded" style="height: 6px; background: #868e96; margin-left: 2.4rem"></div>
            </div>

            <hr>
            @if ($bin)
                <div data-isi-bin>
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div>
                            <div class="fw-semibold">{{ __('Bin') }} {{ $bin['level'] }}-{{ $bin['short'] }}</div>
                            <div class="font-monospace text-muted" style="font-size: .75rem" x-data="{ salin: false }">
                                {{ $bin['code'] }}
                                <button class="btn btn-link btn-sm p-0 ms-1 align-baseline" type="button" title="{{ __('Salin kode penuh') }}" aria-label="{{ __('Salin kode penuh') }}"
                                        x-on:click="navigator.clipboard?.writeText(@js($bin['code'])); salin = true; setTimeout(() => salin = false, 1500)">
                                    <i class="bi" :class="salin ? 'bi-clipboard-check' : 'bi-clipboard'"></i>
                                </button>
                            </div>
                        </div>
                        <div class="text-end">
                            <span class="badge text-bg-light border">{{ $labelStatus[$bin['status']] }}</span>
                            @if ($bin['capacity_qty'] !== null) <div class="text-muted">{{ $angka($bin['total']) }} / {{ $angka($bin['capacity_qty']) }}</div> @endif
                        </div>
                    </div>
                    @if ($bin['nonaktif']) <span class="badge text-bg-secondary mt-1">{{ __('nonaktif') }}</span> @endif
                    @if ($bin['terpakai_oleh'])
                        <div class="mt-1"><span class="badge" style="background: #d0bfff; color: #212529">{{ __('ikut terpakai oleh') }} {{ $bin['terpakai_oleh'] }}</span>
                            @if ($edit) <button class="btn btn-sm btn-link p-0" type="button" wire:click="lepasTerpakai({{ $bin['id'] }})">{{ __('lepas') }}</button> @endif
                            @if ($bin['occupied_reason']) <div class="text-muted">{{ $bin['occupied_reason'] }}</div> @endif
                        </div>
                    @endif
                    @forelse ($bin['isi'] as $s)
                        <div class="border rounded p-2 mt-2">
                            <div class="d-flex justify-content-between gap-2">
                                <strong>{{ $s['item_code'] }}</strong>
                                <span>{{ $angka($s['qty']) }} {{ $s['uom'] }}@if ($s['kemasan'] ?? null) <span class="text-muted">({{ $s['kemasan'] }})</span>@endif</span>
                            </div>
                            <div class="text-muted">{{ $s['item_name'] }}@if ($s['tracking'] !== '') · {{ $s['tracking'] }} @endif
                                @if ($s['status'] && $s['status'] !== __('Tersedia')) <span class="badge text-bg-light border">{{ $s['status'] }}</span> @endif</div>
                            <div>{{ __('Masuk') }} {{ $s['masuk']?->lokal()->format('d/m/Y') }} · {{ $s['umur'] }} {{ __('hari') }}
                                @if ($s['tertua']) <span class="badge text-bg-warning" title="{{ __('Masuk paling lama untuk item ini di gudang — ambil dulu (FIFO)') }}">{{ __('tertua — ambil dulu') }}</span> @endif</div>
                        </div>
                    @empty
                        @unless ($bin['terpakai_oleh']) <div class="text-muted mt-2">{{ __('Bin kosong.') }}</div> @endunless
                    @endforelse
                    <div class="mt-2"><a href="{{ route('bins.index', ['q' => $bin['code']]) }}">{{ __('Lihat di daftar bin') }}</a></div>
                </div>
            @else
                <div class="text-muted">{{ __('Rak ini belum punya bin.') }}</div>
            @endif
        </div>
    @else
        <div class="card-body small" data-tab-atur>
            <div class="text-muted mb-2">{{ __('Kode zona/rak/level/bin terkunci setelah dibuat (BR-WH-01); yang diatur hanya nama, ukuran, posisi, dan arah.') }}</div>
            <div class="row g-2">
                <div class="col-12"><label class="form-label mb-0" for="rak-nama">{{ __('Nama rak') }}</label><input class="form-control form-control-sm" id="rak-nama" type="text" maxlength="60" wire:model="formRak.name"></div>
                @foreach (['length_m' => __('Panjang (m)'), 'width_m' => __('Lebar (m)'), 'height_m' => __('Tinggi (m)'), 'pos_x' => __('X (m)'), 'pos_y' => __('Y (m)')] as $k => $t)
                    <div class="col-4">
                        <label class="form-label mb-0" for="rak-{{ $k }}">{{ $t }}</label>
                        <input class="form-control form-control-sm @error('formRak.'.$k) is-invalid @enderror" id="rak-{{ $k }}" type="number" step="0.5" min="0" wire:model="formRak.{{ $k }}" placeholder="{{ __('opsional') }}">
                        @error('formRak.'.$k) <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                @endforeach
                <div class="col-4">
                    <label class="form-label mb-0" for="rak-arah">{{ __('Arah') }}</label>
                    <select class="form-select form-select-sm" id="rak-arah" wire:model="formRak.orientation">
                        <option value="h">{{ __('Memanjang ke samping') }}</option>
                        <option value="v">{{ __('Memanjang ke bawah') }}</option>
                    </select>
                </div>
            </div>
            <div class="d-flex gap-2 mt-2">
                <button class="btn btn-sm btn-primary" type="button" wire:click="simpanRak">{{ __('Simpan rak') }}</button>
                <button class="btn btn-sm btn-outline-secondary" type="button" wire:click="putar"><i class="bi bi-arrow-clockwise"></i> {{ __('Putar 90°') }}</button>
            </div>
            <div class="text-muted mt-2">{{ __('Rak tidak bisa dipindah ke zona lain karena kode binnya ikut berubah. Buat rak baru di zona tujuan (Tambah rak), kosongkan rak ini, lalu nonaktifkan.') }}</div>

            @unless ($rak['is_area'])
                <hr>
                <div class="fw-semibold mb-1">{{ __('Tambah level') }}</div>
                <div class="row g-2">
                    <div class="col-5">
                        <input class="form-control form-control-sm @error('formLevelBaru.code') is-invalid @enderror" type="text" maxlength="10" wire:model="formLevelBaru.code" placeholder="{{ __('Kode (kosong = otomatis)') }}" aria-label="{{ __('Kode level') }}">
                        @error('formLevelBaru.code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-3">
                        <input class="form-control form-control-sm @error('formLevelBaru.bins') is-invalid @enderror" type="number" min="0" max="{{ \App\Domain\Warehouse\Actions\SaveWarehouseLayout::MAKS_BIN_PER_LEVEL }}" wire:model="formLevelBaru.bins" aria-label="{{ __('Jumlah bin di level baru') }}">
                        @error('formLevelBaru.bins') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-4"><button class="btn btn-sm btn-outline-primary w-100" type="button" wire:click="tambahLevel">{{ __('Tambah level') }}</button></div>
                </div>
                <div class="fw-semibold mt-3 mb-1">{{ __('Tambah bin') }}</div>
                @foreach ($rak['levels'] as $lv)
                    <div class="d-flex align-items-center gap-2 mb-1" wire:key="tambah-bin-{{ $lv['id'] }}">
                        <span class="text-muted" style="width: 2.2rem">{{ $lv['code'] }}</span>
                        <input class="form-control form-control-sm @error('formBinBaru.'.$lv['id']) is-invalid @enderror" style="max-width: 5rem" type="number" min="1" max="{{ \App\Domain\Warehouse\Actions\SaveWarehouseLayout::MAKS_BIN_PER_LEVEL }}" wire:model="formBinBaru.{{ $lv['id'] }}" placeholder="1" aria-label="{{ __('Jumlah bin baru di level') }} {{ $lv['code'] }}">
                        <button class="btn btn-sm btn-outline-primary" type="button" wire:click="tambahBin({{ $lv['id'] }})">{{ __('Tambah bin') }}</button>
                        @error('formBinBaru.'.$lv['id']) <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                @endforeach
            @endunless

            {{-- A-255: barang besar tak terduga memakan bin sebelahnya. --}}
            <hr>
            <div class="fw-semibold mb-1">{{ __('Tandai bin ikut terpakai barang besar') }}</div>
            <div class="row g-2">
                <div class="col-6">
                    <select class="form-select form-select-sm @error('tandai.occupied_by') is-invalid @enderror" wire:model="tandai.utama" aria-label="{{ __('Bin utama') }}">
                        <option value="">{{ __('Bin utama…') }}</option>
                        @foreach ($semuaBin->where('total', '>', 0) as $b) <option value="{{ $b['id'] }}">{{ $b['code'] }}</option> @endforeach
                    </select>
                    @error('tandai.occupied_by') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-6">
                    <input class="form-control form-control-sm @error('tandai.occupied_reason') is-invalid @enderror" type="text" maxlength="255" wire:model="tandai.alasan" placeholder="{{ __('Alasan, mis. genset besar') }}" aria-label="{{ __('Alasan') }}">
                    @error('tandai.occupied_reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-12">
                    @foreach ($semuaBin->where('total', 0)->whereNull('terpakai_oleh') as $b)
                        <label class="form-check form-check-inline"><input class="form-check-input" type="checkbox" value="{{ $b['id'] }}" wire:model="tandai.bins"> <span class="form-check-label">{{ $b['code'] }}</span></label>
                    @endforeach
                    @error('tandai.bins') <div class="text-danger">{{ $message }}</div> @enderror
                </div>
            </div>
            <button class="btn btn-sm btn-outline-primary mt-2" type="button" wire:click="tandaiTerpakai">{{ __('Tandai ikut terpakai') }}</button>

            {{-- A-324: nonaktif, bukan hapus (P-03); semua bin harus kosong. --}}
            <hr>
            <div class="fw-semibold mb-1">{{ __('Nonaktifkan rak') }}</div>
            <div class="d-flex flex-wrap gap-2 align-items-start">
                <div>
                    <select class="form-select form-select-sm @error('nonaktif.reason') is-invalid @enderror" wire:model="alasanNonaktif" aria-label="{{ __('Alasan nonaktif') }}">
                        <option value="">{{ __('Alasan…') }}</option>
                        @foreach ($alasan as $kode => $label) <option value="{{ $kode }}">{{ $label }}</option> @endforeach
                    </select>
                    @error('nonaktif.reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <button class="btn btn-sm btn-outline-danger" type="button" wire:click="nonaktifkan" wire:confirm="{{ __('Nonaktifkan rak ini beserta level & binnya?') }}"><i class="bi bi-slash-circle"></i> {{ __('Nonaktifkan') }}</button>
            </div>
            <div class="text-muted mt-1">{{ __('Hanya bila semua bin kosong, tanpa reservasi, dan tidak dibekukan opname. Rak nonaktif tidak digambar.') }}</div>
        </div>
    @endif
</div>
