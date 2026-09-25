<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">
                {{ $ast->number }}
                <span class="badge {{ $ast->status->badge() }}">{{ $ast->status->label() }}</span>
                @if ($ast->isOverdue()) <span class="badge text-bg-danger">{{ __('Lewat jatuh tempo') }}</span> @endif
                @if ($ast->lost_at) <span class="badge text-bg-danger">{{ __('Hilang') }}</span> @endif
            </h1>
            <p class="text-muted mb-0">
                <a href="{{ route('assets.show', $ast->serial_id) }}">{{ $ast->serial?->serial_no }}</a> · {{ $ast->item?->code }} — {{ $ast->item?->name }}
                · {{ __('Proyek') }} {{ $ast->project?->code }} · {{ __('Gudang') }} {{ $ast->warehouse?->code }}
                @if ($ast->shipment) · {{ __('SJ') }} <a href="{{ route('shipments.show', $ast->shipment_id) }}">{{ $ast->shipment->number }}</a> @endif
                @if ($ast->goodsReturn) · {{ __('RET') }} <a href="{{ route('returns.show', $ast->goods_return_id) }}">{{ $ast->goodsReturn->number }}</a> @endif
            </p>
            @if ($ast->lost_at)
                <p class="text-danger small mb-0">{{ __('Ditandai hilang') }} {{ $ast->lost_at->lokal()->format('d/m/Y') }}: {{ $ast->lostReason?->label }} @if ($ast->adjustment) · <a href="{{ route('adjustments.show', $ast->stock_adjustment_id) }}">{{ $ast->adjustment->number }}</a> ({{ $ast->adjustment->status->label() }}) @endif</p>
            @endif
            @if ($ast->notes) <p class="small mb-0">{{ $ast->notes }}</p> @endif
        </div>
        <div class="d-flex flex-wrap gap-2">
            @include('print.partials.button', ['jenis' => \App\Domain\Template\Enums\DocumentTemplateType::AssetHandover, 'id' => $ast->id])
            <a class="btn btn-outline-secondary" href="{{ route('asset-handovers.index') }}">{{ __('Kembali') }}</a>
        </div>
    </div>

    @if ($ruleError !== '')
        <div class="alert alert-danger" role="alert">
            {{ $ruleError }}
            @if ($ruleCode !== '') <span class="badge text-bg-dark ms-1">{{ $ruleCode }}</span> @endif
        </div>
    @endif

    @if (session('errors')?->any())
        <div class="alert alert-danger" role="alert">
            {{ session('errors')->first() }}
            @if (session('rule')) <span class="badge text-bg-dark ms-1">{{ session('rule') }}</span> @endif
        </div>
    @endif

    <div class="row g-3 mb-3">
        @foreach ([
            [__('Keluar'), $ast->checked_out_at?->lokal()->format('d/m/Y H:i')],
            [__('Jatuh tempo'), $ast->due_return_date?->format('d/m/Y') ?? '—'],
            [__('Grade keluar'), $ast->condition_out ?? '—'],
            [__('Meter keluar / kembali'), ($ast->meter_out !== null ? number_format((float) $ast->meter_out, 1, ',', '.') : '—').' / '.($ast->meter_in !== null ? number_format((float) $ast->meter_in, 1, ',', '.') : '—')],
            [__('Kembali'), $ast->returned_at?->lokal()->format('d/m/Y H:i') ?? '—'],
            [__('Hari pakai'), $ast->usage_days ?? '—'],
            [__('Pemakaian meter'), $ast->usage_hours !== null ? number_format((float) $ast->usage_hours, 1, ',', '.').' '.__('jam') : ($ast->usage_km !== null ? number_format((float) $ast->usage_km, 1, ',', '.').' km' : '—')],
        ] as [$label, $nilai])
            <div class="col-6 col-md">
                <div class="card h-100"><div class="card-body py-2">
                    <div class="small text-muted">{{ $label }}</div>
                    <div class="fs-6">{{ $nilai }}</div>
                </div></div>
            </div>
        @endforeach
    </div>

    @can('update', $ast)
        @if ($ubah)
            <div class="card border-primary mb-3">
                <div class="card-header"><strong>{{ __('Lengkapi serah terima keluar') }}</strong></div>
                <div class="card-body row g-3">
                    <div class="col-md-3">
                        <label class="form-label" for="ast-tempo">{{ __('Tanggal kembali') }}</label>
                        <input class="form-control @error('form.due_return_date') is-invalid @enderror" id="ast-tempo" type="date" wire:model="form.due_return_date">
                        @error('form.due_return_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="ast-meter">{{ __('Meter keluar') }}</label>
                        <input class="form-control @error('form.meter_out') is-invalid @enderror" id="ast-meter" type="number" step="0.1" min="0" wire:model="form.meter_out">
                        @error('form.meter_out') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="ast-grade">{{ __('Grade keluar') }}</label>
                        <select class="form-select @error('form.condition_out') is-invalid @enderror" id="ast-grade" wire:model="form.condition_out">
                            <option value="">—</option>
                            @foreach ($grades as $nilai => $label)
                                <option value="{{ $nilai }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="ast-ket">{{ __('Keterangan') }}</label>
                        <input class="form-control" id="ast-ket" type="text" wire:model="form.notes" placeholder="{{ __('Opsional') }}">
                    </div>
                </div>
                <div class="card-footer d-flex gap-2">
                    <button class="btn btn-primary" type="button" wire:click="simpan">{{ __('Simpan') }}</button>
                    <button class="btn btn-outline-secondary" type="button" wire:click="batalUbah">{{ __('Tutup') }}</button>
                </div>
            </div>
        @else
            <button class="btn btn-outline-primary mb-3" type="button" wire:click="mulaiUbah">{{ __('Lengkapi serah terima') }}</button>
        @endif
    @endcan

    @can('inspect', $ast)
        <form class="card border-warning mb-3" method="POST" action="{{ route('asset-handovers.inspect', $ast) }}" enctype="multipart/form-data">
            @csrf
            <div class="card-header"><strong>{{ __('Periksa aset') }}</strong> <span class="small text-muted">{{ __('grade + skor + catatan komponen + foto; A/B kembali Tersedia, C Maintenance, D Rusak') }}</span></div>
            <div class="card-body row g-3">
                <div class="col-md-3">
                    <label class="form-label" for="periksa-grade">{{ __('Grade kondisi') }} <span class="wajib">*</span></label>
                    <select class="form-select" id="periksa-grade" name="condition_grade" required>
                        <option value="">{{ __('Pilih…') }}</option>
                        @foreach ($grades as $nilai => $label)
                            <option value="{{ $nilai }}" @selected(old('condition_grade') === $nilai)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="periksa-skor">{{ __('Skor (%)') }} <span class="wajib">*</span></label>
                    <input class="form-control" id="periksa-skor" name="condition_score" type="number" min="0" max="100" step="1" value="{{ old('condition_score') }}" required>
                </div>
                @if ($ast->serial?->meter_unit?->value !== 'none')
                    <div class="col-md-2">
                        <label class="form-label" for="periksa-meter">{{ __('Meter kembali') }} <span class="wajib">*</span></label>
                        <input class="form-control" id="periksa-meter" name="meter_in" type="number" min="0" step="0.1" value="{{ old('meter_in') }}">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label" for="periksa-reset">{{ __('Alasan ganti meter') }}</label>
                        <input class="form-control" id="periksa-reset" name="meter_reset_reason" type="text" maxlength="255" value="{{ old('meter_reset_reason') }}" placeholder="{{ __('Isi bila meter kembali lebih kecil dari meter keluar') }}">
                    </div>
                @endif
                <div class="col-md-6">
                    <label class="form-label" for="periksa-komponen">{{ __('Catatan komponen') }} <span class="wajib">*</span></label>
                    <textarea class="form-control" id="periksa-komponen" name="component_notes" rows="3" placeholder="{{ __("Satu baris per komponen, mis.\nMesin: normal\nKabel: terkelupas") }}" required>{{ old('component_notes') }}</textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="periksa-foto">{{ __('Foto') }} <span class="wajib">*</span></label>
                    <input class="form-control" id="periksa-foto" name="photo" type="file" accept="image/jpeg,image/png,image/webp" required>
                    <label class="form-label mt-2" for="periksa-ket">{{ __('Keterangan') }}</label>
                    <input class="form-control" id="periksa-ket" name="notes" type="text" maxlength="255" value="{{ old('notes') }}" placeholder="{{ __('Opsional') }}">
                </div>
            </div>
            <div class="card-footer">
                <button class="btn btn-warning" type="submit">{{ __('Simpan pemeriksaan') }}</button>
            </div>
        </form>
    @endcan

    <div class="card mb-3">
        <div class="card-header"><strong>{{ __('Hasil pemeriksaan') }}</strong></div>
        <ul class="list-group list-group-flush small">
            @forelse ($inspections as $i)
                <li class="list-group-item">
                    {{ $i->inspected_at?->lokal()->format('d/m/Y H:i') }} · {{ $i->inspector?->name }} · {{ $i->condition_grade->label() }} · {{ $i->condition_score }} % → <strong>{{ $i->resulting_state->label() }}</strong>
                    @if ($i->photo_path) · <a href="{{ route('asset-inspections.photo', $i) }}" target="_blank" rel="noopener">{{ __('foto') }}</a> @endif
                    @if ($i->meter_reset_reason) · {{ __('meter diganti') }}: {{ $i->meter_reset_reason }} @endif
                    <div class="text-muted">@foreach ($i->component_notes ?? [] as $k) {{ $k['component'] }}: {{ $k['note'] }}@if (! $loop->last); @endif @endforeach</div>
                    @if ($i->notes) <div>{{ $i->notes }}</div> @endif
                </li>
            @empty
                <li class="list-group-item text-muted">{{ __('Belum diperiksa.') }}</li>
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
