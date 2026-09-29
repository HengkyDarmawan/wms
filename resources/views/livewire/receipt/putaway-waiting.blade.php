<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Menunggu dimasukkan') }}</h1>
            <p class="text-muted mb-0">{{ __('Pindai label barang, lalu pindai QR bin tujuan.') }}</p>
        </div>
        <a class="btn btn-outline-secondary" href="{{ route('putaways.index') }}">{{ __('Tugas put-away') }}</a>
    </div>

    @if ($terakhir !== '')
        <div class="alert alert-success py-2" role="status">{{ $terakhir }}</div>
    @endif
    @foreach ($peringatan as $p)
        <div class="alert alert-warning py-2" role="alert">{{ $p }}</div>
    @endforeach
    @if ($ruleError !== '')
        <div class="alert alert-danger" role="alert">
            {{ $ruleError }}
            @if ($ruleCode !== '') <span class="badge text-bg-dark ms-1">{{ $ruleCode }}</span> @endif
        </div>
    @endif

    @if ($baris === null)
        <div class="card mb-3">
            <div class="card-body">
                @if ($gudangList->count() > 1)
                    <x-pilih model="gudang" id="mdm-gudang" class="mb-3" live :label="__('Gudang')" :kosong="__('Semua gudang saya')"
                             :options="$gudangList->map(fn ($g) => ['value' => $g->id, 'text' => $g->code.' — '.$g->name])->all()" />
                @endif
                <label class="form-label fw-semibold" for="mdm-barang">{{ __('1. Pindai label barang') }}</label>
                <input class="form-control form-control-lg @error('kodeBarang') is-invalid @enderror" id="mdm-barang" type="text" data-scan
                       autocomplete="off" autofocus wire:model="kodeBarang" wire:keydown.enter.prevent="pindaiBarang"
                       placeholder="{{ __('Label kemasan, lot, serial, atau kode item') }}">
                @error('kodeBarang') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-2">
            <strong>{{ __(':n baris menunggu', ['n' => $jumlah]) }}</strong>
            @if ($saring !== [])
                <button class="btn btn-sm btn-link" type="button" wire:click="batalPilih">{{ __('Tampilkan semua') }}</button>
            @endif
        </div>
        <div class="list-group mb-3">
            @forelse ($daftar as $l)
                <button class="list-group-item list-group-item-action py-3" type="button" wire:key="mdm-{{ $l->id }}" wire:click="mulai({{ $l->id }})">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div class="text-start">
                            <div class="fw-semibold">{{ $l->item?->code }} <span class="fw-normal text-muted">{{ $l->item?->name }}</span></div>
                            <div class="small">{{ $qty($l) }}
                                @if ($l->lot || $l->serial || $l->piece) · {{ $l->lot?->lot_no ?? $l->serial?->serial_no ?? $l->piece?->piece_no }} @endif
                            </div>
                            <div class="small text-muted">{{ $l->task?->number }} · {{ __('dari') }} {{ $l->fromBin?->code }}</div>
                        </div>
                        <div class="text-end">
                            <div class="small text-muted">{{ __('Taruh di') }}</div>
                            <div class="fs-5 fw-bold font-monospace">{{ $l->suggestedBin ? ($pendek[$l->suggested_bin_id] ?? $l->suggestedBin->code) : '—' }}</div>
                            @if ($penuh[$l->id] ?? false) <span class="badge text-bg-warning">{{ __('Tempat simpan penuh') }}</span> @endif
                        </div>
                    </div>
                </button>
            @empty
                <div class="list-group-item text-center text-muted py-4">{{ __('Tidak ada barang yang menunggu dimasukkan.') }}</div>
            @endforelse
        </div>
        @if ($jumlah >= \App\Domain\Receipt\Support\PutawayTargets::MAKS)
            <p class="small text-muted">{{ __('Menampilkan :n baris pertama; pindai label untuk mencari yang lain.', ['n' => $jumlah]) }}</p>
        @endif
    @else
        <div class="card mb-3 border-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between gap-2">
                    <div>
                        <div class="fw-semibold fs-5">{{ $baris->item?->code }}</div>
                        <div class="text-muted">{{ $baris->item?->name }}</div>
                        <div>{{ $qty($baris) }}
                            @if ($baris->lot || $baris->serial || $baris->piece) · {{ $baris->lot?->lot_no ?? $baris->serial?->serial_no ?? $baris->piece?->piece_no }} @endif
                        </div>
                        <div class="small text-muted">{{ $baris->task?->number }} · {{ __('dari') }} {{ $baris->fromBin?->code }}</div>
                    </div>
                    <button class="btn btn-outline-secondary align-self-start" type="button" wire:click="batalPilih">{{ __('Ganti barang') }}</button>
                </div>

                <div class="text-center my-4">
                    <div class="text-muted">{{ __('Taruh di') }}</div>
                    @if ($baris->suggestedBin)
                        <div class="display-5 fw-bold font-monospace" data-bin-tujuan>{{ $pendek[$baris->suggested_bin_id] ?? $baris->suggestedBin->code }}</div>
                        <div class="small text-muted font-monospace">{{ $baris->suggestedBin->code }}</div>
                    @else
                        <div class="fs-4 text-muted">{{ __('Tidak ada saran — pilih bin sendiri') }}</div>
                    @endif
                    @if ($penuh[$baris->id] ?? false)
                        <div class="mt-1"><span class="badge text-bg-warning">{{ __('Tempat simpan barang ini penuh — saran dari aturan lama') }}</span></div>
                    @endif
                </div>

                @if ($bolehTaruh)
                    <label class="form-label fw-semibold" for="mdm-bin">{{ __('2. Pindai QR bin') }}</label>
                    <input class="form-control form-control-lg @error('kodeBin') is-invalid @enderror" id="mdm-bin" type="text" data-scan
                           autocomplete="off" autofocus wire:model="kodeBin" wire:keydown.enter.prevent="pindaiBin"
                           placeholder="{{ __('QR bin, kode bin, atau kode pendek') }}">
                    @error('kodeBin') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror

                    @if ($binLainModel)
                        <div class="alert alert-warning mt-3 mb-0">
                            <div class="mb-2">{{ __('Bin yang dipindai bukan saran:') }} <strong class="font-monospace">{{ $pendek[$binLainModel->id] ?? $binLainModel->code }}</strong></div>
                            <label class="form-label small" for="mdm-alasan">{{ __('Alasan menaruh di bin lain') }} <span class="wajib">*</span></label>
                            <input class="form-control @error('alasan') is-invalid @enderror" id="mdm-alasan" type="text" maxlength="255" wire:model="alasan"
                                   wire:keydown.enter.prevent="taruhBinLain" placeholder="{{ __('Mis. bin saran terhalang') }}">
                            @error('alasan') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            <button class="btn btn-warning btn-lg w-100 mt-2" type="button" wire:click="taruhBinLain">
                                {{ __('Taruh di :bin', ['bin' => $pendek[$binLainModel->id] ?? $binLainModel->code]) }}
                            </button>
                        </div>
                    @endif
                    <div class="mt-3">@include('livewire.warehouse.partials.buka-khusus')</div>
                @else
                    <p class="text-muted mb-0">{{ __('Anda hanya bisa melihat; menaruh barang butuh izin menyelesaikan put-away.') }}</p>
                @endif
            </div>
        </div>
    @endif
</div>
