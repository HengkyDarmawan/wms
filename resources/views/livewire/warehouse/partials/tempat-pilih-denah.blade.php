{{--
    Popup *Pilih tempat di denah* (A-400) — kartu Tempat simpan, detail item.
    Kiri: tampak atas gudang (denah mini, klik rak/area). Kanan: tampak depan rak terpilih —
    petak bin diketuk seperti memilih kursi bioskop. "Tambah ke daftar" memakai tambah() yang sama
    dengan isian bertahap A-399; tersimpan hanya lewat "Simpan tempat simpan" di kartu.
    Variabel: $denahPilih, $petak, $gudangUbahModel, $baris, $galat, $khususBaru, $binBaru, $rakBaru, $rakArea.
--}}
<div class="modal d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="judul-pilih-denah" style="background: rgba(0,0,0,.45)" data-pilih-denah wire:keydown.escape.window="tutupDenah">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title h5 mb-0" id="judul-pilih-denah">{{ __('Pilih tempat di denah') }} — {{ $gudangUbahModel->code }}</h2>
                    <div class="small text-muted">{{ __('Klik rak atau area di tampak atas, lalu ketuk petak bin di tampak depan. Kosongkan petak = seluruh rak.') }}</div>
                </div>
                <button class="btn-close" type="button" wire:click="tutupDenah" aria-label="{{ __('Tutup') }}"></button>
            </div>

            <div class="modal-body small">
                <div class="row g-3">
                    <div class="col-lg-7">
                        <div class="fw-semibold mb-1">{{ __('Tampak atas') }} <span class="text-muted fw-normal">· {{ __('bentuk kotak = arah rak; biru = sudah di daftar') }}</span></div>
                        <div class="border rounded p-2 bg-body overflow-auto" wire:loading.class="opacity-50" wire:target="pilihRakDenah">
                            @if (! $denahPilih['d']['zones'])
                                <div class="text-muted">{{ __('Gudang ini belum punya zona dan rak. Susun dulu lewat Denah gudang → Atur denah.') }}</div>
                            @else
                                @include('warehouse.partials.denah-mini', ['d' => $denahPilih['d'], 'sorot' => $denahPilih['sorot'], 'tolak' => $denahPilih['tolak'], 'klik' => true, 'rakAktif' => $denahPilih['rakAktif'], 'tinggi' => '55vh'])
                            @endif
                        </div>
                        <div class="d-flex flex-wrap gap-3 mt-1 text-muted">
                            <span><span class="d-inline-block border rounded-1 align-middle" style="width: .9rem; height: .9rem; background: #d0ebff; border-color: #1c7ed6 !important"></span> {{ __('rak/area sudah di daftar') }}</span>
                            <span><span class="d-inline-block border rounded-1 align-middle" style="width: .9rem; height: .9rem; background: #1c7ed6"></span> {{ __('bin sudah di daftar') }}</span>
                            <span><span class="text-danger">⊘</span> {{ __('khusus barang lain — tidak bisa dipilih') }}</span>
                        </div>
                    </div>

                    <div class="col-lg-5" data-tampak-depan-pilih>
                        @if ($petak === null)
                            <div class="border rounded p-3 text-muted h-100 d-flex align-items-center justify-content-center text-center" style="min-height: 10rem">
                                {{ __('Belum ada rak yang dipilih. Klik rak atau area lantai di tampak atas.') }}
                            </div>
                        @else
                            @php $r = $petak['rak']; @endphp
                            <div class="fw-semibold fs-6 text-body">{{ $r['is_area'] ? __('Area') : __('Rak') }} {{ $r['code'] }} <span class="text-muted fw-normal">· {{ __('Zona') }} {{ $r['zona'] }}</span></div>
                            <div class="text-muted mb-2" data-arah-rak>
                                @if ($r['name']) {{ $r['name'] }} · @endif
                                <strong>{{ $r['arah'] }}</strong>
                                @if ($r['ukuran']) · {{ $r['ukuran'] }} @endif
                                @unless ($r['is_area']) · {{ $r['jumlah_tingkat'] }} {{ __('tingkat') }} × {{ $r['jumlah_bin'] }} {{ __('bin') }} @endunless
                            </div>
                            @if ($r['barang'] !== [])
                                <div class="text-muted mb-2">{{ __('Barang lain di seluruh rak ini') }}: {{ implode(', ', $r['barang']) }}</div>
                            @endif

                            @if ($r['is_area'])
                                <div class="alert alert-info py-2">{{ __('Area lantai dipilih seluruhnya — tidak ada petak bin.') }}</div>
                            @elseif ($petak['tingkat'] === [])
                                <div class="alert alert-warning py-2">{{ __('Rak ini belum punya tingkat atau bin.') }}</div>
                            @else
                                <div class="text-muted mb-1">{{ __('Tampak depan (tingkat paling bawah di bawah) — ketuk petak untuk memilih') }}</div>
                                <div class="overflow-auto">
                                    <div class="d-grid gap-1" style="min-width: {{ 2.2 + $r['jumlah_bin'] * 3.2 }}rem">
                                        @foreach ($petak['tingkat'] as $t)
                                            <div class="d-grid gap-1 align-items-stretch" style="grid-template-columns: 2.2rem repeat({{ max(1, count($t['bins'])) }}, minmax(0, 1fr))">
                                                <div class="d-flex align-items-center justify-content-between pe-1">
                                                    <span class="fw-semibold text-muted">{{ $t['kode'] }}</span>
                                                </div>
                                                @forelse ($t['bins'] as $b)
                                                    @php
                                                        $kelas = $b['terpilih'] ? 'btn-primary' : ($b['sudah'] ? 'btn-outline-primary' : ($b['khusus_lain'] !== null ? 'btn-outline-danger' : ($b['milik_ini'] ? 'btn-outline-info' : 'btn-outline-secondary')));
                                                        $judul = $b['pendek']
                                                            .($b['sudah'] ? ' · '.__('sudah di daftar') : '')
                                                            .($b['khusus_lain'] !== null ? ' · '.__('khusus barang').' '.$b['khusus_lain'] : '')
                                                            .($b['barang_lain'] !== [] ? ' · '.__('barang lain').': '.implode(', ', $b['barang_lain']) : '')
                                                            .($b['tergabung'] ? ' · '.__('tergabung ke').' '.$b['utama'] : '')
                                                            .($b['nonaktif'] ? ' · '.__('nonaktif') : '');
                                                    @endphp
                                                    <button type="button" class="btn btn-sm text-start {{ $kelas }}" style="min-height: 2.6rem; line-height: 1.15"
                                                            wire:click="toggleBin({{ $b['id'] }})" @disabled(! $b['boleh'] || $b['sudah']) title="{{ $judul }}" aria-pressed="{{ $b['terpilih'] ? 'true' : 'false' }}"
                                                            data-petak="{{ $b['pendek'] }}" @if ($b['terpilih']) data-terpilih @endif>
                                                        <span class="fw-semibold">{{ $b['short'] }}</span>
                                                        @if ($b['terpilih']) <i class="bi bi-check-lg"></i>
                                                        @elseif ($b['sudah']) <i class="bi bi-check2-all"></i>
                                                        @elseif ($b['khusus_lain'] !== null) <i class="bi bi-slash-circle"></i>
                                                        @elseif ($b['tergabung']) <span class="text-muted">⧉</span>
                                                        @endif
                                                        @if ($b['barang_lain'] !== [])
                                                            <br><span class="text-muted" style="font-size: .7rem">{{ implode(', ', array_slice($b['barang_lain'], 0, 2)) }}{{ count($b['barang_lain']) > 2 ? '…' : '' }}</span>
                                                        @elseif ($b['khusus_lain'] !== null)
                                                            <br><span style="font-size: .7rem">{{ $b['khusus_lain'] }}</span>
                                                        @endif
                                                    </button>
                                                @empty
                                                    <div class="text-muted align-self-center">{{ __('tingkat kosong') }}</div>
                                                @endforelse
                                            </div>
                                        @endforeach
                                        <div class="rounded" style="height: 6px; background: #868e96; margin-left: 2.4rem"></div>
                                    </div>
                                </div>
                                @if (count($petak['tingkat']) > 1)
                                    <div class="d-flex flex-wrap align-items-center gap-1 mt-2">
                                        <span class="text-muted">{{ __('Pilih semua bin di:') }}</span>
                                        @foreach ($petak['tingkat'] as $t)
                                            <button class="btn btn-sm btn-outline-secondary py-0" type="button" wire:click="pilihTingkat({{ $t['id'] }})">{{ $t['kode'] }}</button>
                                        @endforeach
                                    </div>
                                @endif
                                <div class="d-flex flex-wrap gap-3 mt-2 text-muted">
                                    <span><span class="badge border text-primary bg-body">✓</span> {{ __('dipilih sekarang') }}</span>
                                    <span><span class="badge border text-primary bg-body"><i class="bi bi-check2-all"></i></span> {{ __('sudah di daftar') }}</span>
                                    <span><span class="badge border text-info bg-body">&nbsp;</span> {{ __('tempat barang ini (tersimpan)') }}</span>
                                    <span><span class="badge border text-danger bg-body"><i class="bi bi-slash-circle"></i></span> {{ __('khusus barang lain') }}</span>
                                </div>
                            @endif
                        @endif
                    </div>
                </div>

                @if ($galat !== '')
                    <div class="alert alert-danger py-2 mt-3 mb-0" data-galat-tempat>{{ $galat }}</div>
                @endif
            </div>

            <div class="modal-footer d-flex flex-wrap align-items-center gap-2">
                <div class="me-auto small">
                    <div data-ringkas-tambah>
                        @if ($rakBaru === '')
                            <span class="text-muted">{{ __('Pilih rak atau area di tampak atas.') }}</span>
                        @elseif ($rakArea)
                            {{ __('Akan ditambah: seluruh area lantai ini.') }}
                        @elseif (count($binBaru) === 0)
                            {{ __('Akan ditambah: seluruh rak ini.') }}
                        @else
                            {{ __('Akan ditambah: :n bin.', ['n' => count($binBaru)]) }}
                        @endif
                    </div>
                    <div class="text-muted">{{ __('Daftar tempat sekarang') }}: {{ count($baris) }} —
                        {{ collect($baris)->pluck('label')->take(4)->implode(', ') }}{{ count($baris) > 4 ? ', …' : '' }}
                    </div>
                </div>
                <div class="form-check mb-0">
                    <input class="form-check-input" type="checkbox" id="khusus-denah" wire:model="khususBaru">
                    <label class="form-check-label small" for="khusus-denah" title="{{ __('Barang lain ditolak di tempat ini') }}">{{ __('Khusus barang ini') }}</label>
                </div>
                <button class="btn btn-primary" type="button" wire:click="tambah" @disabled($rakBaru === '') data-tambah-ke-daftar>
                    <i class="bi bi-plus-lg"></i> {{ __('Tambah ke daftar') }}
                </button>
                <button class="btn btn-outline-secondary" type="button" wire:click="tutupDenah">{{ __('Selesai') }}</button>
            </div>
        </div>
    </div>
</div>
