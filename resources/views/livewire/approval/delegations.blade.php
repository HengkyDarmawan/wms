<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Delegasi approval') }}</h1>
            <p class="text-muted mb-0">{{ __('Limpahkan hak approve selama cuti atau dinas. Berperiode dan tidak berantai: delegat tidak bisa mendelegasikan lagi (BR-APR-05).') }}</p>
        </div>
        @can('create', App\Domain\Approval\Models\ApprovalDelegation::class)
            <button class="btn btn-primary" type="button" wire:click="buat">{{ __('Delegasi baru') }}</button>
        @endcan
    </div>

    @if ($ruleError !== '')
        <div class="alert alert-danger" role="alert">
            {{ $ruleError }}
            @if ($ruleCode !== '') <span class="badge text-bg-dark ms-1">{{ $ruleCode }}</span> @endif
        </div>
    @endif

    @if ($showForm)
        <div class="card border-primary mb-3">
            <div class="card-header"><strong>{{ __('Delegasi baru') }}</strong></div>
            <div class="card-body row g-3">
                @if ($admin)
                    <div class="col-md-4">
                        <label class="form-label" for="del-dari">{{ __('Pemberi delegasi') }} <span class="wajib">*</span></label>
                        <select class="form-select @error('form.from_user_id') is-invalid @enderror" id="del-dari" wire:model="form.from_user_id">
                            @foreach ($users as $u) <option value="{{ $u->id }}">{{ $u->name }}</option> @endforeach
                        </select>
                        @error('form.from_user_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                @endif
                <div class="col-md-4">
                    <label class="form-label" for="del-ke">{{ __('Delegat') }} <span class="wajib">*</span></label>
                    <select class="form-select @error('form.to_user_id') is-invalid @enderror" id="del-ke" wire:model="form.to_user_id">
                        <option value="">{{ __('Pilih user…') }}</option>
                        @foreach ($users as $u) <option value="{{ $u->id }}">{{ $u->name }}</option> @endforeach
                    </select>
                    @error('form.to_user_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="del-jenis">{{ __('Jenis dokumen') }}</label>
                    <select class="form-select @error('form.document_types') is-invalid @enderror" id="del-jenis" multiple size="3" wire:model="form.document_types">
                        @foreach ($types as $nilai => $label) <option value="{{ $nilai }}">{{ $label }}</option> @endforeach
                    </select>
                    <div class="form-text">{{ __('Kosong = semua jenis dokumen.') }}</div>
                    @error('form.document_types') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="del-mulai">{{ __('Mulai') }} <span class="wajib">*</span></label>
                    <input class="form-control @error('form.starts_at') is-invalid @enderror" id="del-mulai" type="datetime-local" wire:model="form.starts_at">
                    @error('form.starts_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="del-akhir">{{ __('Sampai') }} <span class="wajib">*</span></label>
                    <input class="form-control @error('form.ends_at') is-invalid @enderror" id="del-akhir" type="datetime-local" wire:model="form.ends_at">
                    @error('form.ends_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="del-ket">{{ __('Keterangan') }}</label>
                    <input class="form-control" id="del-ket" type="text" wire:model="form.notes" maxlength="255" placeholder="{{ __('Opsional, mis. cuti tahunan') }}">
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <button class="btn btn-primary" type="button" wire:click="simpan">{{ __('Simpan') }}</button>
                <button class="btn btn-outline-secondary" type="button" wire:click="batalForm">{{ __('Batal') }}</button>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Dari') }}</th>
                        <th scope="col">{{ __('Kepada') }}</th>
                        <th scope="col">{{ __('Periode') }}</th>
                        <th scope="col">{{ __('Jenis dokumen') }}</th>
                        <th scope="col">{{ __('Status') }}</th>
                        <th class="text-end" scope="col">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($delegations as $d)
                        <tr wire:key="delegasi-{{ $d->id }}">
                            <td>{{ $d->fromUser?->name }}</td>
                            <td>{{ $d->toUser?->name }}</td>
                            <td>{{ $d->starts_at->timezone($zona)->format('d/m/Y H:i') }} – {{ $d->ends_at->timezone($zona)->format('d/m/Y H:i') }}
                                @if ($d->notes) <div class="small text-muted">{{ $d->notes }}</div> @endif
                            </td>
                            <td class="small">{{ $d->document_types ? collect($d->document_types)->map(fn ($t) => \App\Domain\Approval\Enums\ApprovalDocumentType::tryFrom($t)?->code() ?? $t)->implode(', ') : __('Semua') }}</td>
                            <td>
                                @if ($d->isEffective()) <span class="badge text-bg-success">{{ __('Berlaku') }}</span>
                                @elseif ($d->is_active && $d->starts_at->isFuture()) <span class="badge text-bg-info">{{ __('Terjadwal') }}</span>
                                @else <span class="badge text-bg-secondary">{{ __('Berakhir') }}</span> @endif
                            </td>
                            <td class="text-end">
                                @can('end', $d)
                                    <button class="btn btn-sm btn-outline-danger" type="button" wire:click="akhiri({{ $d->id }})">{{ __('Akhiri') }}</button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td class="text-center text-muted py-4" colspan="6">{{ __('Belum ada delegasi.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
