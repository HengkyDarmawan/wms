<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Simulasi approval') }}</h1>
            <p class="text-muted mb-0">{{ __('Siapa yang akan menyetujui dokumen ini bila diajukan sekarang? Memakai aturan aktif, pemisahan tugas, dan eskalasi yang sama dengan pengajuan sungguhan. Tidak ada yang disimpan.') }}</p>
        </div>
        @can('viewAny', App\Domain\Approval\Models\ApprovalRule::class)
            <a class="btn btn-outline-secondary" href="{{ route('approval.rules.index') }}">{{ __('Aturan approval') }}</a>
        @endcan
    </div>

    @if ($ruleError !== '')
        <div class="alert alert-danger" role="alert">{{ $ruleError }} <span class="badge text-bg-dark ms-1">{{ $ruleCode }}</span></div>
    @endif

    <div class="card mb-3">
        <div class="card-body row g-3">
            <div class="col-md-4">
                <label class="form-label" for="sim-jenis">{{ __('Jenis dokumen') }} <span class="wajib">*</span></label>
                <select class="form-select" id="sim-jenis" wire:model="documentType">
                    @foreach ($types as $nilai => $label) <option value="{{ $nilai }}">{{ $label }}</option> @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="sim-sumber">{{ __('Sumber data') }}</label>
                <select class="form-select" id="sim-sumber" wire:model.live="source">
                    <option value="dokumen">{{ __('Dokumen yang sudah ada') }}</option>
                    <option value="manual">{{ __('Isi manual') }}</option>
                </select>
            </div>

            @if ($source === 'dokumen')
                <div class="col-md-4">
                    <label class="form-label" for="sim-no">{{ __('Nomor dokumen') }} <span class="wajib">*</span></label>
                    <input class="form-control @error('sim.number') is-invalid @enderror" id="sim-no" type="text" wire:model="number" placeholder="REQ/…">
                    @error('sim.number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            @else
                <div class="col-md-4">
                    <label class="form-label" for="sim-gudang">{{ __('Gudang') }}</label>
                    <select class="form-select" id="sim-gudang" multiple size="3" wire:model="manual.warehouse_ids">
                        @foreach ($warehouses as $w) <option value="{{ $w->id }}">{{ $w->code }} — {{ $w->name }}</option> @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="sim-proyek">{{ __('Proyek') }}</label>
                    <select class="form-select" id="sim-proyek" wire:model="manual.project_id">
                        <option value="">—</option>
                        @foreach ($projects as $p) <option value="{{ $p->id }}">{{ $p->code }} — {{ $p->name }}</option> @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="sim-kategori">{{ __('Kategori barang') }}</label>
                    <select class="form-select" id="sim-kategori" multiple size="3" wire:model="manual.category_ids">
                        @foreach ($categories as $c) <option value="{{ $c->id }}">{{ $c->code }} — {{ $c->name }}</option> @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="sim-milik">{{ __('Model kepemilikan') }}</label>
                    <select class="form-select" id="sim-milik" multiple size="3" wire:model="manual.ownership_models">
                        @foreach ($ownerships as $nilai => $label) <option value="{{ $nilai }}">{{ $label }}</option> @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="sim-baris">{{ __('Jumlah baris') }}</label>
                    <input class="form-control" id="sim-baris" type="number" min="0" wire:model="manual.line_count">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="sim-qty">{{ __('Jumlah terbesar per baris') }}</label>
                    <input class="form-control" id="sim-qty" type="number" min="0" step="0.0001" wire:model="manual.max_line_qty">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="sim-pemohon">{{ __('Pemohon / pengaju') }}</label>
                    <select class="form-select" id="sim-pemohon" wire:model="manual.requester_id">
                        <option value="">—</option>
                        @foreach ($users as $u) <option value="{{ $u->id }}">{{ $u->name }}</option> @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="sim-vendor">{{ __('Jenis vendor') }}</label>
                    <select class="form-select" id="sim-vendor" wire:model="manual.vendor_type">
                        <option value="">—</option>
                        @foreach ($vendorTypes as $nilai => $label) <option value="{{ $nilai }}">{{ $label }}</option> @endforeach
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <div class="form-check">
                        <input class="form-check-input" id="sim-klien" type="checkbox" wire:model="manual.from_client">
                        <label class="form-check-label" for="sim-klien">{{ __('Permintaan dari klien') }}</label>
                    </div>
                </div>
            @endif
        </div>
        <div class="card-footer">
            <button class="btn btn-primary" type="button" wire:click="simulasikan">{{ __('Simulasikan') }}</button>
        </div>
    </div>

    @if ($hasil !== null)
        <div class="card">
            <div class="card-header">
                <strong>{{ __('Hasil') }}</strong>
                @if ($hasil['rule']) · {{ __('Aturan dipakai') }}: {{ $hasil['rule']['name'] }} @endif
            </div>
            <div class="card-body">
                @include('livewire.approval.partials.simulation-result', ['hasil' => $hasil])
            </div>
        </div>
    @endif
</div>
