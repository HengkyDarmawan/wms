<div class="card mb-3" id="servis">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <strong>{{ __('Servis') }}</strong>
            <span class="small text-muted">{{ __('selama diservis aset ada di bin Karantina dan tidak bisa dicadangkan') }}</span>
        </div>
        @if ($bisaAtur && $ubah === 0 && ! $jadwal->contains(fn ($j) => $j->status !== \App\Domain\Asset\Enums\MaintenanceStatus::Done))
            <button class="btn btn-sm btn-primary" type="button" wire:click="bukaForm">{{ __('Jadwalkan servis') }}</button>
        @endif
    </div>

    @if ($ruleError !== '')
        <div class="alert alert-danger m-3 mb-0" role="alert">
            {{ $ruleError }} @if ($ruleCode !== '') <span class="badge text-bg-dark ms-1">{{ $ruleCode }}</span> @endif
        </div>
    @endif

    @if ($ubah !== 0)
        <div class="card-body border-bottom row g-3 align-items-end">
            <div class="col-sm-4">
                <label class="form-label" for="servis-tanggal">{{ __('Tanggal servis') }} <span class="wajib">*</span></label>
                <input class="form-control @error('form.scheduled_at') is-invalid @enderror" id="servis-tanggal" type="date" wire:model="form.scheduled_at">
                @error('form.scheduled_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-sm-3">
                <label class="form-label" for="servis-interval">{{ __('Ulangi tiap (hari)') }}</label>
                <input class="form-control @error('form.interval_days') is-invalid @enderror" id="servis-interval" type="number" min="1" max="3650" inputmode="numeric" wire:model="form.interval_days" placeholder="{{ __('kosong = sekali') }}">
                @error('form.interval_days') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            @php($bermeter = $aset && $aset->meter_unit?->value !== 'none')
            @if ($bermeter)
                <div class="col-sm-3">
                    <label class="form-label" for="servis-meter">{{ __('atau tiap (:u)', ['u' => $aset->meter_unit->label()]) }}</label>
                    <input class="form-control @error('form.interval_meter') is-invalid @enderror" id="servis-meter" type="number" min="0" step="0.1" wire:model="form.interval_meter" placeholder="{{ __('mis. 250') }}">
                    @error('form.interval_meter') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <div class="form-text">{{ __('Meter sekarang :m; yang lebih dulu tercapai yang berlaku.', ['m' => rtrim(rtrim(number_format((float) $aset->meter_total, 1, ',', '.'), '0'), ',')]) }}</div>
                </div>
            @endif
            <div class="col-sm-{{ $bermeter ? 2 : 5 }}">
                <label class="form-label" for="servis-catatan">{{ __('Catatan') }}</label>
                <input class="form-control" id="servis-catatan" type="text" maxlength="255" wire:model="form.notes" placeholder="{{ __('mis. ganti oli dan filter') }}">
            </div>
            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" type="button" wire:click="simpan">{{ __('Simpan jadwal') }}</button>
                <button class="btn btn-outline-secondary" type="button" wire:click="tutupForm">{{ __('Batal') }}</button>
            </div>
        </div>
    @endif

    @if ($jadwal->isEmpty())
        <div class="card-body text-muted small">{{ __('Belum ada jadwal servis untuk aset ini.') }}</div>
    @else
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Tanggal') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Asal') }}</th>
                        <th>{{ __('Catatan') }}</th>
                        <th class="text-end">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($jadwal as $j)
                        <tr wire:key="servis-{{ $j->id }}">
                            <td class="text-nowrap">
                                {{ $j->scheduled_at->format('d/m/Y') }}
                                @if ($j->status === \App\Domain\Asset\Enums\MaintenanceStatus::Planned && $j->scheduled_at->lte(today()))
                                    <span class="badge text-bg-warning">{{ __('Jatuh tempo') }}</span>
                                @endif
                                @if ($j->interval_days) <div class="small text-muted">{{ __('tiap :n hari', ['n' => $j->interval_days]) }}</div> @endif
                                @if ($j->due_meter !== null) <div class="small text-muted">{{ __('atau saat meter :m', ['m' => rtrim(rtrim(number_format((float) $j->due_meter, 1, ',', '.'), '0'), ',')]) }}</div> @endif
                            </td>
                            <td>
                                <span class="badge text-bg-{{ $j->status->tone() }}">{{ $j->status->label() }}</span>
                                @if ($j->performed_at) <div class="small text-muted">{{ __('selesai :t', ['t' => $j->performed_at->format('d/m/Y')]) }}</div> @endif
                            </td>
                            <td class="small">{{ $j->source->label() }}@if ($j->originBin) <div class="text-muted">{{ __('dari bin :b', ['b' => $j->originBin->code]) }}</div> @endif</td>
                            <td class="small">{{ $j->notes ?? '-' }}</td>
                            <td class="text-end">
                                @if ($bisaAtur && $j->status === \App\Domain\Asset\Enums\MaintenanceStatus::Planned)
                                    <div class="d-flex flex-wrap justify-content-end gap-1">
                                        <button class="btn btn-sm btn-outline-secondary" type="button" wire:click="bukaForm({{ $j->id }})">{{ __('Ubah') }}</button>
                                        <button class="btn btn-sm btn-primary" type="button" wire:click="mulai({{ $j->id }})"
                                                wire:confirm="{{ __('Mulai servis? Aset dipindah ke bin Karantina.') }}">{{ __('Mulai servis') }}</button>
                                    </div>
                                @elseif ($bisaAtur && $j->status === \App\Domain\Asset\Enums\MaintenanceStatus::InProgress)
                                    <div class="d-flex flex-wrap justify-content-end align-items-start gap-2">
                                        <div style="min-width: 12rem" class="text-start">
                                            <x-pilih model="binTujuan" :options="$binPilihan" :kosong="__('Pilih bin tujuan…')" :aria="__('Bin tujuan')" kecil />
                                        </div>
                                        <button class="btn btn-sm btn-success" type="button" wire:click="selesai({{ $j->id }})">{{ __('Selesai servis') }}</button>
                                    </div>
                                @else
                                    <span class="text-muted small">-</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
