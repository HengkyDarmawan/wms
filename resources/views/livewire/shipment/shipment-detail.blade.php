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
                    · {{ __('Berangkat') }} {{ $sj->shipped_at->lokal()->format('d/m/Y H:i') }}
                @endif
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @include('print.partials.button', ['jenis' => \App\Domain\Template\Enums\DocumentTemplateType::Shipment, 'id' => $sj->id, 'teks' => __('Cetak SJ')])
            @if ($bukti)
                @include('print.partials.button', ['jenis' => \App\Domain\Template\Enums\DocumentTemplateType::ProofOfDelivery, 'id' => $sj->id, 'teks' => __('Cetak bukti terima')])
            @endif
            <a class="btn btn-outline-secondary" href="{{ route('shipments.index') }}">{{ __('Kembali') }}</a>
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

    @if ($otpSekali !== null)
        <div class="alert alert-info" role="alert">
            {{ __('Kode OTP untuk penerima') }}: <strong class="fs-5">{{ $otpSekali }}</strong>
            @if ($tautanSekali)
                <div class="mt-1">{{ __('Tautan') }}: <a href="{{ $tautanSekali }}" target="_blank" rel="noopener" class="text-break">{{ $tautanSekali }}</a></div>
            @endif
            <div class="small">
                {{ __('Kode ini hanya ditampilkan sekali dan tidak tersimpan. Bagikan tautannya dan sampaikan kode langsung kepada penerima.') }}
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
        <div class="card border-success mb-3" data-draft="pod-{{ $sj->id }}">
            <div class="card-header"><strong>{{ __('Bukti terima') }}</strong></div>
            <div class="alert alert-warning py-2 small m-2 d-none" role="status" data-draft-status></div>
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
                                    <td>
                                        {{ $l->pickTaskLine?->item?->code }}
                                        @if ($l->pickTaskLine?->serial) <div class="small text-muted">{{ __('Serial') }} {{ $l->pickTaskLine->serial->serial_no }}</div> @endif
                                        @if ($l->pickTaskLine?->piece) <div class="small text-muted">{{ __('Potongan') }} {{ $l->pickTaskLine->piece->piece_no }}</div> @endif
                                    </td>
                                    <td class="text-end">{{ number_format((float) $l->qty_shipped, 2, ',', '.') }}</td>
                                    @if (($terima[$l->id]['kondisi'] ?? null) !== null)
                                        {{-- A-244: satu unit — baik, rusak, atau kurang seluruhnya. --}}
                                        <td colspan="3">
                                            <select class="form-select form-select-sm" wire:model="terima.{{ $l->id }}.kondisi" aria-label="{{ __('Kondisi unit') }}">
                                                @foreach (\App\Domain\Shipment\Enums\PodUnitCondition::cases() as $k)
                                                    <option value="{{ $k->value }}">{{ $k->label() }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                    @else
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
                                    @endif
                                    <td>
                                        <input class="form-control form-control-sm @error('fotoRusak.'.$l->id) is-invalid @enderror" type="file"
                                               accept="image/jpeg,image/png,image/webp" capture="environment"
                                               wire:model="fotoRusak.{{ $l->id }}" aria-label="{{ __('Foto kerusakan') }}">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @error('form.qty_good') <div class="text-danger small mt-2">{{ $message }}</div> @enderror
                @error('form.damage_photo_path') <div class="text-danger small mt-2">{{ $message }}</div> @enderror

                {{-- A-231: foto serah terima & tanda tangan penerima (kanvas → data URL). --}}
                <div class="row g-3 mt-1">
                    <div class="col-md-6">
                        <label class="form-label" for="terima-foto">{{ __('Foto serah terima') }} <span class="text-muted small">({{ __('opsional') }})</span></label>
                        <input class="form-control @error('foto') is-invalid @enderror" id="terima-foto" type="file"
                               accept="image/jpeg,image/png,image/webp" capture="environment" wire:model="foto">
                        @error('foto') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6" data-signature wire:ignore>
                        <label class="form-label">{{ __('Tanda tangan penerima') }} <span class="text-muted small">({{ __('gambar dengan jari') }})</span></label>
                        <canvas class="border rounded w-100 bg-white" height="140" aria-label="{{ __('Kanvas tanda tangan') }}"></canvas>
                        <input type="hidden" wire:model="tandaTangan" data-signature-target>
                        <button class="btn btn-sm btn-outline-secondary mt-1" type="button" data-signature-clear>{{ __('Hapus tanda tangan') }}</button>
                    </div>
                </div>
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
                @can('issueToken', $sj)
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
                    · {{ $bukti->confirmed_at?->lokal()->format('d/m/Y H:i') }}
                    · {{ $bukti->channel->label() }}
                </p>
                @if ($bukti->photo_path || $bukti->signature_path)
                    <p class="mb-1 small">
                        @if ($bukti->photo_path) <a href="{{ route('shipments.proof.file', [$sj, 'foto']) }}" target="_blank" rel="noopener"><i class="bi bi-image"></i> {{ __('Foto serah terima') }}</a> @endif
                        @if ($bukti->signature_path) <a class="ms-2" href="{{ route('shipments.proof.file', [$sj, 'ttd']) }}" target="_blank" rel="noopener"><i class="bi bi-pen"></i> {{ __('Tanda tangan') }}</a> @endif
                    </p>
                @endif
                @if ($bukti->confirm_deadline_at)
                    <p class="text-muted small mb-0">
                        {{ __('Pemohon bisa mengajukan keberatan sampai :tgl', [
                            'tgl' => $bukti->confirm_deadline_at->lokal()->format('d/m/Y H:i'),
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
                                <td class="text-end">{{ number_format((float) $bl->qty_missing, 2, ',', '.') }} @if ($bl->damage_photo_path) <a class="ms-1" href="{{ route('shipments.proof.file', [$sj, 'baris-'.$bl->id]) }}" target="_blank" rel="noopener" title="{{ __('Foto kerusakan') }}"><i class="bi bi-image"></i></a> @endif</td>
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
                            <span>
                                <a class="small me-2" target="_blank" rel="noopener"
                                   href="{{ route('print.document', ['type' => 'delivery-discrepancy', 'id' => $dsc->id]) }}"><i class="bi bi-printer"></i> {{ __('Cetak BA') }}</a>
                                <span class="badge {{ $dsc->status->badge() }}">{{ $dsc->status->label() }}</span>
                            </span>
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
