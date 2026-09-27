<div class="card mb-3" id="label-kemasan">
    <div class="card-header d-flex flex-wrap align-items-center gap-2">
        <strong class="me-auto">{{ __('Label kemasan') }}</strong>
        @can('label.print')
            @if ($cetakPilih)
                <a class="btn btn-sm btn-outline-primary" href="{{ $cetakPilih }}" target="_blank" rel="noopener">{{ __('Cetak terpilih (:n)', ['n' => min(count($pilih), 200)]) }}</a>
            @endif
            @foreach ($cetakSemua as $i => $url)
                <a class="btn btn-sm btn-primary" href="{{ $url }}" target="_blank" rel="noopener">
                    {{ $cetakSemua->count() > 1 ? __('Cetak semua (bagian :n)', ['n' => $i + 1]) : __('Cetak semua label induk') }}
                </a>
            @endforeach
        @endcan
        @if ($belum->isNotEmpty())
            @can('receipt.complete')
                <button class="btn btn-sm btn-outline-success" type="button" wire:click="mintaBuat">{{ __('Buat label') }}</button>
            @endcan
        @endif
    </div>

    @if ($ruleError !== '')
        <div class="alert alert-danger m-3 mb-0" role="alert">
            {{ $ruleError }} @if ($ruleCode !== '') <span class="badge text-bg-dark ms-1">{{ $ruleCode }}</span> @endif
        </div>
    @endif

    @if ($dialog === 'buat')
        <div class="card-body border-bottom">
            <p class="small text-muted">{{ __('Barang Baik yang belum berlabel. Isi 0 dus bila tidak perlu dilabeli.') }}</p>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-2">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Item') }}</th>
                            <th scope="col" style="width: 9rem">{{ __('Jumlah dus') }}</th>
                            <th scope="col" style="width: 11rem">{{ __('Isi per dus') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($belum as $l)
                            <tr wire:key="lbl-buat-{{ $l->id }}">
                                <td>{{ $l->item?->code }} <span class="small text-muted">{{ $l->item?->name }}</span></td>
                                <td><input class="form-control form-control-sm" type="number" min="0" step="1" wire:model="buat.{{ $l->id }}.packages" aria-label="{{ __('Jumlah dus') }}"></td>
                                <td>
                                    <div class="input-group input-group-sm">
                                        <input class="form-control" type="number" min="0" step="0.0001" wire:model="buat.{{ $l->id }}.per_package" aria-label="{{ __('Isi per dus') }}">
                                        <span class="input-group-text">{{ $l->item?->baseUom?->code }}</span>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-success btn-sm" type="button" wire:click="buatLabel">{{ __('Buat label') }}</button>
                <button class="btn btn-outline-secondary btn-sm" type="button" wire:click="tutup">{{ __('Tutup') }}</button>
            </div>
        </div>
    @endif

    @if ($dialog === 'isi' && $labelDialog)
        <div class="card-body border-bottom">
            <strong>{{ __('Cetak label isi — :kode', ['kode' => $labelDialog->code]) }}</strong>
            <p class="small text-muted mb-2">
                {{ __('Isi kemasan') }} {{ \App\Domain\Master\Support\QtyFormat::withUnit($labelDialog->qty_remaining, $labelDialog->item?->baseUom?->code) }}
                {{ __('dibagi rata ke label isi; label isi terakhir berisi sisanya. Setelah itu yang dipindai saat keluar adalah label isinya.') }}
            </p>
            <label class="form-label" for="lbl-n-isi">{{ __('Jumlah label isi') }} <span class="wajib">*</span></label>
            <input class="form-control @error('nIsi') is-invalid @enderror" id="lbl-n-isi" type="number" min="1" step="1" style="max-width: 10rem" wire:model="nIsi">
            @error('nIsi') <div class="invalid-feedback">{{ $message }}</div> @enderror
            <div class="d-flex gap-2 mt-2">
                <button class="btn btn-primary btn-sm" type="button" wire:click="buatIsi">{{ __('Buat label isi') }}</button>
                <button class="btn btn-outline-secondary btn-sm" type="button" wire:click="tutup">{{ __('Tutup') }}</button>
            </div>
        </div>
    @endif

    @if ($dialog === 'batal' && $labelDialog)
        <div class="card-body border-bottom">
            <strong>{{ __('Batalkan label :kode', ['kode' => $labelDialog->code]) }}</strong>
            <p class="small text-muted mb-2">{{ __('Untuk label rusak/hilang atau barang yang sudah keluar tanpa dipindai. Stok tidak berubah; label isi yang masih Di gudang ikut batal.') }}</p>
            <div class="row g-2">
                <div class="col-md-5">
                    <label class="form-label" for="lbl-alasan">{{ __('Alasan') }} <span class="wajib">*</span></label>
                    <select class="form-select" id="lbl-alasan" wire:model="alasan">
                        <option value="">{{ __('Pilih alasan…') }}</option>
                        @foreach ($alasanBatal as $kode => $nama)
                            <option value="{{ $kode }}">{{ $nama }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-7">
                    <label class="form-label" for="lbl-catatan">{{ __('Keterangan') }}</label>
                    <input class="form-control" id="lbl-catatan" type="text" maxlength="255" wire:model="catatan" placeholder="{{ __('Opsional') }}">
                </div>
            </div>
            <div class="d-flex gap-2 mt-2">
                <button class="btn btn-warning btn-sm" type="button" wire:click="batalkan">{{ __('Batalkan label') }}</button>
                <button class="btn btn-outline-secondary btn-sm" type="button" wire:click="tutup">{{ __('Tutup') }}</button>
            </div>
        </div>
    @endif

    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th scope="col" style="width: 2rem"><span class="visually-hidden">{{ __('Pilih') }}</span></th>
                    <th scope="col">{{ __('Kode label') }}</th>
                    <th scope="col">{{ __('Item') }}</th>
                    <th class="text-end" scope="col">{{ __('Isi') }}</th>
                    <th scope="col">{{ __('Lokasi') }}</th>
                    <th scope="col">{{ __('Status') }}</th>
                    <th class="text-end" scope="col">{{ __('Aksi') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($induk as $l)
                    <tr wire:key="lbl-{{ $l->id }}">
                        <td>
                            @if ($l->status->value !== 'cancelled')
                                <input class="form-check-input" type="checkbox" value="{{ $l->id }}" id="lbl-pilih-{{ $l->id }}" wire:model.live="pilih">
                            @endif
                        </td>
                        <td class="font-monospace">
                            <label for="lbl-pilih-{{ $l->id }}"><a href="{{ route('labels.trace', ['code' => $l->code]) }}" wire:navigate>{{ $l->code }}</a></label>
                            @if ($l->children->isNotEmpty())
                                <div class="small text-muted">{{ __(':n label isi', ['n' => $l->children->count()]) }}
                                    · {{ __(':n di gudang', ['n' => $l->children->filter(fn ($c) => $c->status->value === 'in_stock')->count()]) }}</div>
                            @endif
                        </td>
                        <td>{{ $l->item?->code }} @if ($l->lot) <span class="small text-muted">· {{ __('Lot') }} {{ $l->lot->lot_no }}</span> @endif</td>
                        <td class="text-end">
                            {{ \App\Domain\Master\Support\QtyFormat::withUnit($l->qty, $l->item?->baseUom?->code) }}
                            @if ($l->packageUom) <div class="small text-muted">1 {{ $l->packageUom->code }}</div> @endif
                            @if ((float) $l->qty_remaining + 0.00005 < (float) $l->qty && $l->status->value === 'in_stock')
                                <div class="small text-muted">{{ __('sisa') }} {{ \App\Domain\Label\Support\PackageLabelLedger::angka((float) $l->qty_remaining) }}</div>
                            @endif
                        </td>
                        <td class="small">{{ $l->warehouse?->code }} · {{ $l->bin?->code ?? '—' }}</td>
                        <td><span class="badge {{ $l->status->badge() }}">{{ $l->status->label() }}</span></td>
                        <td class="text-end text-nowrap">
                            @can('label.print')
                                @if ($l->status->value !== 'cancelled')
                                    <a class="btn btn-sm btn-outline-primary" href="{{ $this->urlCetak([$l->id]) }}" target="_blank" rel="noopener">{{ __('Cetak') }}</a>
                                @endif
                                @if ($l->status->value === 'in_stock' && (float) $l->qty_remaining > 0.00005)
                                    <button class="btn btn-sm btn-outline-secondary" type="button" wire:click="mintaIsi({{ $l->id }})">{{ __('Cetak label isi') }}</button>
                                @endif
                                @if ($l->children->isNotEmpty())
                                    <a class="btn btn-sm btn-outline-secondary" href="{{ $this->urlCetak($l->children->filter(fn ($c) => $c->status->value !== 'cancelled')->pluck('id')->take(200)->all()) }}" target="_blank" rel="noopener">{{ __('Cetak isi (:n)', ['n' => $l->children->filter(fn ($c) => $c->status->value !== 'cancelled')->count()]) }}</a>
                                @endif
                            @endcan
                            @can('adjustment.approve')
                                @if ($l->status->value === 'in_stock')
                                    <button class="btn btn-sm btn-outline-danger" type="button" wire:click="mintaBatal({{ $l->id }})">{{ __('Batalkan') }}</button>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td class="text-center text-muted py-3" colspan="7">{{ __('Belum ada label kemasan untuk penerimaan ini.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
