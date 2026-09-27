<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Ukuran label') }}</h1>
            <p class="text-muted mb-0">
                {{ __('Daftar ukuran kertas label yang dipakai company: gulungan thermal atau lembar berisi beberapa label. Tata letak isinya diatur di Desain label.') }}
            </p>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="{{ route('label-designs.index') }}"><i class="bi bi-vector-pen"></i> {{ __('Desain label') }}</a>
            <button class="btn btn-primary" type="button" wire:click="baru"><i class="bi bi-plus-lg"></i> {{ __('Tambah ukuran') }}</button>
        </div>
    </div>

    @error('format')
        <div class="alert alert-danger" role="alert">{{ $message }}</div>
    @enderror

    @if ($showForm)
        <div class="card mb-3" wire:key="form-{{ $editingId ?? 'baru' }}">
            <div class="card-header"><strong>{{ $editingId ? __('Ubah ukuran :kode', ['kode' => $form['code']]) : __('Ukuran label baru') }}</strong></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-lg-7">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label" for="fmt-kode">{{ __('Kode') }} <span class="wajib">*</span></label>
                                <input class="form-control text-uppercase @error('code') is-invalid @enderror" id="fmt-kode" wire:model="form.code" maxlength="20" @disabled($editingId) placeholder="THERMAL-60X40">
                                @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-8">
                                <label class="form-label" for="fmt-nama">{{ __('Nama') }} <span class="wajib">*</span></label>
                                <input class="form-control @error('name') is-invalid @enderror" id="fmt-nama" wire:model="form.name" maxlength="80" placeholder="{{ __('Thermal 60×40 mm') }}">
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-12">
                                <span class="form-label d-block">{{ __('Bentuk kertas') }} <span class="wajib">*</span></span>
                                @foreach ($media as $nilai => $nama)
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" id="fmt-media-{{ $nilai }}" value="{{ $nilai }}" wire:model.live="form.media">
                                        <label class="form-check-label" for="fmt-media-{{ $nilai }}">{{ $nama }}</label>
                                    </div>
                                @endforeach
                                @error('media') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label" for="fmt-w">{{ __('Lebar label (mm)') }} <span class="wajib">*</span></label>
                                <input class="form-control @error('width_mm') is-invalid @enderror" id="fmt-w" inputmode="decimal" wire:model.live.debounce.500ms="form.width_mm">
                                @error('width_mm') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label" for="fmt-h">{{ __('Tinggi label (mm)') }} <span class="wajib">*</span></label>
                                <input class="form-control @error('height_mm') is-invalid @enderror" id="fmt-h" inputmode="decimal" wire:model.live.debounce.500ms="form.height_mm">
                                @error('height_mm') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            @if (($form['media'] ?? '') === 'sheet')
                                <div class="col-12">
                                    <span class="small text-muted me-2">{{ __('Halaman:') }}</span>
                                    <button class="btn btn-sm btn-outline-secondary" type="button" wire:click="halaman('a4')">A4</button>
                                    <button class="btn btn-sm btn-outline-secondary" type="button" wire:click="halaman('letter')">Letter</button>
                                    <button class="btn btn-sm btn-outline-secondary" type="button" wire:click="halaman('f4')">F4</button>
                                    <button class="btn btn-sm btn-outline-secondary" type="button" wire:click="tengahkan">{{ __('Tengahkan susunan') }}</button>
                                </div>
                                @foreach ([
                                    'page_width_mm' => __('Lebar halaman (mm)'), 'page_height_mm' => __('Tinggi halaman (mm)'),
                                    'columns' => __('Kolom'), 'rows' => __('Baris'),
                                    'margin_top_mm' => __('Margin atas (mm)'), 'margin_left_mm' => __('Margin kiri (mm)'),
                                    'gap_x_mm' => __('Jarak antar kolom (mm)'), 'gap_y_mm' => __('Jarak antar baris (mm)'),
                                ] as $kunci => $judul)
                                    <div class="col-6 col-md-3">
                                        <label class="form-label" for="fmt-{{ $kunci }}">{{ $judul }} @if (in_array($kunci, ['page_width_mm', 'page_height_mm', 'columns', 'rows'], true)) <span class="wajib">*</span> @endif</label>
                                        <input class="form-control @error($kunci) is-invalid @enderror" id="fmt-{{ $kunci }}" inputmode="decimal" wire:model.live.debounce.500ms="form.{{ $kunci }}">
                                        @error($kunci) <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="small text-muted mb-1">{{ __('Pratinjau susunan') }}</div>
                        @if ($pratinjau)
                            @php
                                [$pw, $ph] = $pratinjau->pageSize();
                                $skala = 260 / max($pw, $ph);
                            @endphp
                            <svg class="border bg-white" width="{{ round($pw * $skala) }}" height="{{ round($ph * $skala) }}" viewBox="0 0 {{ $pw }} {{ $ph }}" role="img" aria-label="{{ __('Pratinjau susunan label') }}">
                                @foreach ($pratinjau->positions() as [$x, $y])
                                    <rect x="{{ $x }}" y="{{ $y }}" width="{{ $pratinjau->width_mm }}" height="{{ $pratinjau->height_mm }}"
                                          fill="{{ $x + $pratinjau->width_mm > $pw + 0.01 || $y + $pratinjau->height_mm > $ph + 0.01 ? '#f8d7da' : '#e7f1ff' }}" stroke="#0d6efd" stroke-width="{{ max($pw, $ph) / 300 }}" rx="1"></rect>
                                @endforeach
                            </svg>
                            <div class="small text-muted mt-1">{{ $pratinjau->summary() }}</div>
                        @else
                            <div class="text-muted small">{{ __('Isi ukuran yang sah untuk melihat pratinjau.') }}</div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <button class="btn btn-primary" type="button" wire:click="simpan">{{ __('Simpan ukuran') }}</button>
                <button class="btn btn-outline-secondary" type="button" wire:click="batal">{{ __('Batal') }}</button>
                @if ($editingId && ($desain[$editingId] ?? 0) > 0)
                    <span class="small text-muted align-self-center">{{ __('Mengubah ukuran menata ulang elemen desain yang keluar batas.') }}</span>
                @endif
            </div>
        </div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>{{ __('Kode') }}</th>
                        <th>{{ __('Nama') }}</th>
                        <th>{{ __('Ukuran') }}</th>
                        <th>{{ __('Bawaan untuk') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-end">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($formats as $f)
                        <tr wire:key="fmt-{{ $f->id }}" class="{{ $f->is_active ? '' : 'text-muted' }}">
                            <td class="font-monospace">{{ $f->code }}</td>
                            <td>{{ $f->name }}</td>
                            <td>{{ $f->summary() }}</td>
                            <td>{{ implode(', ', $bawaan[$f->id] ?? []) ?: '—' }}</td>
                            <td><span class="badge {{ $f->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $f->is_active ? __('Aktif') : __('Nonaktif') }}</span></td>
                            <td class="text-end text-nowrap">
                                @if ($f->is_active)
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('label-designs.index', ['format' => $f->id]) }}">{{ __('Desain') }}</a>
                                @endif
                                <button class="btn btn-sm btn-outline-secondary" type="button" wire:click="ubah({{ $f->id }})">{{ __('Ubah') }}</button>
                                @if ($f->is_active)
                                    <button class="btn btn-sm btn-outline-danger" type="button" wire:click="aktifkan({{ $f->id }}, false)">{{ __('Nonaktifkan') }}</button>
                                @else
                                    <button class="btn btn-sm btn-outline-success" type="button" wire:click="aktifkan({{ $f->id }}, true)">{{ __('Aktifkan') }}</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
