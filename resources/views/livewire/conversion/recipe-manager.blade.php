<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Resep konversi') }}</h1>
            <p class="text-muted mb-0">{{ __('Pola potong atau hasil yang sering dipakai. Resep hanya mengisi form konversi; isiannya tetap bisa diubah sebelum disimpan.') }}</p>
        </div>
        <div class="d-flex gap-2">
            <a wire:navigate class="btn btn-outline-secondary" href="{{ route('conversions.index') }}">{{ __('Konversi material') }}</a>
            @if ($bisaUbah && $ubah === 0)
                <button class="btn btn-primary" type="button" wire:click="bukaForm">{{ __('Resep baru') }}</button>
            @endif
        </div>
    </div>

    @if ($galat !== '')
        <div class="alert alert-danger" role="alert">{{ $galat }} <span class="badge text-bg-dark ms-1">BR-CNV-06</span></div>
    @endif

    @if ($ubah !== 0)
        @php($jenisDipilih = $form['conversion_type'] ?? 'cut')
        <div class="card border-primary mb-3">
            <div class="card-header"><strong>{{ $ubah > 0 ? __('Ubah resep') : __('Resep baru') }}</strong></div>
            <div class="card-body row g-3">
                <div class="col-sm-4 col-lg-2">
                    <label class="form-label" for="resep-kode">{{ __('Kode') }} <span class="wajib">*</span></label>
                    <input class="form-control @error('form.code') is-invalid @enderror" id="resep-kode" type="text" maxlength="30" wire:model="form.code" placeholder="{{ __('mis. PIPA6-4X150') }}">
                    @error('form.code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-sm-8 col-lg-4">
                    <label class="form-label" for="resep-nama">{{ __('Nama') }} <span class="wajib">*</span></label>
                    <input class="form-control @error('form.name') is-invalid @enderror" id="resep-nama" type="text" maxlength="100" wire:model="form.name" placeholder="{{ __('mis. Pipa 6 m jadi 4 × 1,5 m') }}">
                    @error('form.name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label class="form-label" for="resep-jenis">{{ __('Jenis') }} <span class="wajib">*</span></label>
                    <select class="form-select @error('form.conversion_type') is-invalid @enderror" id="resep-jenis" wire:model.live="form.conversion_type">
                        @foreach ($jenis as $nilai => $label)
                            <option value="{{ $nilai }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('form.conversion_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-sm-6 col-lg-4">
                    <x-pilih model="form.input_item_id" id="resep-input" server :label="__('Item input (opsional)')" :kosong="__('Item apa saja')" :options="$opsiInput" galat="form.input_item_id" />
                </div>

                @if ($jenisDipilih === 'cut')
                    <div class="col-12">
                        <label class="form-label">{{ __('Pola potong per batang') }} <span class="wajib">*</span></label>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-1">
                                <thead><tr><th>{{ __('Item hasil') }}</th><th style="width: 9rem">{{ __('Ukuran') }}</th><th style="width: 7rem">{{ __('Jumlah') }}</th><th></th></tr></thead>
                                <tbody>
                                    @foreach ($form['potong'] ?? [] as $i => $p)
                                        <tr wire:key="resep-potong-{{ $p['id'] }}">
                                            <td style="min-width: 14rem"><x-pilih model="form.potong.{{ $i }}.item_id" id="resep-potong-item-{{ $i }}" server kecil :aria="__('Item hasil')" :kunci="count($form['potong'])" :options="$opsiPotong[$i] ?? []" :kosong="__('Sama dengan batang')" /></td>
                                            <td><input class="form-control form-control-sm" type="number" step="0.0001" min="0" wire:model="form.potong.{{ $i }}.length" aria-label="{{ __('Ukuran') }}"></td>
                                            <td><input class="form-control form-control-sm" type="number" step="1" min="1" wire:model="form.potong.{{ $i }}.count" aria-label="{{ __('Jumlah') }}"></td>
                                            <td class="text-end"><button class="btn btn-sm btn-outline-danger" type="button" wire:click="hapusPotong({{ $i }})" aria-label="{{ __('Hapus baris') }}"><i class="bi bi-x-lg"></i></button></td>
                                        </tr>
                                        @error('form.potong.'.$i) <tr><td colspan="4" class="text-danger small pt-0">{{ $message }}</td></tr> @enderror
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @error('form.potong') <div class="text-danger small">{{ $message }}</div> @enderror
                        <button class="btn btn-sm btn-outline-secondary" type="button" wire:click="tambahPotong"><i class="bi bi-plus-lg"></i> {{ __('Tambah ukuran') }}</button>
                    </div>
                @else
                    <div class="col-12">
                        <label class="form-label">{{ __('Hasil per satu input') }} <span class="wajib">*</span></label>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-1">
                                <thead><tr><th style="width: 8rem">{{ __('Jenis') }}</th><th>{{ __('Item') }}</th><th style="width: 9rem">{{ __('Jumlah') }}</th><th></th></tr></thead>
                                <tbody>
                                    @foreach ($form['hasil'] ?? [] as $i => $h)
                                        <tr wire:key="resep-hasil-{{ $h['id'] }}">
                                            <td>
                                                <select class="form-select form-select-sm" wire:model="form.hasil.{{ $i }}.kind" aria-label="{{ __('Jenis hasil') }}">
                                                    <option value="output">{{ __('Hasil') }}</option>
                                                    <option value="waste">{{ __('Waste') }}</option>
                                                </select>
                                            </td>
                                            <td style="min-width: 14rem"><x-pilih model="form.hasil.{{ $i }}.item_id" id="resep-hasil-item-{{ $i }}" server kecil :aria="__('Item')" :kunci="count($form['hasil'])" :options="$opsiHasil[$i] ?? []" :kosong="__('Pilih item…')" /></td>
                                            <td><input class="form-control form-control-sm" type="number" step="0.0001" min="0" wire:model="form.hasil.{{ $i }}.qty" aria-label="{{ __('Jumlah') }}"></td>
                                            <td class="text-end"><button class="btn btn-sm btn-outline-danger" type="button" wire:click="hapusHasil({{ $i }})" aria-label="{{ __('Hapus baris') }}"><i class="bi bi-x-lg"></i></button></td>
                                        </tr>
                                        @error('form.hasil.'.$i) <tr><td colspan="4" class="text-danger small pt-0">{{ $message }}</td></tr> @enderror
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @error('form.hasil') <div class="text-danger small">{{ $message }}</div> @enderror
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-outline-secondary" type="button" wire:click="tambahHasil('output')"><i class="bi bi-plus-lg"></i> {{ __('Tambah hasil') }}</button>
                            <button class="btn btn-sm btn-outline-secondary" type="button" wire:click="tambahHasil('waste')"><i class="bi bi-plus-lg"></i> {{ __('Tambah waste') }}</button>
                        </div>
                    </div>
                @endif
            </div>
            <div class="card-footer d-flex gap-2">
                <button class="btn btn-primary" type="button" wire:click="simpan">{{ __('Simpan resep') }}</button>
                <button class="btn btn-outline-secondary" type="button" wire:click="tutupForm">{{ __('Batal') }}</button>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-body border-bottom">
            <label class="form-label" for="cari-resep">{{ __('Cari') }}</label>
            <input class="form-control" id="cari-resep" type="search" wire:model.live.debounce.400ms="cari" placeholder="{{ __('Kode atau nama resep') }}" style="max-width: 24rem">
        </div>
        @if ($resep->isEmpty())
            <div class="card-body text-muted small">{{ __('Belum ada resep. Buat resep untuk pola potong atau hasil yang sering dipakai.') }}</div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>{{ __('Kode') }}</th><th>{{ __('Nama') }}</th><th>{{ __('Isi') }}</th><th>{{ __('Dipakai') }}</th><th class="text-end">{{ __('Aksi') }}</th></tr></thead>
                    <tbody>
                        @foreach ($resep as $r)
                            <tr wire:key="resep-{{ $r->id }}" @class(['text-muted' => ! $r->is_active])>
                                <td class="text-nowrap">{{ $r->code }} @unless ($r->is_active) <span class="badge text-bg-secondary">{{ __('Nonaktif') }}</span> @endunless</td>
                                <td>{{ $r->name }}@if ($masuk = $r->inputItem()) <div class="small text-muted">{{ __('Input: :i', ['i' => $masuk->code]) }}</div> @endif</td>
                                <td class="small">{{ $r->ringkasan() }}</td>
                                <td>{{ $dipakai[$r->id] ?? 0 }}×</td>
                                <td class="text-end">
                                    @if ($bisaUbah)
                                        <div class="d-flex justify-content-end gap-1">
                                            <button class="btn btn-sm btn-outline-secondary" type="button" wire:click="bukaForm({{ $r->id }})">{{ __('Ubah') }}</button>
                                            <button class="btn btn-sm btn-outline-secondary" type="button" wire:click="aktifkan({{ $r->id }}, {{ $r->is_active ? 'false' : 'true' }})">{{ $r->is_active ? __('Nonaktifkan') : __('Aktifkan') }}</button>
                                        </div>
                                    @else
                                        <span class="text-muted small">-</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
