<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Peta approval') }}</h1>
            <p class="text-muted mb-0">{{ __('Siapa menyetujui apa, dan siapa atasan siapa. Hanya membaca — ubah lewat Aturan approval dan Struktur organisasi.') }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-secondary" href="{{ route('approval.rules.index') }}">{{ __('Aturan approval') }}</a>
            @can('org.view')
                <a class="btn btn-outline-secondary" href="{{ route('org.index') }}">{{ __('Struktur organisasi') }}</a>
            @endcan
        </div>
    </div>

    {{-- 1. Siapa menyetujui apa (A-348: kalimat yang sama dengan daftar aturan). --}}
    <div class="card mb-3">
        <div class="card-header"><strong>{{ __('1. Siapa menyetujui apa') }}</strong></div>
        <ul class="list-group list-group-flush">
            @foreach ($baris as $b)
                <li class="list-group-item" wire:key="peta-{{ $b['jenis']->value }}">
                    <div class="fw-semibold">{{ $b['jenis']->longLabel() }}</div>
                    @if ($b['aturan'] === [])
                        <div class="small text-muted">{{ $b['tanpa'] }}</div>
                    @else
                        @foreach ($b['aturan'] as $a)
                            <div class="small">
                                @if (count($b['aturan']) > 1)<span class="text-muted">{{ $loop->iteration }}.</span>@endif
                                {{ $a['kalimat'] }}
                                @if ($a['dasar']) <span class="badge text-bg-info">{{ __('Dasar') }}</span> @endif
                            </div>
                        @endforeach
                        @if (count($b['aturan']) > 1)
                            <div class="small text-muted">{{ __('Diperiksa berurutan; yang pertama cocok dipakai.') }}</div>
                        @endif
                    @endif
                </li>
            @endforeach
        </ul>
    </div>

    {{-- 2. Bagan jabatan (A-344). --}}
    <div class="card mb-3">
        <div class="card-header"><strong>{{ __('2. Bagan jabatan') }}</strong> <span class="text-muted small">{{ __('— pemegang jabatan atasan menjadi atasan langsung, kecuali diisi manual di pengguna') }}</span></div>
        <div class="card-body">
            @forelse ($bagan as $b)
                <div class="py-1" style="padding-left: {{ $b['depth'] * 1.5 }}rem" wire:key="bagan-{{ $b['jabatan']->id }}">
                    @if ($b['depth'] > 0)<span class="text-muted">└</span>@endif
                    <strong>{{ $b['jabatan']->name }}</strong>
                    <span class="text-muted small">· {{ $b['unit']?->name ?? '—' }} · {{ __('level :n', ['n' => $b['jabatan']->level]) }}</span>
                    — {{ $b['pemegang'] === [] ? __('belum ada pemegang') : implode(', ', $b['pemegang']) }}
                </div>
            @empty
                <p class="text-muted mb-0">{{ __('Belum ada jabatan. Tambahkan di Struktur organisasi.') }}</p>
            @endforelse
        </div>
    </div>

    {{-- 3. Cek untuk orang (A-349): simulasi yang sama dengan pengajuan sungguhan. --}}
    <div class="card mb-3">
        <div class="card-header"><strong>{{ __('3. Cek untuk orang') }}</strong></div>
        <div class="card-body row g-3 align-items-end">
            <div class="col-md-3">
                <x-pilih model="pemohon" id="cek-pemohon" server wajib :label="__('Pemohon')" :kosong="__('Pilih orang…')"
                         :options="$opsiPemohon" />
            </div>
            <div class="col-md-3">
                <label class="form-label" for="cek-jenis">{{ __('Dokumen') }} <span class="wajib">*</span></label>
                <select class="form-select @error('jenis') is-invalid @enderror" id="cek-jenis" wire:model="jenis">
                    @foreach ($types as $nilai => $label) <option value="{{ $nilai }}">{{ $label }}</option> @endforeach
                </select>
                @error('jenis') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-2">
                <x-pilih model="gudang" id="cek-gudang" :label="__('Gudang')" :kosong="__('— opsional —')"
                         :options="$warehouses->map(fn ($w) => ['value' => $w->id, 'text' => $w->code, 'sub' => $w->name])->all()" />
            </div>
            <div class="col-md-2">
                <x-pilih model="proyek" id="cek-proyek" server :label="__('Proyek')" :kosong="__('— opsional —')" :options="$opsiProyek" />
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100" type="button" wire:click="cek">{{ __('Cek') }}</button>
            </div>
        </div>
        @if ($ruleError !== '')
            <div class="card-body border-top"><div class="alert alert-danger mb-0">{{ $ruleError }}</div></div>
        @endif
        @if ($hasil !== null)
            <div class="card-body border-top">
                @if ($hasil['rule'])
                    <p class="mb-2">{{ __('Aturan dipakai') }}: <strong>{{ $hasil['rule']['name'] }}</strong></p>
                @endif
                @include('livewire.approval.partials.simulation-result', ['hasil' => $hasil])
            </div>
        @endif
    </div>
</div>
