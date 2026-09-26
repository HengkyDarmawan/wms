<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Retur baru') }}</h1>
            <p class="text-muted mb-0">{{ __('Pilih barang yang kembali ke gudang. Jumlah dalam satuan dasar item; serial dan potongan diretur utuh.') }}</p>
        </div>
        <a class="btn btn-outline-secondary" href="{{ route($rute.'.index') }}">{{ __('Kembali') }}</a>
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
                <label class="form-label" for="ret-proyek">{{ __('Proyek') }} <span class="wajib">*</span></label>
                <select class="form-select @error('form.project_id') is-invalid @enderror" id="ret-proyek" wire:model.live="form.project_id">
                    <option value="">{{ __('Pilih proyek…') }}</option>
                    @foreach ($projects as $p)
                        <option value="{{ $p->id }}">{{ $p->code }} — {{ $p->name }}</option>
                    @endforeach
                </select>
                @error('form.project_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="ret-tujuan">{{ __('Kembali ke gudang') }} <span class="wajib">*</span></label>
                <select class="form-select @error('form.to_warehouse_id') is-invalid @enderror" id="ret-tujuan" wire:model="form.to_warehouse_id">
                    <option value="">{{ __('Pilih gudang…') }}</option>
                    @foreach ($warehouses as $g)
                        <option value="{{ $g->id }}">{{ $g->code }} — {{ $g->name }}</option>
                    @endforeach
                </select>
                @error('form.to_warehouse_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="ret-angkut">{{ __('Pengangkutan') }} <span class="wajib">*</span></label>
                <select class="form-select @error('form.self_delivered') is-invalid @enderror" id="ret-angkut" wire:model="form.self_delivered">
                    <option value="1">{{ __('Diantar sendiri (tanpa SJ balik)') }}</option>
                    {{-- A-111/A-248: stok Gudang Site dipetik lalu dikirim; barang lain dijemput driver. --}}
                    <option value="0">{{ $portal ? __('Dijemput driver gudang (SJ jemput)') : __('SJ balik: stok Gudang Site dipetik, barang lain dijemput') }}</option>
                </select>
                @error('form.self_delivered') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-12">
                <label class="form-label" for="ret-ket">{{ __('Keterangan') }}</label>
                <input class="form-control" id="ret-ket" type="text" wire:model="form.notes" placeholder="{{ __('Opsional') }}">
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><strong>{{ __('Barang yang bisa diretur') }}</strong></div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Asal') }}</th>
                        <th scope="col">{{ __('Item') }}</th>
                        <th scope="col">{{ __('Lokasi / SJ asal') }}</th>
                        <th class="text-end" scope="col">{{ __('Maks') }}</th>
                        <th scope="col">{{ __('Jumlah retur') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($calon as $kunci => $c)
                        <tr wire:key="ret-calon-{{ $kunci }}">
                            <td class="small">{{ $c['source']->label() }}</td>
                            <td>{{ $c['item_code'] }} <div class="small text-muted">{{ $c['item_name'] }} @if ($c['tracking']) · {{ $c['tracking'] }} @endif</div></td>
                            <td class="small">{{ $c['bin_code'] ?? ($c['shipment_number'] ?? '—') }}</td>
                            <td class="text-end">{{ number_format((float) $c['max'], 2, ',', '.') }}</td>
                            <td style="max-width: 9rem">
                                <input class="form-control form-control-sm" type="number" step="0.0001" min="0" wire:model="qty.{{ $kunci }}">
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-muted py-4" colspan="5">{{ __('Tidak ada barang proyek ini yang bisa diretur.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @foreach (['key', 'qty_base'] as $f)
            @error('form.'.$f) <div class="text-danger small px-3 pb-2">{{ $message }}</div> @enderror
        @endforeach
    </div>

    <button class="btn btn-primary" type="button" wire:click="simpan">{{ __('Ajukan retur') }}</button>
</div>
