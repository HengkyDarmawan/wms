<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">
                {{ $sj->number }}
                <span class="badge {{ $sj->status->badge() }}">{{ $sj->status->label() }}</span>
            </h1>
            <p class="text-muted mb-0">
                {{ $sj->warehouse?->code }} → {{ $sj->destinationLabel() }} ·
                {{ $sj->shipment_method->label() }}: {{ $sj->carrierLabel() }}
                @if ($sj->shipped_at)
                    · {{ __('Berangkat') }} {{ $sj->shipped_at->format('d/m/Y H:i') }}
                @endif
            </p>
        </div>
        <a class="btn btn-outline-secondary" href="{{ route('shipments.index') }}">{{ __('Kembali') }}</a>
    </div>

    @if ($ruleError !== '')
        <div class="alert alert-danger" role="alert">
            {{ $ruleError }}
            @if ($ruleCode !== '')
                <span class="badge text-bg-dark ms-1">{{ $ruleCode }}</span>
            @endif
        </div>
    @endif

    @if ($otpSekali !== null)
        <div class="alert alert-info" role="alert">
            {{ __('Kode OTP untuk penerima') }}: <strong class="fs-5">{{ $otpSekali }}</strong>
            <div class="small">
                {{ __('Kode ini hanya ditampilkan sekali dan tidak tersimpan. Sampaikan langsung kepada penerima.') }}
            </div>
        </div>
    @endif

    @if ($dialog === 'batal')
        <div class="card border-warning mb-3">
            <div class="card-header"><strong>{{ __('Batalkan surat jalan') }}</strong></div>
            <div class="card-body">
                <label class="form-label" for="sj-alasan">{{ __('Alasan') }} <span class="wajib">*</span></label>
                <select class="form-select @error('reasonCode') is-invalid @enderror" id="sj-alasan"
                        wire:model="reasonCode">
                    <option value="">{{ __('Pilih alasan…') }}</option>
                    @foreach ($alasan as $kode => $label)
                        <option value="{{ $kode }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('reasonCode') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="card-footer d-flex gap-2">
                <button class="btn btn-warning" type="button" wire:click="batalkan">{{ __('Batalkan') }}</button>
                <button class="btn btn-outline-secondary" type="button" wire:click="tutupDialog">{{ __('Tutup') }}</button>
            </div>
        </div>
    @endif

    @if ($dialog === 'tautan')
        <div class="card border-primary mb-3">
            <div class="card-header"><strong>{{ __('Terbitkan tautan bukti terima') }}</strong></div>
            <div class="card-body">
                <p class="text-muted small">
                    {{ __('Untuk penerima yang tidak punya akun. Tautan berlaku 24 jam, sekali pakai, dan dilindungi OTP.') }}
                </p>
                <label class="form-label" for="sj-telepon">{{ __('Nomor telepon penerima') }}</label>
                <input class="form-control" id="sj-telepon" type="text" wire:model="form.phone"
                       placeholder="08…">
            </div>
            <div class="card-footer d-flex gap-2">
                <button class="btn btn-primary" type="button" wire:click="terbitkanTautan">{{ __('Terbitkan') }}</button>
                <button class="btn btn-outline-secondary" type="button" wire:click="tutupDialog">{{ __('Tutup') }}</button>
            </div>
        </div>
    @endif

    @if ($dialog === 'terima')
        <div class="card border-success mb-3">
            <div class="card-header"><strong>{{ __('Bukti terima') }}</strong></div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label" for="terima-nama">
                            {{ __('Nama penerima') }} <span class="wajib">*</span>
                        </label>
                        <input class="form-control @error('form.received_by_name') is-invalid @enderror"
                               id="terima-nama" type="text" wire:model="form.received_by_name">
                        @error('form.received_by_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="terima-catatan">{{ __('Catatan') }}</label>
                        <input class="form-control" id="terima-catatan" type="text" wire:model="form.notes"
                               placeholder="{{ __('Opsional') }}">
                    </div>
                </div>

                <p class="text-muted small">
                    {{ __('Jumlah baik + rusak + kurang harus sama dengan yang dikirim. Foto wajib bila ada yang rusak.') }}
                </p>

                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('Item') }}</th>
                                <th class="text-end" scope="col">{{ __('Dikirim') }}</th>
                                <th scope="col">{{ __('Baik') }}</th>
                                <th scope="col">{{ __('Rusak') }}</th>
                                <th scope="col">{{ __('Kurang') }}</th>
                                <th scope="col">{{ __('Foto kerusakan') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($lines as $l)
                                <tr wire:key="terima-{{ $l->id }}">
                                    <td>{{ $l->pickTaskLine?->item?->code }}</td>
                                    <td class="text-end">{{ number_format((float) $l->qty_shipped, 2, ',', '.') }}</td>
                                    <td>
                                        <input class="form-control form-control-sm" type="number" step="0.0001" min="0"
                                               wire:model="terima.{{ $l->id }}.qty_good">
                                    </td>
                                    <td>
                                        <input class="form-control form-control-sm" type="number" step="0.0001" min="0"
                                               wire:model="terima.{{ $l->id }}.qty_damaged">
                                    </td>
                                    <td>
                                        <input class="form-control form-control-sm" type="number" step="0.0001" min="0"
                                               wire:model="terima.{{ $l->id }}.qty_missing">
                                    </td>
                                    <td>
                                        <input class="form-control form-control-sm" type="text"
                                               wire:model="terima.{{ $l->id }}.damage_photo_path"
                                               placeholder="{{ __('Berkas foto') }}">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @error('form.qty_good') <div class="text-danger small mt-2">{{ $message }}</div> @enderror
                @error('form.damage_photo_path') <div class="text-danger small mt-2">{{ $message }}</div> @enderror
            </div>
            <div class="card-footer d-flex gap-2">
                <button class="btn btn-success" type="button" wire:click="simpanBuktiTerima">{{ __('Simpan') }}</button>
                <button class="btn btn-outline-secondary" type="button" wire:click="tutupDialog">{{ __('Tutup') }}</button>
            </div>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-header"><strong>{{ __('Muatan') }}</strong></div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Item') }}</th>
                        <th scope="col">{{ __('Bin asal') }}</th>
                        <th class="text-end" scope="col">{{ __('Dikirim') }}</th>
                        <th class="text-end" scope="col">{{ __('Diterima') }}</th>
                        <th scope="col">{{ __('Kepemilikan') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($lines as $l)
                        <tr @class(['table-warning' => $l->outstandingQty() > 0 && $sj->status->isFinal()])>
                            <td>
                                {{ $l->pickTaskLine?->item?->code }}
                                <div class="small text-muted">{{ $l->pickTaskLine?->item?->name }}</div>
                            </td>
                            <td>{{ $l->pickTaskLine?->bin?->code ?? '—' }}</td>
                            <td class="text-end">{{ number_format((float) $l->qty_shipped, 2, ',', '.') }}</td>
                            <td class="text-end">{{ number_format((float) $l->qty_delivered, 2, ',', '.') }}</td>
                            <td><span class="badge text-bg-light">{{ $l->ownership_effect->label() }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-muted py-4" colspan="5">{{ __('Belum ada muatan.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="card-footer d-flex flex-wrap gap-2">
            @can('ship', $sj)
                <button class="btn btn-primary" type="button" wire:click="berangkatkan">
                    {{ __('Berangkatkan') }}
                </button>
            @endcan

            @can('confirmDelivery', $sj)
                <button class="btn btn-success" type="button" wire:click="mintaDialog('terima')">
                    {{ __('Isi bukti terima') }}
                </button>
            @endcan

            @if ($sj->status->value === 'shipped')
                @can('ship', $sj)
                    <button class="btn btn-outline-primary" type="button" wire:click="mintaDialog('tautan')">
                        {{ __('Terbitkan tautan penerima') }}
                    </button>
                @endcan
            @endif

            @can('cancel', $sj)
                <button class="btn btn-outline-danger" type="button" wire:click="mintaDialog('batal')">
                    {{ __('Batalkan') }}
                </button>
            @endcan
        </div>
    </div>

    @if ($bukti)
        <div class="card mb-3">
            <div class="card-header"><strong>{{ __('Bukti terima') }}</strong></div>
            <div class="card-body">
                <p class="mb-1">
                    {{ __('Diterima oleh') }}: <strong>{{ $bukti->received_by_name }}</strong>
                    · {{ $bukti->confirmed_at?->format('d/m/Y H:i') }}
                    · {{ $bukti->channel->label() }}
                </p>
                @if ($bukti->confirm_deadline_at)
                    <p class="text-muted small mb-0">
                        {{ __('Pemohon bisa mengajukan keberatan sampai :tgl', [
                            'tgl' => $bukti->confirm_deadline_at->format('d/m/Y H:i'),
                        ]) }}
                    </p>
                @endif
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Baris') }}</th>
                            <th class="text-end" scope="col">{{ __('Baik') }}</th>
                            <th class="text-end" scope="col">{{ __('Rusak') }}</th>
                            <th class="text-end" scope="col">{{ __('Kurang') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($bukti->lines as $bl)
                            <tr>
                                <td>#{{ $bl->shipment_line_id }}</td>
                                <td class="text-end">{{ number_format((float) $bl->qty_good, 2, ',', '.') }}</td>
                                <td class="text-end">{{ number_format((float) $bl->qty_damaged, 2, ',', '.') }}</td>
                                <td class="text-end">{{ number_format((float) $bl->qty_missing, 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($selisih->isNotEmpty())
        <div class="card mb-3">
            <div class="card-header"><strong>{{ __('Selisih pengiriman') }}</strong></div>
            <ul class="list-group list-group-flush">
                @foreach ($selisih as $dsc)
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('discrepancies.index') }}">{{ $dsc->number }}</a>
                            <span class="badge {{ $dsc->status->badge() }}">{{ $dsc->status->label() }}</span>
                        </div>
                        <div class="small text-muted">
                            @foreach ($dsc->lines as $dl)
                                {{ $dl->discrepancy_type->label() }}
                                {{ number_format((float) $dl->qty_base, 2, ',', '.') }}
                                {{ $dl->shipmentLine?->pickTaskLine?->item?->code }}{{ ! $loop->last ? ' · ' : '' }}
                            @endforeach
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card">
        <div class="card-header"><strong>{{ __('Riwayat') }}</strong></div>
        <ul class="list-group list-group-flush">
            @forelse ($riwayat as $log)
                <li class="list-group-item">
                    <div class="d-flex justify-content-between">
                        <span>{{ $log->description }}</span>
                        <span class="text-muted small">{{ $log->created_at?->format('d/m/Y H:i') }}</span>
                    </div>
                    <div class="small text-muted">{{ $log->causer?->name ?? __('Sistem') }}</div>
                </li>
            @empty
                <li class="list-group-item text-muted">{{ __('Belum ada riwayat.') }}</li>
            @endforelse
        </ul>
    </div>
</div>
