<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Pindahkan ke proyek lain') }}</h1>
            <p class="text-muted mb-0">{{ __('Dari') }} {{ $project->code }} — {{ $project->name }}. {{ __('Aset yang dipinjam pindah langsung antar proyek; stok Gudang Site dikirim lewat transfer biasa.') }}</p>
        </div>
        <a class="btn btn-outline-secondary" href="{{ route('projects.show', $project) }}">{{ __('Kembali ke proyek') }}</a>
    </div>

    @if ($ruleError !== '')
        <div class="alert alert-danger" role="alert">
            {{ $ruleError }} @if ($ruleCode !== '') <span class="badge text-bg-dark ms-1">{{ $ruleCode }}</span> @endif
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body row g-3">
            <div class="col-md-5">
                <label class="form-label" for="pindah-tujuan">{{ __('Proyek tujuan') }} <span class="wajib">*</span></label>
                <select class="form-select @error('form.to_project_id') is-invalid @enderror" id="pindah-tujuan" wire:model.live="form.to_project_id">
                    <option value="">{{ __('Pilih proyek…') }}</option>
                    @foreach ($tujuan as $p) <option value="{{ $p->id }}">{{ $p->code }} — {{ $p->name }}</option> @endforeach
                </select>
                @error('form.to_project_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3">
                <label class="form-label" for="pindah-gudang">{{ __('Gudang Site tujuan (stok)') }}</label>
                <select class="form-select @error('form.to_warehouse_id') is-invalid @enderror" id="pindah-gudang" wire:model="form.to_warehouse_id">
                    <option value="">—</option>
                    @foreach ($gudangTujuan as $g) <option value="{{ $g->id }}">{{ $g->code }}</option> @endforeach
                </select>
                @error('form.to_warehouse_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="pindah-catatan">{{ __('Catatan') }}</label>
                <input class="form-control" id="pindah-catatan" type="text" maxlength="255" wire:model="form.notes" placeholder="{{ __('Opsional, mis. proyek selesai') }}">
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><strong>{{ __('Aset yang sedang dipinjam') }}</strong></div>
        <ul class="list-group list-group-flush">
            @forelse ($asetDipinjam as $s)
                <li class="list-group-item" wire:key="aset-{{ $s->id }}">
                    <label class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" wire:model="aset.{{ $s->id }}">
                        <span class="form-check-label">{{ $s->item?->code }} · {{ __('Serial') }} {{ $s->serial_no }} <span class="text-muted small">{{ $s->item?->name }}</span></span>
                    </label>
                </li>
            @empty
                <li class="list-group-item text-muted">{{ __('Tidak ada aset yang dipinjam proyek ini.') }}</li>
            @endforelse
        </ul>
        @error('form.serial_ids') <div class="card-footer text-danger small">{{ $message }}</div> @enderror
    </div>

    <div class="card mb-3">
        <div class="card-header"><strong>{{ __('Stok Tersedia di Gudang Site') }}</strong> <span class="small text-muted">{{ __('isi 0 untuk tidak ikut') }}</span></div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Gudang Site') }}</th>
                        <th scope="col">{{ __('Item') }}</th>
                        <th class="text-end" scope="col">{{ __('Bisa dipindah') }}</th>
                        <th scope="col" style="width: 10rem">{{ __('Dipindah') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($stokSite as $b)
                        <tr wire:key="stok-{{ $b['kunci'] }}">
                            <td>{{ $b['gudang']?->code }}</td>
                            <td>{{ $b['item_code'] }} <div class="small text-muted">{{ $b['item_name'] }}</div></td>
                            <td class="text-end">{{ number_format($b['tersedia'], 2, ',', '.') }} <span class="small text-muted">{{ $b['satuan'] }}</span></td>
                            <td><input class="form-control form-control-sm" type="number" step="0.0001" min="0" wire:model="stok.{{ $b['kunci'] }}" aria-label="{{ __('Dipindah') }} {{ $b['item_code'] }}"></td>
                        </tr>
                    @empty
                        <tr><td class="text-center text-muted py-3" colspan="4">{{ __('Tidak ada stok Tersedia di Gudang Site proyek ini.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @error('form.stock') <div class="card-footer text-danger small">{{ $message }}</div> @enderror
    </div>

    <button class="btn btn-primary" type="button" wire:click="simpan">{{ __('Buat transfer pindahan') }}</button>
</div>
