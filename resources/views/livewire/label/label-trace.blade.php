<div>
    <div class="card mb-3">
        <div class="card-body">
            <label class="form-label" for="trace-kode">{{ __('Kode label kemasan') }}</label>
            <div class="input-group">
                <input class="form-control font-monospace" id="trace-kode" type="text" data-scan autocomplete="off" autofocus
                       wire:model="code" wire:keydown.enter.prevent="cari" placeholder="{{ __('Pindai/ketik kode label, kode item, nomor lot, atau nomor GRN') }}">
                <button class="btn btn-primary" type="button" wire:click="cari">{{ __('Telusuri') }}</button>
            </div>
            <div class="form-text">{{ __('Label induk mis. PAKU-0001, label isi mis. PAKU-0001-0003.') }}</div>
        </div>
    </div>

    @if ($label)
        @php($uom = $label->item?->baseUom?->code)
        <div class="row g-3 mb-3">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header d-flex align-items-center gap-2">
                        <strong class="font-monospace me-auto">{{ $label->code }}</strong>
                        <span class="badge {{ $label->status->badge() }}">{{ $label->status->label() }}</span>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0 small">
                            <dt class="col-sm-4">{{ __('Jenis') }}</dt>
                            <dd class="col-sm-8">
                                {{ $label->isParent() ? __('Label induk (kemasan)') : __('Label isi') }}
                                @if ($label->parent)
                                    · {{ __('dari') }} <a href="{{ route('labels.trace', ['code' => $label->parent->code]) }}" wire:navigate class="font-monospace">{{ $label->parent->code }}</a>
                                @endif
                            </dd>
                            <dt class="col-sm-4">{{ __('Item') }}</dt>
                            <dd class="col-sm-8">{{ $label->item?->code }} {{ $label->item?->name }}</dd>
                            <dt class="col-sm-4">{{ __('Isi') }}</dt>
                            <dd class="col-sm-8">
                                {{ \App\Domain\Master\Support\QtyFormat::withUnit($label->qty, $uom) }}
                                @if ($label->packageUom) (1 {{ $label->packageUom->code }}) @endif
                                · {{ __('sisa') }} {{ \App\Domain\Master\Support\QtyFormat::withUnit($label->qty_remaining, $uom) }}
                            </dd>
                            @if ($label->lot)
                                <dt class="col-sm-4">{{ __('Lot') }}</dt>
                                <dd class="col-sm-8">{{ $label->lot->lot_no }}
                                    @if ($label->lot->expiry_date) · {{ __('Kedaluwarsa') }} {{ $label->lot->expiry_date->format('d/m/Y') }} @endif
                                </dd>
                            @endif
                            @if ($asal['batch'])
                                <dt class="col-sm-4">{{ __('Batch vendor') }}</dt>
                                <dd class="col-sm-8">{{ $asal['batch'] }}</dd>
                            @endif
                            <dt class="col-sm-4">{{ __('Lokasi terakhir') }}</dt>
                            <dd class="col-sm-8">{{ $label->warehouse?->code }} {{ $label->warehouse?->name }} · {{ $label->bin?->code ?? '—' }}</dd>
                            @if ($label->cancelReason)
                                <dt class="col-sm-4">{{ __('Alasan batal') }}</dt>
                                <dd class="col-sm-8">{{ $label->cancelReason->label }} · {{ $label->cancelled_at?->format('d/m/Y H:i') }}</dd>
                            @endif
                        </dl>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header"><strong>{{ __('Asal barang') }}</strong></div>
                    <div class="card-body">
                        <dl class="row mb-0 small">
                            <dt class="col-sm-4">{{ __('Penerimaan') }}</dt>
                            <dd class="col-sm-8">
                                @if ($asal['grnEntry'])
                                    <a href="{{ $asal['grnEntry']['url'] }}" wire:navigate>{{ $asal['grnEntry']['number'] }}</a>
                                @else
                                    {{ $asal['grn']?->number ?? '—' }}
                                @endif
                            </dd>
                            <dt class="col-sm-4">{{ __('Vendor') }}</dt>
                            <dd class="col-sm-8">{{ $asal['vendor'] ?? '—' }}</dd>
                            <dt class="col-sm-4">{{ __('Diterima') }}</dt>
                            <dd class="col-sm-8">{{ $asal['diterima']?->format('d/m/Y H:i') ?? '—' }}</dd>
                            <dt class="col-sm-4">{{ __('Catatan pemesanan') }}</dt>
                            <dd class="col-sm-8">
                                {{ $asal['order']?->reference() ?? '—' }}
                                @if ($asal['order']?->ordered_at) · {{ __('dipesan') }} {{ $asal['order']->ordered_at->format('d/m/Y') }} @endif
                            </dd>
                            <dt class="col-sm-4">{{ __('Dokumen asal') }}</dt>
                            <dd class="col-sm-8">
                                @forelse ($asal['dokumen'] as $d)
                                    <a class="me-2" href="{{ $d['url'] }}" wire:navigate><span class="badge text-bg-light border">{{ $d['jenis'] }}</span> {{ $d['number'] }}</a>
                                @empty
                                    —
                                @endforelse
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        @if ($label->children->isNotEmpty())
            <div class="card mb-3">
                <div class="card-header"><strong>{{ __('Label isi (:n)', ['n' => $label->children->count()]) }}</strong></div>
                <div class="card-body d-flex flex-wrap gap-2">
                    @foreach ($label->children as $c)
                        <a class="badge {{ $c->status->badge() }} font-monospace text-decoration-none" href="{{ route('labels.trace', ['code' => $c->code]) }}" wire:navigate>
                            {{ $c->code }} · {{ \App\Domain\Label\Support\PackageLabelLedger::angka((float) $c->qty_remaining) }}/{{ \App\Domain\Label\Support\PackageLabelLedger::angka((float) $c->qty) }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="card mb-3">
            <div class="card-header"><strong>{{ __('Riwayat label') }}</strong></div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Waktu') }}</th>
                            <th scope="col">{{ __('Kejadian') }}</th>
                            <th class="text-end" scope="col">{{ __('Jumlah') }}</th>
                            <th scope="col">{{ __('Dokumen') }}</th>
                            <th scope="col">{{ __('Lokasi') }}</th>
                            <th scope="col">{{ __('Oleh') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($riwayat as $r)
                            @php($m = $r['move'])
                            <tr wire:key="trace-{{ $m->id }}">
                                <td class="small text-nowrap">{{ $m->occurred_at?->format('d/m/Y H:i') }}</td>
                                <td>{{ __($r['kejadian']) }}
                                    @if ($m->reason) <div class="small text-muted">{{ $m->reason->label }}</div> @endif
                                    @if ($m->notes && $m->notes !== 'transfer') <div class="small text-muted">{{ $m->notes }}</div> @endif
                                </td>
                                <td class="text-end">{{ (float) $m->qty_change > 0 ? '+' : '' }}{{ \App\Domain\Label\Support\PackageLabelLedger::angka((float) $m->qty_change) }}</td>
                                <td class="small">
                                    @if ($r['dokumen'])
                                        <a href="{{ $r['dokumen']['url'] }}" wire:navigate>{{ $r['dokumen']['number'] }}</a>
                                    @else
                                        {{ $m->document_number ?? '—' }}
                                    @endif
                                    @if ($r['sj'])
                                        · <a href="{{ $r['sj']['url'] }}" wire:navigate>{{ $r['sj']['number'] }}</a>
                                    @endif
                                </td>
                                <td class="small">{{ $m->warehouse?->code }} @if ($m->fromBin || $m->toBin) · {{ $m->fromBin?->code ?? '—' }} → {{ $m->toBin?->code ?? '—' }} @endif</td>
                                <td class="small">{{ $m->performer?->name ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td class="text-center text-muted py-3" colspan="6">{{ __('Belum ada riwayat.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @elseif ($kode !== '')
        <div class="card mb-3">
            <div class="card-header"><strong>{{ __('Label terkait ":kode"', ['kode' => $kode]) }}</strong></div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Kode label') }}</th>
                            <th scope="col">{{ __('Item') }}</th>
                            <th class="text-end" scope="col">{{ __('Sisa') }}</th>
                            <th scope="col">{{ __('GRN') }}</th>
                            <th scope="col">{{ __('Lokasi') }}</th>
                            <th scope="col">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($daftar as $l)
                            <tr wire:key="trace-l-{{ $l->id }}">
                                <td class="font-monospace"><a href="{{ route('labels.trace', ['code' => $l->code]) }}" wire:navigate>{{ $l->code }}</a></td>
                                <td>{{ $l->item?->code }}</td>
                                <td class="text-end">{{ \App\Domain\Master\Support\QtyFormat::withUnit($l->qty_remaining, $l->item?->baseUom?->code) }}</td>
                                <td class="small">{{ $l->receipt?->number }}</td>
                                <td class="small">{{ $l->warehouse?->code }} · {{ $l->bin?->code ?? '—' }}</td>
                                <td><span class="badge {{ $l->status->badge() }}">{{ $l->status->label() }}</span></td>
                            </tr>
                        @empty
                            <tr><td class="text-center text-muted py-3" colspan="6">{{ __('Kode ini bukan label kemasan, item, lot, atau GRN yang berlabel.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
