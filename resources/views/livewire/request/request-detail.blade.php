<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">
                {{ $req->number }}
                <span class="badge {{ $req->status->badge() }}">{{ $req->status->label() }}</span>
            </h1>
            <p class="text-muted mb-0">
                {{ $req->project?->code }} — {{ $req->project?->name }} ·
                {{ __('Pemohon') }}: {{ $req->requester?->name ?? '—' }} ({{ $req->requester_type->label() }}) ·
                {{ __('Dibutuhkan') }}: {{ $req->required_date?->format('d/m/Y') }}
                @if ($req->parent)
                    · {{ __('Tambahan dari') }} <a href="{{ route('requests.show', $req->parent) }}">{{ $req->parent->number }}</a>
                @endif
            </p>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="{{ route('requests.index') }}">{{ __('Kembali') }}</a>
            @can('update', $req)
                <a class="btn btn-outline-primary" href="{{ route('requests.edit', $req) }}">{{ __('Ubah') }}</a>
            @endcan
        </div>
    </div>

    @if ($ruleError !== '')
        <div class="alert alert-danger" role="alert">
            {{ $ruleError }}
            @if ($ruleCode !== '')
                <span class="badge text-bg-dark ms-1">{{ $ruleCode }}</span>
            @endif
        </div>
    @endif

    {{-- Tolak, batal, tutup dengan sisa: satu bentuk dialog, tiga maksud. --}}
    @if (in_array($dialog, ['tolak', 'batal', 'tutup'], true))
        <div class="card border-warning mb-3">
            <div class="card-header">
                <strong>
                    @switch($dialog)
                        @case('tolak') {{ __('Tolak permintaan') }} @break
                        @case('batal') {{ __('Batalkan permintaan') }} @break
                        @default {{ __('Tutup dengan sisa') }}
                    @endswitch
                </strong>
            </div>
            <div class="card-body row g-3">
                <div class="col-md-5">
                    <label class="form-label" for="req-alasan">{{ __('Alasan') }} <span class="wajib">*</span></label>
                    <select class="form-select @error('reasonCode') is-invalid @enderror" id="req-alasan"
                            wire:model="reasonCode">
                        <option value="">{{ __('Pilih alasan…') }}</option>
                        @foreach ($alasan as $kode => $label)
                            <option value="{{ $kode }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('reasonCode') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-7">
                    <label class="form-label" for="req-keterangan">{{ __('Keterangan') }}</label>
                    <input class="form-control" id="req-keterangan" type="text" wire:model="reasonNotes"
                           placeholder="{{ __('Opsional') }}">
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <button class="btn btn-warning" type="button" wire:click="jalankanDialog">{{ __('Jalankan') }}</button>
                <button class="btn btn-outline-secondary" type="button" wire:click="tutupDialog">{{ __('Batal') }}</button>
            </div>
        </div>
    @endif

    @if ($dialog === 'petakan')
        <div class="card border-primary mb-3">
            <div class="card-header"><strong>{{ __('Petakan baris ke item') }}</strong></div>
            <div class="card-body row g-3">
                <div class="col-md-12">
                    <label class="form-label" for="petakan-item">{{ __('Item yang sudah ada') }}</label>
                    <select class="form-select" id="petakan-item" wire:model="form.item_id">
                        <option value="">{{ __('— buat item sementara —') }}</option>
                        @foreach ($items as $item)
                            <option value="{{ $item->id }}">{{ $item->code }} — {{ $item->name }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">
                        {{ __('Bila tidak ada yang cocok, isi tiga kolom di bawah untuk membuat item sementara.') }}
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="petakan-kode">{{ __('Kode item') }}</label>
                    <input class="form-control" id="petakan-kode" type="text" wire:model="form.item_code">
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="petakan-nama">{{ __('Nama item') }}</label>
                    <input class="form-control" id="petakan-nama" type="text" wire:model="form.item_name">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="petakan-satuan">{{ __('Satuan dasar') }}</label>
                    <select class="form-select" id="petakan-satuan" wire:model="form.base_uom_id">
                        <option value="">{{ __('Pilih satuan…') }}</option>
                        @foreach ($uoms as $uom)
                            <option value="{{ $uom->id }}">{{ $uom->code }} — {{ $uom->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <button class="btn btn-primary" type="button" wire:click="petakan">{{ __('Petakan') }}</button>
                <button class="btn btn-outline-secondary" type="button" wire:click="tutupDialog">{{ __('Batal') }}</button>
            </div>
        </div>
    @endif

    @if ($dialog === 'pecah')
        <div class="card border-primary mb-3">
            <div class="card-header d-flex align-items-center justify-content-between">
                <strong>{{ __('Pecah baris ke beberapa gudang') }}</strong>
                <button class="btn btn-sm btn-outline-primary" type="button" wire:click="tambahPecahan">
                    {{ __('Tambah pecahan') }}
                </button>
            </div>
            <div class="card-body">
                <p class="text-muted small">
                    {{ __('Sisa untuk baris asal dihitung otomatis; total pecahan harus kurang dari jumlah baris.') }}
                </p>
                @foreach ($splits as $i => $pecahan)
                    <div class="row g-2 mb-2" wire:key="pecah-{{ $i }}">
                        <div class="col-md-7">
                            <select class="form-select" wire:model="splits.{{ $i }}.warehouse_id">
                                <option value="">{{ __('Pilih gudang…') }}</option>
                                @foreach ($warehouses as $gudang)
                                    <option value="{{ $gudang->id }}">{{ $gudang->code }} — {{ $gudang->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-5">
                            <input class="form-control" type="number" step="0.0001" min="0"
                                   wire:model="splits.{{ $i }}.qty_base" placeholder="{{ __('Jumlah') }}">
                        </div>
                    </div>
                @endforeach
                @error('form.qty_base') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>
            <div class="card-footer d-flex gap-2">
                <button class="btn btn-primary" type="button" wire:click="pecah">{{ __('Pecah baris') }}</button>
                <button class="btn btn-outline-secondary" type="button" wire:click="tutupDialog">{{ __('Batal') }}</button>
            </div>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-header"><strong>{{ __('Baris permintaan') }}</strong></div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Barang') }}</th>
                        <th class="text-end" scope="col">{{ __('Jumlah') }}</th>
                        <th class="text-end" scope="col" title="{{ __('Terkirim / diterima baik') }}">{{ __('Terkirim / Diterima') }}</th>
                        <th scope="col">{{ __('Gudang sumber') }}</th>
                        <th scope="col">{{ __('Cara pemenuhan') }}</th>
                        <th scope="col">{{ __('Tanggal janji') }}</th>
                        <th scope="col">{{ __('Status') }}</th>
                        <th class="text-end" scope="col">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($lines as $l)
                        <tr wire:key="detail-baris-{{ $l->id }}"
                            @class([
                                'table-secondary' => $l->status->value === 'cancelled',
                                'table-warning' => $l->awaitsCancelConfirmation(),
                            ])>
                            <td>
                                {{ $l->displayName() }}
                                @unless ($l->isMapped())
                                    <span class="badge text-bg-warning">{{ __('Belum dipetakan') }}</span>
                                @endunless
                                @if ($l->isSubstituted())
                                    <div class="small text-muted">
                                        {{ __('Semula') }}: {{ $l->original_item_text }} ·
                                        {{ $l->substitution_response?->label() ?? __('menunggu tanggapan klien') }}
                                    </div>
                                @endif
                                @if ($l->splitParent)
                                    <span class="badge text-bg-light">{{ __('Pecahan') }}</span>
                                @endif
                            </td>
                            <td class="text-end">{{ number_format((float) $l->qty_base, 2, ',', '.') }}</td>
                            <td class="text-end text-nowrap">
                                {{ number_format((float) $l->qty_shipped, 2, ',', '.') }}
                                <span class="text-muted">/</span>
                                {{ number_format((float) $l->qty_received, 2, ',', '.') }}
                            </td>
                            <td>
                                @can('review', $req)
                                    <select class="form-select form-select-sm"
                                            wire:change="simpanSumber({{ $l->id }}, $event.target.value, '{{ $l->fulfillment_source?->value }}', '{{ $l->promised_date?->toDateString() }}')">
                                        <option value="">{{ __('— belum —') }}</option>
                                        @foreach ($warehouses as $gudang)
                                            <option value="{{ $gudang->id }}" @selected($l->source_warehouse_id === $gudang->id)>
                                                {{ $gudang->code }}
                                            </option>
                                        @endforeach
                                    </select>
                                @else
                                    {{ $l->sourceWarehouse?->code ?? '—' }}
                                @endcan
                            </td>
                            <td>
                                @can('review', $req)
                                    <select class="form-select form-select-sm"
                                            wire:change="simpanSumber({{ $l->id }}, '{{ $l->source_warehouse_id }}', $event.target.value, '{{ $l->promised_date?->toDateString() }}')">
                                        <option value="">{{ __('— belum —') }}</option>
                                        @foreach ($sources as $nilai => $label)
                                            <option value="{{ $nilai }}" @selected($l->fulfillment_source?->value === $nilai)>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                @else
                                    {{ $l->fulfillment_source?->label() ?? '—' }}
                                @endcan
                            </td>
                            <td>
                                @can('review', $req)
                                    <input class="form-control form-control-sm" type="date"
                                           value="{{ $l->promised_date?->toDateString() }}"
                                           wire:change="simpanSumber({{ $l->id }}, '{{ $l->source_warehouse_id }}', '{{ $l->fulfillment_source?->value }}', $event.target.value)">
                                @else
                                    {{ $l->promised_date?->format('d/m/Y') ?? '—' }}
                                @endcan
                            </td>
                            <td>
                                <span class="badge text-bg-light">{{ $l->status->label() }}</span>
                                @if ($l->awaitsCancelConfirmation())
                                    <div class="small text-danger">{{ __('Klien minta batal') }}</div>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                @if ($l->status->value === 'open')
                                    @can('review', $req)
                                        <button class="btn btn-sm btn-outline-primary" type="button"
                                                wire:click="mintaPetakan({{ $l->id }})">
                                            {{ __('Petakan') }}
                                        </button>
                                    @endcan
                                    @can('splitLine', $req)
                                        <button class="btn btn-sm btn-outline-secondary" type="button"
                                                wire:click="mintaPecah({{ $l->id }})">
                                            {{ __('Pecah') }}
                                        </button>
                                    @endcan
                                    @can('confirmCancel', $req)
                                        @if ($l->awaitsCancelConfirmation())
                                            <button class="btn btn-sm btn-danger" type="button"
                                                    wire:click="konfirmasiBatalBaris({{ $l->id }})">
                                                {{ __('Setujui batal') }}
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" type="button"
                                                    wire:click="tolakBatalBaris({{ $l->id }})">
                                                {{ __('Tolak batal') }}
                                            </button>
                                        @endif
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-muted py-4" colspan="8">{{ __('Belum ada baris.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="card-footer d-flex flex-wrap gap-2">
            @can('review', $req)
                <button class="btn btn-primary" type="button" wire:click="kirimKeApproval">
                    {{ __('Kirim ke persetujuan') }}
                </button>
                <button class="btn btn-outline-danger" type="button" wire:click="mintaDialog('tolak')">
                    {{ __('Tolak') }}
                </button>
            @endcan

            @can('approve', $req)
                <button class="btn btn-success" type="button" wire:click="setujui">{{ __('Setujui') }}</button>
                <button class="btn btn-outline-danger" type="button" wire:click="mintaDialog('tolak')">
                    {{ __('Tolak') }}
                </button>
            @endcan

            @can('closeShort', $req)
                @if (in_array($req->status->value, ['in_progress', 'partially_fulfilled'], true))
                    <button class="btn btn-outline-dark" type="button" wire:click="mintaDialog('tutup')">
                        {{ __('Tutup dengan sisa') }}
                    </button>
                @endif
            @endcan

            @can('cancel', $req)
                @unless ($req->status->isFinal())
                    <button class="btn btn-outline-danger" type="button" wire:click="mintaDialog('batal')">
                        {{ __('Batalkan REQ') }}
                    </button>
                @endunless
            @endcan
        </div>
    </div>

    @if ($supplements->isNotEmpty())
        <div class="card mb-3">
            <div class="card-header"><strong>{{ __('REQ Tambahan') }}</strong></div>
            <ul class="list-group list-group-flush">
                @foreach ($supplements as $s)
                    <li class="list-group-item d-flex justify-content-between">
                        <a href="{{ route('requests.show', $s->id) }}">{{ $s->number }}</a>
                        <span class="badge {{ $s->status->badge() }}">{{ $s->status->label() }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($transferBackorder->isNotEmpty())
        <div class="card mb-3">
            <div class="card-header"><strong>{{ __('Transfer backorder') }}</strong></div>
            <ul class="list-group list-group-flush small">
                @foreach ($transferBackorder as $t)
                    <li class="list-group-item">
                        @can('view', $t)
                            <a href="{{ route('transfers.show', $t) }}">{{ $t->number }}</a>
                        @else
                            {{ $t->number }}
                        @endcan
                        · {{ $t->fromWarehouse?->code }} → {{ $t->toWarehouse?->code }}
                        · <span class="badge {{ $t->status->badge() }}">{{ $t->status->label() }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @include('request.partials.deliveries', ['req' => $req])

    {{-- A-252: dokumen asal & turunan. --}}
    @unless ($portal ?? false)
        <x-related-documents :document="$req" />
    @endunless
    @include('approval.partials.history', ['riwayatApproval' => $riwayatApproval])

    <div class="card">
        <div class="card-header"><strong>{{ __('Riwayat') }}</strong></div>
        <ul class="list-group list-group-flush">
            @forelse ($riwayat as $log)
                <li class="list-group-item">
                    <div class="d-flex justify-content-between">
                        <span>{{ $log->description }}</span>
                        <span class="text-muted small">{{ $log->created_at?->lokal()->format('d/m/Y H:i') }}</span>
                    </div>
                    <div class="small text-muted">{{ $log->causer?->name ?? __('Sistem') }}</div>
                </li>
            @empty
                <li class="list-group-item text-muted">{{ __('Belum ada riwayat.') }}</li>
            @endforelse
        </ul>
    </div>
</div>
