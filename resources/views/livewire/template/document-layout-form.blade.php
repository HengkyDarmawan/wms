<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Layout dokumen') }}</h1>
            <p class="text-muted mb-0">
                {{ __('Kop, footer, dan kotak tanda tangan yang dipakai semua dokumen cetak. Cetakan tidak pernah memuat harga.') }}
            </p>
        </div>
        <a class="btn btn-outline-secondary" href="{{ route('document-layout.preview') }}" target="_blank" rel="noopener">
            <i class="bi bi-file-earmark-pdf"></i> {{ __('Contoh cetak') }}
        </a>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><strong>{{ __('Kop & footer') }}</strong></div>
                <div class="card-body row g-3">
                    <div class="col-md-8">
                        <label class="form-label" for="lay-nama">{{ __('Nama layout') }}</label>
                        <input class="form-control @error('name') is-invalid @enderror" id="lay-nama" type="text"
                               maxlength="80" wire:model="name">
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="lay-warna">{{ __('Warna aksen') }}</label>
                        <div class="input-group">
                            <input class="form-control form-control-color" type="color" aria-label="{{ __('Pilih warna') }}"
                                   wire:model.live="accent">
                            <input class="form-control @error('accent') is-invalid @enderror" id="lay-warna" type="text"
                                   maxlength="7" wire:model.live="accent">
                        </div>
                        @error('accent') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="lay-kop">{{ __('Teks kop') }}</label>
                        <textarea class="form-control @error('header') is-invalid @enderror" id="lay-kop" rows="3"
                                  maxlength="500" wire:model="header"
                                  placeholder="{{ __('Alamat, telepon, email: satu per baris') }}"></textarea>
                        <div class="form-text">{{ __('Nama company tercetak otomatis di atas teks ini. Teks biasa, tanpa HTML.') }}</div>
                        @error('header') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="lay-footer">{{ __('Footer') }}</label>
                        <input class="form-control @error('footer') is-invalid @enderror" id="lay-footer" type="text"
                               maxlength="300" wire:model="footer">
                        @error('footer') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header"><strong>{{ __('Kotak tanda tangan') }}</strong></div>
                <div class="card-body">
                    <p class="text-muted small">
                        {{ __('Satu label per baris, paling banyak 4. Nama pelaku yang tercatat di sistem ikut tercetak menurut urutan kotak.') }}
                    </p>
                    <div class="row g-3">
                        @foreach ($dokumen as $type)
                            @continue($type->isStub())
                            <div class="col-md-6">
                                <label class="form-label" for="blok-{{ $type->value }}">{{ $type->label() }}</label>
                                <textarea class="form-control @error('signatureBlocks.'.$type->value) is-invalid @enderror"
                                          id="blok-{{ $type->value }}" rows="3"
                                          wire:model="blocks.{{ $type->value }}"></textarea>
                                @error('signatureBlocks.'.$type->value) <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card">
                <div class="card-header"><strong>{{ __('Logo') }}</strong></div>
                <div class="card-body">
                    @if ($layout->logo_path)
                        <img src="{{ route('document-layout.logo') }}?v={{ $layout->updated_at?->timestamp }}"
                             alt="{{ __('Logo dokumen') }}" class="img-fluid border rounded mb-2" style="max-height: 80px;">
                        <form method="POST" action="{{ route('document-layout.logo.destroy') }}" class="mb-3">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger" type="submit">{{ __('Hapus logo') }}</button>
                        </form>
                    @else
                        <p class="text-muted small">{{ __('Belum ada logo.') }}</p>
                    @endif
                    <form method="POST" action="{{ route('document-layout.logo.store') }}" enctype="multipart/form-data">
                        @csrf
                        <label class="form-label" for="lay-logo">{{ __('Unggah logo') }}</label>
                        <input class="form-control" id="lay-logo" type="file"
                               name="logo" accept="image/png,image/jpeg" required>
                        <div class="form-text">{{ __('PNG atau JPEG, maks 20 MB; dikecilkan otomatis.') }}</div>
                        <button class="btn btn-sm btn-outline-primary mt-2" type="submit">{{ __('Simpan logo') }}</button>
                    </form>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>{{ __('Template') }}</strong>
                    <span class="small text-muted">{{ __('Atur teks: tambahkan kalimat, syarat, atau catatan di atas/bawah isi dokumen.') }}</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead>
                            <tr><th>{{ __('Jenis') }}</th><th>{{ __('Kertas') }}</th><th class="text-end">{{ __('Teks') }}</th></tr>
                        </thead>
                        <tbody>
                            @foreach ([$dokumen] as $kelompok)
                                @foreach ($kelompok as $type)
                                    <tr>
                                        <td>
                                            {{ $type->label() }}
                                            <span class="badge text-bg-light">{{ $type->isStub() ? __('Menunggu modulnya') : (in_array($type->value, $berteks, true) ? __('Teks kustom') : __('Bawaan')) }}</span>
                                        </td>
                                        <td style="min-width: 11rem;">
                                            <select class="form-select form-select-sm @error('papers.'.$type->value) is-invalid @enderror"
                                                    aria-label="{{ __('Kertas') }} {{ $type->label() }}"
                                                    wire:model="papers.{{ $type->value }}">
                                                @foreach ($kertasDokumen as $kertas)
                                                    <option value="{{ $kertas->value }}">{{ $kertas->label() }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="text-end">
                                            @unless ($type->isStub())
                                                <button class="btn btn-sm btn-outline-secondary" type="button" wire:click="aturTeks('{{ $type->value }}')">{{ __('Atur teks') }}</button>
                                            @endunless
                                        </td>
                                    </tr>
                                    @if ($editType === $type->value)
                                        <tr>
                                            <td colspan="3" class="bg-body-tertiary">
                                                <label class="form-label mt-2" for="teks-template">{{ __('Teks template :jenis', ['jenis' => $type->label()]) }}</label>
                                                <textarea class="form-control font-monospace @error('body') is-invalid @enderror" id="teks-template" rows="8" wire:model="editBody"
                                                          placeholder="&lt;p&gt;Dikirim untuk proyek {proyek}.&lt;/p&gt;&#10;{isi_bawaan}&#10;&lt;p&gt;&lt;small&gt;Barang diterima dalam keadaan baik.&lt;/small&gt;&lt;/p&gt;"></textarea>
                                                @error('body') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                                <div class="form-text">{{ __('Boleh teks biasa atau HTML sederhana (p, b, i, u, tabel, daftar). Skrip, gambar, dan tautan luar dibuang. Tanpa {isi_bawaan}, teks muncul di atas isi dokumen.') }}</div>
                                                <details class="small mt-2">
                                                    <summary>{{ __('Variabel yang bisa dipakai') }}</summary>
                                                    <ul class="mb-0 mt-1">
                                                        @foreach ($variabel as $kunci => $ket)
                                                            <li><code>{{ '{'.$kunci.'}' }}</code> · {{ $ket }}</li>
                                                        @endforeach
                                                    </ul>
                                                </details>
                                                <div class="d-flex flex-wrap gap-2 my-2">
                                                    <button class="btn btn-sm btn-primary" type="button" wire:click="simpanTeks">{{ __('Simpan teks') }}</button>
                                                    <a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" href="{{ route('document-layout.preview', ['type' => $type->value]) }}">{{ __('Pratinjau PDF') }}</a>
                                                    <button class="btn btn-sm btn-outline-danger" type="button" wire:click="simpanTeks(true)" wire:confirm="{{ __('Hapus teks kustom dan kembali ke bawaan?') }}">{{ __('Kembalikan ke bawaan') }}</button>
                                                    <button class="btn btn-sm btn-link" type="button" wire:click="tutupTeks">{{ __('Tutup') }}</button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                            @endforeach
                            <tr>
                                <td>{{ __('Label bin, item, lot, potongan') }}</td>
                                <td>
                                    <a wire:navigate href="{{ route('label-designs.index') }}">{{ __('Desain label') }}</a> ·
                                    <a wire:navigate href="{{ route('label-formats.index') }}">{{ __('Ukuran label') }}</a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-3">
        <button class="btn btn-primary" type="button" wire:click="save">{{ __('Simpan layout') }}</button>
    </div>
</div>
