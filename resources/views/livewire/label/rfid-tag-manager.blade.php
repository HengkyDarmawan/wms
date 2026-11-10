<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Tag RFID') }}</h1>
            <p class="text-muted mb-0">{{ __('Reader mode keyboard wedge mengetik kode tag seperti pemindai barcode. Tag yang terdaftar langsung dikenali di semua layar pindai barang dan bin.') }}</p>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header"><strong>{{ __('Daftarkan tag') }}</strong></div>
                <div class="card-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="rfid-epc">{{ __('Kode tag (EPC)') }} <span class="wajib">*</span></label>
                        <input class="form-control font-monospace @error('form.epc') is-invalid @enderror" id="rfid-epc" type="text" maxlength="80" autocomplete="off"
                               wire:model="form.epc" wire:keydown.enter.prevent="simpan" placeholder="{{ __('Arahkan reader ke tag') }}">
                        @error('form.epc') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="rfid-jenis">{{ __('Untuk') }} <span class="wajib">*</span></label>
                        <select class="form-select @error('form.target_type') is-invalid @enderror" id="rfid-jenis" wire:model="form.target_type">
                            @foreach ($target as $k => $label) <option value="{{ $k }}">{{ $label }}</option> @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="rfid-target">{{ __('Kode target') }} <span class="wajib">*</span></label>
                        <input class="form-control @error('form.target_code') is-invalid @enderror" id="rfid-target" type="text" data-scan wire:model="form.target_code" placeholder="{{ __('Kode / nomor') }}">
                        @error('form.target_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-12">
                        <input class="form-control" type="text" maxlength="255" wire:model="form.notes" placeholder="{{ __('Catatan (opsional)') }}" aria-label="{{ __('Catatan') }}">
                    </div>
                    <div class="col-12 d-flex gap-2 align-items-center">
                        <button class="btn btn-primary" type="button" wire:click="simpan">{{ __('Daftarkan') }}</button>
                        <span class="small text-muted">{{ __('Setelah tersimpan, kotak EPC dikosongkan dan target tetap: pindai tag berikutnya untuk target yang sama.') }}</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header"><strong>{{ __('Uji pindai') }}</strong></div>
                <div class="card-body">
                    <label class="form-label" for="rfid-uji">{{ __('Pindai tag atau kode') }}</label>
                    <input class="form-control font-monospace" id="rfid-uji" type="text" wire:model="uji" wire:keydown.enter.prevent="ujiPindai" autocomplete="off">
                    @if ($hasilUji !== '') <div class="alert alert-info py-2 mt-2 mb-0 small" role="status">{{ $hasilUji }}</div> @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body border-bottom">
            <input class="form-control" type="search" wire:model.live.debounce.400ms="cari" placeholder="{{ __('Cari EPC') }}" aria-label="{{ __('Cari EPC') }}" style="max-width: 24rem">
        </div>
        @if ($tags->isEmpty())
            <div class="card-body text-muted small">{{ __('Belum ada tag RFID terdaftar.') }}</div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>{{ __('EPC') }}</th><th>{{ __('Untuk') }}</th><th>{{ __('Catatan') }}</th><th class="text-end">{{ __('Aksi') }}</th></tr></thead>
                    <tbody>
                        @foreach ($tags as $t)
                            <tr wire:key="rfid-{{ $t->id }}" @class(['text-muted' => ! $t->is_active])>
                                <td class="font-monospace small">{{ $t->epc }} @unless ($t->is_active) <span class="badge text-bg-secondary">{{ __('Nonaktif') }}</span> @endunless</td>
                                <td>{{ $target[$t->target_type] ?? $t->target_type }} · {{ $kode[$t->id] ?? '-' }}</td>
                                <td class="small">{{ $t->notes ?? '-' }}</td>
                                <td class="text-end"><button class="btn btn-sm btn-outline-secondary" type="button" wire:click="aktifkan({{ $t->id }}, {{ $t->is_active ? 'false' : 'true' }})">{{ $t->is_active ? __('Nonaktifkan') : __('Aktifkan') }}</button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
