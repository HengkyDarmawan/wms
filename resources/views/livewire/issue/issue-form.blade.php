<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ $nomor ? __('Ubah draf').' '.$nomor : __('Pemakaian baru') }}</h1>
            <p class="text-muted mb-0">{{ __('Catat barang habis pakai yang dipakai proyek dari Gudang Site. Jumlah dalam satuan dasar; serial dipakai per unit, potongan dipakai utuh. Stok baru keluar saat dikonfirmasi.') }}</p>
        </div>
        <a class="btn btn-outline-secondary" href="{{ route('issues.index') }}">{{ __('Kembali') }}</a>
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
                <label class="form-label" for="isu-proyek">{{ __('Proyek') }} <span class="wajib">*</span></label>
                <select class="form-select @error('form.project_id') is-invalid @enderror" id="isu-proyek" wire:model.live="form.project_id" @disabled($nomor)>
                    <option value="">{{ __('Pilih proyek…') }}</option>
                    @foreach ($projects as $p)
                        <option value="{{ $p->id }}">{{ $p->code }} — {{ $p->name }}</option>
                    @endforeach
                </select>
                @error('form.project_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="isu-site">{{ __('Gudang Site') }} <span class="wajib">*</span></label>
                <select class="form-select @error('form.warehouse_id') is-invalid @enderror" id="isu-site" wire:model.live="form.warehouse_id" @disabled($nomor)>
                    <option value="">{{ __('Pilih Gudang Site…') }}</option>
                    @foreach ($sites as $g)
                        <option value="{{ $g->id }}">{{ $g->code }} — {{ $g->name }}</option>
                    @endforeach
                </select>
                @error('form.warehouse_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                @if ($form['project_id'] !== '' && $sites->isEmpty())
                    <div class="form-text text-danger">{{ __('Proyek ini belum punya Gudang Site aktif dalam cakupan Anda.') }}</div>
                @endif
            </div>
            <div class="col-md-4">
                <label class="form-label" for="isu-ket">{{ __('Keterangan') }}</label>
                <input class="form-control" id="isu-ket" type="text" wire:model="form.notes" placeholder="{{ __('Opsional') }}">
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><strong>{{ __('Stok Gudang Site yang bisa dipakai') }}</strong></div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Item') }}</th>
                        <th scope="col">{{ __('Bin') }}</th>
                        <th class="text-end" scope="col">{{ __('Tersedia') }}</th>
                        <th scope="col">{{ __('Jumlah dipakai') }}</th>
                        <th scope="col">{{ __('Keperluan') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($calon as $kunci => $c)
                        <tr wire:key="isu-calon-{{ $kunci }}">
                            <td>{{ $c['item_code'] }} <div class="small text-muted">{{ $c['item_name'] }} @if ($c['tracking'] !== '') · {{ $c['tracking'] }} @endif</div></td>
                            <td class="small">
                                {{ $c['bin_code'] }}
                                @if ($c['frozen']) <span class="badge text-bg-warning">{{ __('Dibeku') }}</span> @endif
                            </td>
                            <td class="text-end">{{ number_format((float) $c['max'], 2, ',', '.') }} <span class="small text-muted">{{ $c['uom'] }}</span></td>
                            <td style="max-width: 9rem">
                                <input class="form-control form-control-sm" type="number" step="0.0001" min="0" wire:model="qty.{{ $kunci }}" @disabled($c['frozen'])>
                            </td>
                            <td>
                                <input class="form-control form-control-sm" type="text" maxlength="255" wire:model="note.{{ $kunci }}" placeholder="{{ __('mis. pengecoran pondasi STA 0+100') }}" @disabled($c['frozen'])>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-muted py-4" colspan="5">
                                {{ $form['warehouse_id'] === '' ? __('Pilih proyek dan Gudang Site dulu.') : __('Tidak ada stok habis pakai yang tersedia di Gudang Site ini. Ajukan permintaan material untuk kekurangannya.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @foreach (['key', 'qty_base'] as $f)
            @error('form.'.$f) <div class="text-danger small px-3 pb-2">{{ $message }}</div> @enderror
        @endforeach
    </div>

    <button class="btn btn-primary" type="button" wire:click="simpan">{{ $nomor ? __('Simpan draf') : __('Simpan sebagai draf') }}</button>
</div>
