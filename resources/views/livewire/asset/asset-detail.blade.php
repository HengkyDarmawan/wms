<div>
    @php($sisa = $aset->remainingLifePercent())
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">
                {{ $aset->serial_no }}
                <span class="badge text-bg-{{ $aset->stateBadge() }}">{{ $aset->asset_state->label() }}</span>
                @if ($aset->isOverdue()) <span class="badge text-bg-danger">{{ __('Lewat jatuh tempo') }}</span> @endif
            </h1>
            <p class="text-muted mb-0">
                {{ $aset->item?->code }} — {{ $aset->item?->name }}
                · {{ __('Lokasi') }} {{ $saldo?->bin?->code ?? '—' }}
                @if ($aset->currentProject) · {{ __('Proyek') }} {{ $aset->currentProject->code }} @endif
                @if ($aset->due_return_date) · {{ __('Kembali') }} {{ $aset->due_return_date->format('d/m/Y') }} @endif
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-secondary" href="{{ route('assets.index') }}">{{ __('Kembali') }}</a>
        </div>
    </div>

    @if ($ruleError !== '')
        <div class="alert alert-danger" role="alert">
            {{ $ruleError }}
            @if ($ruleCode !== '') <span class="badge text-bg-dark ms-1">{{ $ruleCode }}</span> @endif
        </div>
    @endif

    <div class="row g-3 mb-3">
        @foreach ([
            [__('Grade / skor'), ($aset->condition_grade ?? '—').($aset->condition_score !== null ? ' · '.$aset->condition_score.' %' : '')],
            [__('Meter'), $aset->meter_unit?->value === 'none' ? '—' : number_format((float) $aset->meter_total, 1, ',', '.').' '.$aset->meter_unit?->label()],
            [__('Diperoleh'), $aset->acquired_at?->format('d/m/Y') ?? '—'],
            [__('Umur harapan'), trim(($aset->expected_life_days ? $aset->expected_life_days.' '.__('hari') : '').($aset->expected_life_hours ? ' · '.number_format((float) $aset->expected_life_hours, 1, ',', '.').' '.$aset->meter_unit?->label() : '')) ?: '—'],
            [__('Sisa umur'), $sisa === null ? '—' : number_format($sisa, 1, ',', '.').' %'],
        ] as [$label, $nilai])
            <div class="col-6 col-md">
                <div class="card h-100"><div class="card-body py-2">
                    <div class="small text-muted">{{ $label }}</div>
                    <div class="fs-6 {{ $label === __('Sisa umur') && $sisa !== null && $sisa < $ambang ? 'text-danger fw-semibold' : '' }}">{{ $nilai }}</div>
                </div></div>
            </div>
        @endforeach
    </div>

    @if ($dialog === 'profil')
        <div class="card border-primary mb-3">
            <div class="card-header"><strong>{{ __('Profil masa pakai') }}</strong></div>
            <div class="card-body row g-3">
                <div class="col-md-3">
                    <label class="form-label" for="aset-perolehan">{{ __('Tanggal perolehan') }}</label>
                    <input class="form-control @error('form.acquired_at') is-invalid @enderror" id="aset-perolehan" type="date" wire:model="form.acquired_at">
                    @error('form.acquired_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="aset-meter">{{ __('Satuan meter') }}</label>
                    <select class="form-select @error('form.meter_unit') is-invalid @enderror" id="aset-meter" wire:model="form.meter_unit">
                        @foreach ($units as $nilai => $label)
                            <option value="{{ $nilai }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('form.meter_unit') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="aset-total">{{ __('Akumulasi meter') }}</label>
                    <input class="form-control @error('form.meter_total') is-invalid @enderror" id="aset-total" type="number" step="0.1" min="0" wire:model="form.meter_total">
                    @error('form.meter_total') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="aset-umur-hari">{{ __('Umur (hari)') }}</label>
                    <input class="form-control @error('form.expected_life_days') is-invalid @enderror" id="aset-umur-hari" type="number" min="0" wire:model="form.expected_life_days">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="aset-umur-jam">{{ __('Umur (jam/km meter)') }}</label>
                    <input class="form-control @error('form.expected_life_hours') is-invalid @enderror" id="aset-umur-jam" type="number" step="0.1" min="0" wire:model="form.expected_life_hours">
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <button class="btn btn-primary" type="button" wire:click="simpanProfil">{{ __('Simpan profil') }}</button>
                <button class="btn btn-outline-secondary" type="button" wire:click="tutupDialog">{{ __('Tutup') }}</button>
            </div>
        </div>
    @endif

    @if ($dialog === 'hilang')
        <div class="card border-danger mb-3">
            <div class="card-header"><strong>{{ __('Tandai aset hilang') }}</strong> <span class="small text-muted">{{ __('penyesuaian stok keluar dibuat dan menunggu approval; setelah diposting aset Dihapuskan') }}</span></div>
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="aset-alasan">{{ __('Alasan') }} <span class="wajib">*</span></label>
                    <select class="form-select @error('form.reason') is-invalid @enderror" id="aset-alasan" wire:model="form.reason">
                        <option value="">{{ __('Pilih alasan…') }}</option>
                        @foreach ($alasanHilang as $kode => $label)
                            <option value="{{ $kode }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('form.reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="aset-ket">{{ __('Keterangan') }}</label>
                    <input class="form-control" id="aset-ket" type="text" wire:model="form.notes" placeholder="{{ __('Opsional') }}">
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <button class="btn btn-danger" type="button" wire:click="tandaiHilang">{{ __('Tandai hilang') }}</button>
                <button class="btn btn-outline-secondary" type="button" wire:click="tutupDialog">{{ __('Tutup') }}</button>
            </div>
        </div>
    @endif

    <div class="d-flex flex-wrap gap-2 mb-3">
        @can('update', $aset)
            <button class="btn btn-outline-primary" type="button" wire:click="mintaDialog('profil')">{{ __('Ubah profil masa pakai') }}</button>
        @endcan
        @can('markLost', $aset)
            <button class="btn btn-outline-danger" type="button" wire:click="mintaDialog('hilang')">{{ __('Tandai hilang') }}</button>
        @endcan
        @can('found', $aset)
            <button class="btn btn-outline-success" type="button" wire:click="ditemukan" wire:confirm="{{ __('Batalkan tanda hilang? Penyesuaian stoknya harus sudah ditolak atau dibatalkan.') }}">{{ __('Ditemukan kembali') }}</button>
        @endcan
        @foreach ($adjustments as $adj)
            <a class="btn btn-outline-secondary" href="{{ route('adjustments.show', $adj->id) }}">{{ $adj->number }} ({{ $adj->status->label() }})</a>
        @endforeach
    </div>

    <div class="card mb-3">
        <div class="card-header"><strong>{{ __('Serah terima') }}</strong></div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Nomor') }}</th>
                        <th scope="col">{{ __('Proyek') }}</th>
                        <th scope="col">{{ __('Keluar') }}</th>
                        <th scope="col">{{ __('Kembali') }}</th>
                        <th class="text-end" scope="col">{{ __('Hari pakai') }}</th>
                        <th scope="col">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($handovers as $h)
                        <tr>
                            <td><a href="{{ route('asset-handovers.show', $h) }}">{{ $h->number }}</a> <div class="small text-muted">{{ $h->shipment?->number }} @if ($h->goodsReturn) · {{ $h->goodsReturn->number }} @endif</div></td>
                            <td>{{ $h->project?->code }}</td>
                            <td class="small">{{ $h->checked_out_at?->format('d/m/Y') }} @if ($h->due_return_date) <div class="text-muted">{{ __('jatuh tempo') }} {{ $h->due_return_date->format('d/m/Y') }}</div> @endif</td>
                            <td class="small">{{ $h->returned_at?->format('d/m/Y') ?? '—' }}</td>
                            <td class="text-end">{{ $h->usage_days ?? '—' }}</td>
                            <td>
                                <span class="badge {{ $h->status->badge() }}">{{ $h->status->label() }}</span>
                                @if ($h->lost_at) <span class="badge text-bg-danger">{{ __('Hilang') }}</span> @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td class="text-center text-muted py-3" colspan="6">{{ __('Belum pernah dipinjamkan.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><strong>{{ __('Riwayat kondisi') }}</strong></div>
        <ul class="list-group list-group-flush small">
            @forelse ($inspections as $i)
                <li class="list-group-item">
                    {{ $i->inspected_at?->lokal()->format('d/m/Y H:i') }} · {{ $i->inspector?->name }} · {{ __('Grade') }} {{ $i->condition_grade->value }} · {{ $i->condition_score }} % → {{ $i->resulting_state->label() }}
                    @if ($i->meter_in !== null) · {{ __('meter') }} {{ number_format((float) $i->meter_in, 1, ',', '.') }} @endif
                    @if ($i->handover) · <a href="{{ route('asset-handovers.show', $i->handover->id) }}">{{ $i->handover->number }}</a> @endif
                    <div class="text-muted">
                        @foreach ($i->component_notes ?? [] as $k) {{ $k['component'] }}: {{ $k['note'] }}@if (! $loop->last); @endif @endforeach
                    </div>
                </li>
            @empty
                <li class="list-group-item text-muted">{{ __('Belum pernah diperiksa.') }}</li>
            @endforelse
        </ul>
    </div>

    <div class="card mb-3">
        <div class="card-header"><strong>{{ __('Kartu stok serial') }}</strong></div>
        <ul class="list-group list-group-flush small">
            @forelse ($movements as $m)
                <li class="list-group-item">#{{ $m->id }} · {{ $m->occurred_at?->lokal()->format('d/m/Y H:i') }} · {{ $m->fromBin?->code ?? __('luar') }} → {{ $m->toBin?->code ?? __('luar') }} · {{ $m->stock_status->label() }} · {{ $m->document_number }}</li>
            @empty
                <li class="list-group-item text-muted">{{ __('Belum ada pergerakan.') }}</li>
            @endforelse
        </ul>
    </div>

    <div class="card">
        <div class="card-header"><strong>{{ __('Riwayat') }}</strong></div>
        <ul class="list-group list-group-flush small">
            @forelse ($riwayat as $r)
                <li class="list-group-item">{{ $r->created_at?->lokal()->format('d/m/Y H:i') }} · {{ $r->causer?->name ?? __('Sistem') }} · {{ $r->description }}</li>
            @empty
                <li class="list-group-item text-muted">{{ __('Belum ada riwayat.') }}</li>
            @endforelse
        </ul>
    </div>
</div>
