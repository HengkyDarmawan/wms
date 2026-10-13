<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Pindah bin') }}</h1>
            <p class="text-muted mb-0">{{ __('Memindah barang antar rak/bin di gudang yang sama. Jumlah stok gudang tidak berubah, hanya letaknya.') }}</p>
        </div>
        {{-- A-414: pemandu di layar. --}}
        <x-tur kunci="pindah-bin" :langkah="[
            ['el' => '', 'judul' => __('Pindah bin'), 'isi' => __('Dipakai saat merapikan rak: barang dipindah dari satu bin ke bin lain di gudang yang sama. Tidak perlu approval; tercatat di kartu stok.')],
            ['el' => '[data-langkah-asal]', 'judul' => __('1. Bin asal'), 'isi' => __('Pindai QR label bin, ketik kode pendeknya (mis. R01 L1 01), atau pilih dari daftar. Hanya bin yang berisi barang yang muncul.')],
            ['el' => '[data-langkah-barang]', 'judul' => __('2. Barang & jumlah'), 'isi' => __('Klik barang yang mau dipindah. Jumlah terisi semua isinya; kurangi bila hanya sebagian. Barang bernomor seri dan potongan selalu pindah utuh.')],
            ['el' => '[data-langkah-tujuan]', 'judul' => __('3. Bin tujuan'), 'isi' => __('Pindai atau pilih bin tujuan. Bin khusus barang lain akan ditolak.')],
            ['el' => '[data-simpan-pindah]', 'judul' => __('4. Simpan'), 'isi' => __('Dianggap selesai bila muncul tulisan hijau Dipindah… dan baris baru di Riwayat pindah bin di bawah.')],
        ]" />
    </div>

    @if ($terakhir !== '')
        <div class="alert alert-success py-2" role="status" data-pindah-berhasil>{{ $terakhir }}</div>
    @endif
    @foreach ($peringatan as $p)
        <div class="alert alert-warning py-2" role="alert">{{ $p }}</div>
    @endforeach
    @if ($ruleError !== '')
        <div class="alert alert-danger" role="alert">{{ $ruleError }}</div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            @if ($gudangList->count() > 1)
                <div class="row g-3 mb-1">
                    <div class="col-md-6">
                        <x-pilih model="gudangId" id="pindah-gudang" live wajib :label="__('Gudang')" :kosong="__('Pilih gudang…')"
                                 :options="$gudangList->map(fn ($g) => ['value' => $g->id, 'text' => $g->code.' — '.$g->name])->all()" />
                    </div>
                </div>
            @endif

            @if ($gudangId !== '')
                <div class="row g-3 mt-1" data-langkah-asal>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="pindah-pindai-asal">{{ __('1. Bin asal') }}</label>
                        <input class="form-control @error('pindaiAsal') is-invalid @enderror" id="pindah-pindai-asal" type="text" autocomplete="off"
                               placeholder="{{ __('Pindai QR bin atau ketik kode pendek…') }}"
                               wire:model="pindaiAsal" wire:keydown.enter.prevent="pindai('asal')" wire:change="pindai('asal')">
                        @error('pindaiAsal') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <x-pilih model="asalId" id="pindah-asal" live :label="__('atau pilih bin berisi barang')" :kosong="__('Pilih bin asal…')" :options="$opsiAsal" />
                        @if ($opsiAsal === [])
                            <div class="form-text" data-belum-ada-isi>{{ __('Belum ada bin berisi barang di gudang ini. Stok masuk lewat Penerimaan barang atau Saldo awal.') }}</div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>

    @if ($asalId !== '')
        <div class="card mb-3" data-langkah-barang>
            <div class="card-header"><strong>{{ __('2. Barang di bin asal') }}</strong></div>
            <div class="list-group list-group-flush">
                @forelse ($saldo as $s)
                    <label class="list-group-item list-group-item-action d-flex align-items-start gap-2" for="pindah-saldo-{{ $s->id }}">
                        <input class="form-check-input mt-1" type="radio" id="pindah-saldo-{{ $s->id }}" value="{{ $s->id }}" wire:model.live="form.balance_id">
                        <span class="flex-grow-1">
                            <span class="fw-semibold">{{ $s->item->code }}</span> — {{ $s->item->name }}
                            <span class="d-block small text-muted">
                                @if ($s->lot) {{ __('Lot') }} {{ $s->lot->lot_no }} @endif
                                @if ($s->serial) {{ __('Serial') }} {{ $s->serial->serial_no }} @endif
                                @if ($s->piece) {{ __('Potongan') }} {{ \App\Domain\Master\Support\QtyFormat::number($s->piece->length) }} @endif
                                @if ($s->stock_status->value !== 'available') <span class="badge text-bg-warning">{{ $s->stock_status->label() }}</span> @endif
                            </span>
                        </span>
                        <span class="text-nowrap fw-semibold">{{ \App\Domain\Master\Support\QtyFormat::withUnit($s->qty_base, $s->item->baseUom?->code) }}</span>
                    </label>
                @empty
                    <div class="list-group-item text-muted">{{ __('Bin ini kosong.') }}</div>
                @endforelse
            </div>
            @error('form.balance_id') <div class="text-danger small px-3 pb-2">{{ $message }}</div> @enderror
        </div>
    @endif

    @if ($terpilih !== null)
        <div class="card mb-3" data-langkah-tujuan>
            <div class="card-body row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-semibold" for="pindah-jumlah">{{ __('Jumlah dipindah') }} ({{ $terpilih->item->baseUom?->code }})</label>
                    <input class="form-control @error('form.qty') is-invalid @enderror" id="pindah-jumlah" type="number" step="any" min="0"
                           wire:model="form.qty" @disabled($utuh)>
                    @if ($utuh) <div class="form-text">{{ __('Pindah utuh.') }}</div> @endif
                    @error('form.qty') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold" for="pindah-pindai-tujuan">{{ __('3. Bin tujuan') }}</label>
                    <input class="form-control @error('pindaiTujuan') is-invalid @enderror" id="pindah-pindai-tujuan" type="text" autocomplete="off"
                           placeholder="{{ __('Pindai QR bin atau ketik kode pendek…') }}"
                           wire:model="pindaiTujuan" wire:keydown.enter.prevent="pindai('tujuan')" wire:change="pindai('tujuan')">
                    @error('pindaiTujuan') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-5">
                    <x-pilih model="form.to_bin_id" id="pindah-tujuan" :label="__('atau pilih bin tujuan')" :kosong="__('Pilih bin tujuan…')" :options="$opsiTujuan" />
                </div>
                <div class="col-12">
                    <label class="form-label" for="pindah-catatan">{{ __('Catatan (opsional)') }}</label>
                    <input class="form-control" id="pindah-catatan" type="text" maxlength="200" wire:model="form.notes" placeholder="{{ __('mis. merapikan rak R01') }}">
                </div>
            </div>
            <div class="card-footer">
                <button class="btn btn-primary" type="button" wire:click="simpan" data-simpan-pindah>
                    <i class="bi bi-arrow-left-right"></i> {{ __('Pindahkan') }}
                </button>
            </div>
        </div>
    @endif

    @if ($riwayat->isNotEmpty())
        <div class="card">
            <div class="card-header"><strong>{{ __('Riwayat pindah bin') }}</strong> <span class="text-muted small">— {{ __('10 terakhir di gudang ini') }}</span></div>
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Waktu') }}</th>
                            <th scope="col">{{ __('Barang') }}</th>
                            <th scope="col" class="text-end">{{ __('Jumlah') }}</th>
                            <th scope="col">{{ __('Dari') }}</th>
                            <th scope="col">{{ __('Ke') }}</th>
                            <th scope="col">{{ __('Oleh') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($riwayat as $m)
                            <tr data-riwayat-pindah>
                                <td class="text-nowrap">{{ $m->occurred_at?->lokal()->format('d/m/Y H:i') }}</td>
                                <td>{{ $m->item?->code }}</td>
                                <td class="text-end text-nowrap">{{ \App\Domain\Master\Support\QtyFormat::withUnit($m->qty_base, $m->item?->baseUom?->code) }}</td>
                                <td class="font-monospace small">{{ $m->fromBin?->code }}</td>
                                <td class="font-monospace small">{{ $m->toBin?->code }}</td>
                                <td>{{ $m->performer?->name }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
