<div class="mx-auto" style="max-width: 40rem">
    <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('Bin') }} {{ $tugas->bin?->code }}</h1>
            <p class="text-muted small mb-0">
                {{ $tugas->stockCount?->number }} · {{ $tugas->round === 2 ? __('Hitung ulang') : __('Hitungan pertama') }}
                · <span class="badge {{ $tugas->status->badge() }}">{{ $tugas->status->label() }}</span>
            </p>
        </div>
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('count-tasks.index') }}">{{ __('Kembali') }}</a>
    </div>

    @if ($ruleError !== '')
        <div class="alert alert-danger" role="alert">
            {{ $ruleError }}
            @if ($ruleCode !== '') <span class="badge text-bg-dark ms-1">{{ $ruleCode }}</span> @endif
        </div>
    @endif

    <p class="small text-muted">{{ __('Hitung buta: angka sistem tidak ditampilkan. Isi 0 bila barang tidak ada.') }}</p>

    @forelse ($lines as $l)
        <div class="card mb-2" wire:key="hitung-{{ $l->id }}">
            <div class="card-body">
                <div class="fw-semibold">{{ $l->item?->code }} <span class="text-muted fw-normal">{{ $l->item?->name }}</span></div>
                <div class="small text-muted mb-2">
                    @if ($l->lot) {{ __('Lot') }} {{ $l->lot->lot_no }} @endif
                    @if ($l->serial) {{ __('Serial') }} {{ $l->serial->serial_no }} @endif
                    @if ($l->piece) {{ __('Potongan') }} {{ $l->piece->piece_no }} @endif
                    @if ($l->stock_status->value !== 'available') · {{ $l->stock_status->label() }} @endif
                    @if ($l->is_unexpected) · {{ __('temuan') }} @endif
                </div>
                @if ($l->isUnitLine())
                    <select class="form-select form-select-lg @error('qty.qty.'.$l->id) is-invalid @enderror" wire:model="qty.{{ $l->id }}" @disabled(! $bolehIsi) aria-label="{{ __('Ada atau tidak') }}">
                        <option value="">{{ __('Pilih…') }}</option>
                        <option value="1">{{ __('Ada') }}</option>
                        <option value="0">{{ __('Tidak ada') }}</option>
                    </select>
                @else
                    <input class="form-control form-control-lg @error('qty.qty.'.$l->id) is-invalid @enderror" type="number" inputmode="decimal" step="0.0001" min="0"
                           wire:model="qty.{{ $l->id }}" @disabled(! $bolehIsi) placeholder="{{ __('Jumlah fisik') }}" aria-label="{{ __('Jumlah fisik') }}">
                @endif
                @error('qty.qty.'.$l->id) <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            </div>
        </div>
    @empty
        <div class="card mb-2"><div class="card-body text-muted">{{ __('Tidak ada barang tercatat di bin ini. Catat temuan bila ada barang.') }}</div></div>
    @endforelse

    @if ($bolehIsi)
        @if ($tambahTemuan)
            <div class="card border-info mb-3">
                <div class="card-header"><strong>{{ __('Temuan barang di luar catatan') }}</strong></div>
                <div class="card-body row g-2">
                    <div class="col-12">
                        <label class="form-label" for="temuan-item">{{ __('Item') }} <span class="wajib">*</span></label>
                        <select class="form-select @error('temuan.item_id') is-invalid @enderror" id="temuan-item" wire:model="temuan.item_id">
                            <option value="">{{ __('Pilih item…') }}</option>
                            @foreach ($items as $i)
                                <option value="{{ $i->id }}">{{ $i->code }} — {{ $i->name }}</option>
                            @endforeach
                        </select>
                        @error('temuan.item_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="temuan-lot">{{ __('Nomor lot') }}</label>
                        <input class="form-control" id="temuan-lot" type="text" wire:model="temuan.lot_no" placeholder="{{ __('Bila ber-lot') }}">
                        @error('temuan.lot_no') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="temuan-qty">{{ __('Jumlah') }} <span class="wajib">*</span></label>
                        <input class="form-control @error('temuan.qty') is-invalid @enderror" id="temuan-qty" type="number" inputmode="decimal" step="0.0001" min="0" wire:model="temuan.qty">
                        @error('temuan.qty') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <p class="small text-muted mb-0">{{ __('Serial, potongan, atau lot baru yang tidak tercatat dicatat lewat penyesuaian manual.') }}</p>
                </div>
                <div class="card-footer d-flex gap-2">
                    <button class="btn btn-info" type="button" wire:click="catatTemuan">{{ __('Catat temuan') }}</button>
                    <button class="btn btn-outline-secondary" type="button" wire:click="$set('tambahTemuan', false)">{{ __('Tutup') }}</button>
                </div>
            </div>
        @endif

        <div class="d-grid gap-2 mb-4">
            <button class="btn btn-outline-primary btn-lg" type="button" wire:click="simpan">{{ __('Simpan sementara') }}</button>
            <button class="btn btn-success btn-lg" type="button" wire:click="selesai" wire:confirm="{{ __('Tandai bin ini selesai dihitung? Hitungan tidak bisa diubah lagi.') }}">{{ __('Selesai hitung bin ini') }}</button>
            @unless ($tambahTemuan)
                <button class="btn btn-link" type="button" wire:click="$set('tambahTemuan', true)">{{ __('+ Ada barang di luar daftar') }}</button>
            @endunless
        </div>
    @endif
</div>
