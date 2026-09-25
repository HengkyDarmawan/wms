<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Sesi opname baru') }}</h1>
            <p class="text-muted mb-0">{{ __('Pilih jenis, cakupan, dan tim. Bin berpenanda hitung (short pick/selisih) didahulukan.') }}</p>
        </div>
        <a class="btn btn-outline-secondary" href="{{ route('counts.index') }}">{{ __('Kembali') }}</a>
    </div>

    @if ($ruleError !== '')
        <div class="alert alert-danger" role="alert">
            {{ $ruleError }}
            @if ($ruleCode !== '') <span class="badge text-bg-dark ms-1">{{ $ruleCode }}</span> @endif
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body row g-3">
            <div class="col-md-4">
                <label class="form-label" for="opn-jenis">{{ __('Jenis') }} <span class="wajib">*</span></label>
                <select class="form-select @error('form.count_type') is-invalid @enderror" id="opn-jenis" wire:model.live="form.count_type">
                    @foreach ($types as $nilai => $label)
                        <option value="{{ $nilai }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('form.count_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="opn-tanggal">{{ __('Rencana mulai') }}</label>
                <input class="form-control" id="opn-tanggal" type="date" wire:model="form.planned_start">
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <div class="form-check">
                    <input class="form-check-input" id="opn-beku" type="checkbox" wire:model="form.freeze_bins"
                           @disabled($form['count_type'] === 'spot_check')>
                    <label class="form-check-label" for="opn-beku">{{ __('Bekukan bin selama sesi') }}</label> {{-- BR-OPN-02 --}}
                </div>
            </div>
            <div class="col-12">
                <label class="form-label">{{ __('Gudang') }} <span class="wajib">*</span></label>
                <div class="d-flex flex-wrap gap-3">
                    @foreach ($warehouses as $g)
                        <div class="form-check">
                            <input class="form-check-input" id="opn-g-{{ $g->id }}" type="checkbox" value="{{ $g->id }}" wire:model.live="form.warehouse_ids">
                            <label class="form-check-label" for="opn-g-{{ $g->id }}">{{ $g->code }} — {{ $g->name }}</label>
                        </div>
                    @endforeach
                </div>
                @error('form.warehouse_ids') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>
            @if ($zones->isNotEmpty())
                <div class="col-md-6">
                    <label class="form-label">{{ __('Zona (opsional)') }}</label>
                    <div class="d-flex flex-wrap gap-3">
                        @foreach ($zones as $z)
                            <div class="form-check">
                                <input class="form-check-input" id="opn-z-{{ $z->id }}" type="checkbox" value="{{ $z->id }}" wire:model="form.zone_ids">
                                <label class="form-check-label" for="opn-z-{{ $z->id }}">{{ $z->code }}</label>
                            </div>
                        @endforeach
                    </div>
                    @error('form.zone_ids') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
            @endif
            @if ($bins->isNotEmpty())
                <div class="col-md-6">
                    <label class="form-label" for="opn-bin">{{ __('Bin tertentu (opsional; wajib bin/item untuk pemeriksaan mendadak)') }}</label>
                    <select class="form-select" id="opn-bin" multiple size="6" wire:model="form.bin_ids">
                        @foreach ($bins as $b)
                            <option value="{{ $b->id }}">{{ $b->code }}{{ $b->count_flag ? ' ⚑ '.__('perlu dihitung') : '' }}</option>
                        @endforeach
                    </select>
                    @error('form.bin_ids') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
            @endif
            <div class="col-md-6">
                <label class="form-label" for="opn-item">{{ __('Item tertentu (opsional)') }}</label>
                <select class="form-select" id="opn-item" multiple size="5" wire:model="form.item_ids">
                    @foreach ($items as $i)
                        <option value="{{ $i->id }}">{{ $i->code }} — {{ $i->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">{{ __('Tim penghitung') }} <span class="wajib">*</span></label>
                <div class="d-flex flex-column gap-1">
                    @forelse ($counters as $u)
                        <div class="form-check">
                            <input class="form-check-input" id="opn-u-{{ $u->id }}" type="checkbox" value="{{ $u->id }}" wire:model="form.team_user_ids">
                            <label class="form-check-label" for="opn-u-{{ $u->id }}">{{ $u->name }}</label>
                        </div>
                    @empty
                        <span class="text-muted small">{{ __('Pilih gudang untuk melihat penghitung.') }}</span>
                    @endforelse
                </div>
                @error('form.team_user_ids') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>
            <div class="col-12">
                <label class="form-label" for="opn-ket">{{ __('Keterangan') }}</label>
                <input class="form-control" id="opn-ket" type="text" wire:model="form.notes" placeholder="{{ __('Opsional') }}">
            </div>
        </div>
    </div>

    <button class="btn btn-primary" type="button" wire:click="simpan">{{ __('Rencanakan sesi') }}</button>
</div>
