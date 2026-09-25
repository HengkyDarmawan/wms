<div>
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Pengaturan company') }}</h1>
            <p class="text-muted mb-0">{{ __('Ambang waktu, saklar fitur, dan zona waktu untuk seluruh company. Perubahan tercatat di riwayat.') }}</p>
        </div>
        @if ($bolehUbah)
            <button class="btn btn-primary" type="button" wire:click="simpan"><i class="bi bi-check2"></i> {{ __('Simpan') }}</button>
        @endif
    </div>

    @if ($ruleError !== '')
        <div class="alert alert-danger">{{ $ruleError }}</div>
    @endif
    @if (! $bolehUbah)
        <div class="alert alert-info py-2 small">{{ __('Anda hanya bisa melihat; pengubahan butuh izin Ubah pengaturan company.') }}</div>
    @endif

    <div class="row g-3">
        <div class="col-lg-7">
            @foreach ($grup as $judul => $kunciKunci)
                <div class="card mb-3">
                    <div class="card-header"><strong>{{ __($judul) }}</strong></div>
                    <div class="card-body">
                        @foreach ($kunciKunci as $kunci)
                            @php($def = $katalog[$kunci])
                            <div class="row g-2 align-items-center mb-3">
                                <div class="col-md-7">
                                    <label class="form-label mb-0" for="set-{{ $kunci }}">{{ __($def['label']) }}</label>
                                    <div class="small text-muted">{{ __($def['hint']) }}</div>
                                </div>
                                <div class="col-md-5">
                                    <div class="input-group input-group-sm">
                                        <input class="form-control @error('nilai.'.$kunci) is-invalid @enderror" id="set-{{ $kunci }}" type="number"
                                               step="{{ $def['desimal'] ? 'any' : '1' }}" min="{{ $def['min'] }}" max="{{ $def['max'] }}"
                                               wire:model="nilai.{{ $kunci }}" @disabled(! $bolehUbah)>
                                        <span class="input-group-text">{{ __($def['satuan']) }}</span>
                                        @error('nilai.'.$kunci) <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="form-text">{{ __('Bawaan') }} {{ $def['default'] }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div class="card mb-3">
                <div class="card-header"><strong>{{ __('Zona waktu & periode') }}</strong></div>
                <div class="card-body">
                    <div class="row g-2 align-items-center mb-3">
                        <div class="col-md-7">
                            <label class="form-label mb-0" for="set-timezone">{{ __('Zona waktu company') }}</label>
                            <div class="small text-muted">{{ __('Waktu disimpan UTC dan ditampilkan di zona ini.') }}</div>
                        </div>
                        <div class="col-md-5">
                            <select class="form-select form-select-sm @error('timezone') is-invalid @enderror" id="set-timezone" wire:model="timezone" @disabled(! $bolehUbah)>
                                @foreach ($zona as $kode => $label)
                                    <option value="{{ $kode }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('timezone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="row g-2 align-items-center">
                        <div class="col-md-7">
                            <div class="fw-semibold">{{ __('Kunci periode stok') }}</div>
                            <div class="small text-muted">{{ __('Diubah lewat layar Kunci periode, bukan di sini.') }}</div>
                        </div>
                        <div class="col-md-5">
                            <span class="badge text-bg-light border">{{ $kunciPeriode ? \Illuminate\Support\Carbon::parse($kunciPeriode)->format('d/m/Y') : __('belum dikunci') }}</span>
                            @if (\Illuminate\Support\Facades\Route::has('stock.period'))
                                <a class="small ms-2" href="{{ route('stock.period') }}">{{ __('Buka') }}</a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header"><strong>{{ __('Saklar fitur') }}</strong> <span class="small text-muted">{{ __('lapis company; item menentukan pemakaiannya') }}</span></div>
                <div class="card-body">
                    @foreach ($daftarFitur as $kunci => $def)
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" role="switch" id="fitur-{{ $kunci }}"
                                   wire:model="fitur.{{ $kunci }}" @disabled(! $bolehUbah || $def['tetap'])>
                            <label class="form-check-label" for="fitur-{{ $kunci }}">
                                {{ __($def['label']) }}
                                @if (($pemakai[$kunci] ?? 0) > 0)
                                    <span class="badge text-bg-warning ms-1" title="{{ __('Mematikan saklar menyembunyikan layar & kolomnya; data item tidak dihapus.') }}">{{ __('dipakai :n item', ['n' => $pemakai[$kunci]]) }}</span>
                                @endif
                            </label>
                            <div class="small text-muted">{{ __($def['hint']) }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
