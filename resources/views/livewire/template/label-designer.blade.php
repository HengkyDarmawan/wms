<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Desain label') }}</h1>
            <p class="text-muted mb-0">
                {{ __('Geser dan ubah ukuran elemen di kanvas, pilih barcode, QR, atau keduanya, lalu simpan. Setiap jenis label punya desain per ukuran.') }}
            </p>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="{{ route('label-formats.index') }}"><i class="bi bi-rulers"></i> {{ __('Ukuran label') }}</a>
            @if ($format)
                <a class="btn btn-outline-secondary" href="{{ route('label-designs.preview', ['type' => $type, 'format' => $format->id]) }}" target="_blank" rel="noopener">
                    <i class="bi bi-file-earmark-pdf"></i> {{ __('Contoh cetak (tersimpan)') }}
                </a>
            @endif
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label" for="ld-jenis">{{ __('Jenis label') }} <span class="wajib">*</span></label>
                <select class="form-select" id="ld-jenis" wire:model.live="type">
                    @foreach ($jenisLabel as $j)
                        <option value="{{ $j->value }}">{{ $j->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label" for="ld-format">{{ __('Ukuran label') }} <span class="wajib">*</span></label>
                <select class="form-select" id="ld-format" wire:model.live="formatId">
                    @foreach ($formats as $f)
                        <option value="{{ $f->id }}">{{ $f->name }} — {{ $f->summary() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 small text-muted">
                {{ __('Teks utama') }}: {{ $sumber['title'] }} · {{ __('Teks kedua') }}: {{ $sumber['subtitle'] }} · {{ __('Keterangan') }}: {{ $sumber['detail'] }}
            </div>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    @if ($cfg === null)
        <div class="alert alert-warning">{{ __('Belum ada ukuran label aktif. Tambahkan dulu di Ukuran label.') }}</div>
    @else
        <div wire:ignore wire:key="ld-{{ $type }}-{{ $format->id }}-{{ $versi }}"
             x-data="labelDesigner(@js($cfg))" class="row g-3 ld-designer"
             @keydown.window="tombol($event)">
            <div class="col-xl-8">
                <div class="card">
                    <div class="card-header d-flex flex-wrap gap-3 align-items-center">
                        <div class="btn-group btn-group-sm" role="group" aria-label="{{ __('Kode yang dicetak') }}">
                            @foreach ($kodeOpsi as $nilai => $nama)
                                <input type="radio" class="btn-check" name="ld-kode" id="ld-kode-{{ $nilai }}" value="{{ $nilai }}" x-model="codeMode">
                                <label class="btn btn-outline-primary" for="ld-kode-{{ $nilai }}">{{ $nama }}</label>
                            @endforeach
                        </div>
                        <div class="btn-group btn-group-sm" role="group" aria-label="{{ __('Perbesaran') }}">
                            <button class="btn btn-outline-secondary" type="button" @click="perbesar(-1)" title="{{ __('Perkecil') }}"><i class="bi bi-zoom-out"></i></button>
                            <span class="btn btn-outline-secondary disabled" x-text="Math.round(zoom * 10) / 10 + ' px/mm'"></span>
                            <button class="btn btn-outline-secondary" type="button" @click="perbesar(1)" title="{{ __('Perbesar') }}"><i class="bi bi-zoom-in"></i></button>
                        </div>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="ld-grid" x-model="grid">
                            <label class="form-check-label small" for="ld-grid">{{ __('Tempel ke kisi 0,5 mm') }}</label>
                        </div>
                        <button class="btn btn-sm btn-outline-secondary ms-auto" type="button" @click="tataUlang()">
                            <i class="bi bi-magic"></i> {{ __('Tata ulang otomatis') }}
                        </button>
                    </div>
                    <div class="card-body bg-body-tertiary">
                        <div class="ld-wadah" x-ref="wadah">
                            <div class="ld-kanvas" x-ref="kanvas" :style="kanvasStyle()" @pointerdown.self="dipilih = null">
                                <template x-for="k in urutan" :key="k">
                                    <div class="ld-el" :class="{ 'ld-aktif': dipilih === k }" x-show="tampil(k)" :style="kotak(k)"
                                         :data-el="k" @pointerdown="dipilih = k" x-init="pasang($el, k)">
                                        <template x-if="['title', 'subtitle', 'detail'].includes(k)">
                                            <div class="ld-teks" :style="teksStyle(k)" x-text="sample[k]"></div>
                                        </template>
                                        <template x-if="k === 'barcode'">
                                            <div class="h-100 d-flex flex-column">
                                                <img :src="sample.barcode" alt="" class="ld-img" :style="'height:' + barcodeTinggi() + 'px'">
                                                <div class="ld-teks text-center" x-show="elements.barcode.show_text" :style="teksStyle('barcode')" x-text="sample.code"></div>
                                            </div>
                                        </template>
                                        <template x-if="k === 'qr'">
                                            <div class="h-100 d-flex" :style="qrPosisi()">
                                                <img :src="sample.qr" alt="" :style="qrSisi()">
                                            </div>
                                        </template>
                                        <template x-if="k === 'logo'">
                                            <div class="h-100" :style="'text-align:' + elements.logo.align">
                                                <img x-show="sample.logo" :src="sample.logo" alt="" style="max-width:100%; max-height:100%;">
                                                <span x-show="! sample.logo" class="small text-muted">{{ __('Logo (unggah di Layout dokumen)') }}</span>
                                            </div>
                                        </template>
                                        <span class="ld-tag" x-text="names[k]"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                        <div class="small text-muted mt-2">
                            <span x-text="lebar + ' × ' + tinggi + ' mm'"></span> ·
                            {{ __('Klik elemen untuk memilih; seret untuk memindah; tarik tepinya untuk mengubah ukuran; panah = geser 0,5 mm (Shift = 5 mm).') }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card mb-3">
                    <div class="card-header"><strong>{{ __('Elemen') }}</strong></div>
                    <ul class="list-group list-group-flush">
                        <template x-for="k in urutan" :key="'li-' + k">
                            <li class="list-group-item d-flex align-items-center gap-2" :class="{ 'active': dipilih === k }" @click="dipilih = k" style="cursor: pointer;">
                                <input class="form-check-input mt-0" type="checkbox" :id="'ld-v-' + k" :checked="tampil(k)" :disabled="k === 'barcode' || k === 'qr'"
                                       @click.stop @change="elements[k].visible = $event.target.checked">
                                <span x-text="names[k]"></span>
                                <span class="ms-auto small" x-show="k === 'barcode' || k === 'qr'">{{ __('ikut pilihan kode') }}</span>
                            </li>
                        </template>
                    </ul>
                </div>

                <div class="card mb-3" x-show="dipilih">
                    <div class="card-header"><strong x-text="dipilih ? names[dipilih] : ''"></strong></div>
                    <div class="card-body row g-2" x-show="dipilih">
                        <template x-if="dipilih">
                            <div class="row g-2 m-0 p-0">
                                <template x-for="f in ['x', 'y', 'w', 'h']" :key="f">
                                    <div class="col-3">
                                        <label class="form-label small mb-0" :for="'ld-' + f" x-text="{x: 'X', y: 'Y', w: '{{ __('Lebar') }}', h: '{{ __('Tinggi') }}'}[f] + ' (mm)'"></label>
                                        <input class="form-control form-control-sm" type="number" step="0.5" min="0" :id="'ld-' + f"
                                               :value="elements[dipilih][f]" @change="ubahAngka(f, $event.target.value)">
                                    </div>
                                </template>
                                <div class="col-4" x-show="dipilih !== 'qr' && dipilih !== 'logo'">
                                    <label class="form-label small mb-0" for="ld-font">{{ __('Huruf (pt)') }}</label>
                                    <input class="form-control form-control-sm" type="number" step="0.5" min="4" max="72" id="ld-font"
                                           :value="elements[dipilih].font" @change="ubahAngka('font', $event.target.value)">
                                </div>
                                <div class="col-4" x-show="['title', 'subtitle', 'detail'].includes(dipilih)">
                                    <div class="form-check mt-4">
                                        <input class="form-check-input" type="checkbox" id="ld-bold" x-model="elements[dipilih].bold">
                                        <label class="form-check-label small" for="ld-bold">{{ __('Tebal') }}</label>
                                    </div>
                                </div>
                                <div class="col-4" x-show="dipilih === 'barcode'">
                                    <div class="form-check mt-4">
                                        <input class="form-check-input" type="checkbox" id="ld-showtext" x-model="elements.barcode.show_text">
                                        <label class="form-check-label small" for="ld-showtext">{{ __('Teks kode') }}</label>
                                    </div>
                                </div>
                                <div class="col-12" x-show="dipilih !== 'barcode'">
                                    <div class="btn-group btn-group-sm" role="group" aria-label="{{ __('Rata') }}">
                                        <button type="button" class="btn btn-outline-secondary" :class="{ active: elements[dipilih].align === 'left' }" @click="elements[dipilih].align = 'left'" title="{{ __('Rata kiri') }}"><i class="bi bi-text-left"></i></button>
                                        <button type="button" class="btn btn-outline-secondary" :class="{ active: elements[dipilih].align === 'center' }" @click="elements[dipilih].align = 'center'" title="{{ __('Rata tengah') }}"><i class="bi bi-text-center"></i></button>
                                        <button type="button" class="btn btn-outline-secondary" :class="{ active: elements[dipilih].align === 'right' }" @click="elements[dipilih].align = 'right'" title="{{ __('Rata kanan') }}"><i class="bi bi-text-right"></i></button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="ld-bawaan" x-model="jadikanBawaan" :disabled="isDefault">
                            <label class="form-check-label" for="ld-bawaan" x-text="isDefault ? '{{ __('Sudah ukuran bawaan jenis label ini') }}' : '{{ __('Jadikan ukuran bawaan jenis label ini') }}'"></label>
                        </div>
                        <div class="small text-muted mb-2" x-show="! saved">{{ __('Belum pernah disimpan: kanvas memakai tata letak otomatis.') }}</div>
                        <button class="btn btn-primary w-100" type="button" @click="simpan()" :disabled="menyimpan">
                            <i class="bi bi-save"></i> {{ __('Simpan desain') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
