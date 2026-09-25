<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ $nomor ? __('Ubah draf').' '.$nomor : __('Konversi baru') }}</h1>
            <p class="text-muted mb-0">{{ __('Pilih input dari stok Tersedia, lalu catat hasilnya. Jumlah dalam satuan dasar; potongan dipakai utuh. Stok baru bergerak saat CNV selesai.') }}</p>
        </div>
        <a class="btn btn-outline-secondary" href="{{ route('conversions.index') }}">{{ __('Kembali') }}</a>
    </div>

    @if ($ruleError !== '')
        <div class="alert alert-danger" role="alert">
            {{ $ruleError }}
            @if ($ruleCode !== '') <span class="badge text-bg-dark ms-1">{{ $ruleCode }}</span> @endif
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body row g-3">
            <div class="col-md-3">
                <label class="form-label" for="cnv-proyek">{{ __('Proyek') }} <span class="wajib">*</span></label>
                <select class="form-select @error('form.project_id') is-invalid @enderror" id="cnv-proyek" wire:model.live="form.project_id" @disabled($nomor)>
                    <option value="">{{ __('Pilih proyek…') }}</option>
                    @foreach ($projects as $p)
                        <option value="{{ $p->id }}">{{ $p->code }} — {{ $p->name }}</option>
                    @endforeach
                </select>
                @error('form.project_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text">{{ __('Persiapan stok memakai Proyek Internal.') }}</div>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="cnv-gudang">{{ __('Gudang') }} <span class="wajib">*</span></label>
                <select class="form-select @error('form.warehouse_id') is-invalid @enderror" id="cnv-gudang" wire:model.live="form.warehouse_id" @disabled($nomor)>
                    <option value="">{{ __('Pilih gudang…') }}</option>
                    @foreach ($warehouses as $g)
                        <option value="{{ $g->id }}">{{ $g->code }} — {{ $g->name }}</option>
                    @endforeach
                </select>
                @error('form.warehouse_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3">
                <label class="form-label" for="cnv-jenis">{{ __('Jenis konversi') }} <span class="wajib">*</span></label>
                <select class="form-select @error('form.conversion_type') is-invalid @enderror" id="cnv-jenis" wire:model.live="form.conversion_type">
                    @foreach ($types as $nilai => $label)
                        <option value="{{ $nilai }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('form.conversion_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3">
                <label class="form-label" for="cnv-ket">{{ __('Keterangan') }}</label>
                <input class="form-control" id="cnv-ket" type="text" wire:model="form.notes" placeholder="{{ __('Opsional') }}">
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><strong>{{ __('Input') }}</strong> <span class="small text-muted">{{ __('stok Tersedia di bin penyimpanan, item Bisa dipotong/dikonversi') }}</span></div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Item') }}</th>
                        <th scope="col">{{ __('Bin') }}</th>
                        <th class="text-end" scope="col">{{ __('Tersedia') }}</th>
                        <th scope="col">{{ __('Jumlah input') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($calon as $kunci => $c)
                        <tr wire:key="cnv-calon-{{ $kunci }}">
                            <td>
                                {{ $c['item_code'] }}
                                <div class="small text-muted">
                                    {{ $c['item_name'] }} @if ($c['tracking'] !== '') · {{ $c['tracking'] }} @endif
                                    @if ($c['is_offcut']) <span class="badge text-bg-info">{{ __('offcut') }}</span> @endif
                                </div>
                            </td>
                            <td class="small">
                                {{ $c['bin_code'] }}
                                @if ($c['frozen']) <span class="badge text-bg-warning">{{ __('Dibeku') }}</span> @endif
                            </td>
                            <td class="text-end">{{ number_format((float) $c['max'], 2, ',', '.') }} <span class="small text-muted">{{ $c['uom'] }}</span></td>
                            <td style="max-width: 10rem">
                                @if ($c['piece_id'] !== null)
                                    <div class="form-check">
                                        <input class="form-check-input" id="cnv-ambil-{{ $kunci }}" type="checkbox" value="1" wire:model.live="qty.{{ $kunci }}" @disabled($c['frozen'])>
                                        <label class="form-check-label small" for="cnv-ambil-{{ $kunci }}">{{ __('Pakai utuh') }}</label>
                                    </div>
                                @else
                                    <input class="form-control form-control-sm" type="number" step="0.0001" min="0" wire:model.live.debounce.500ms="qty.{{ $kunci }}" @disabled($c['frozen'])>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-muted py-4" colspan="4">
                                {{ $form['warehouse_id'] === '' ? __('Pilih proyek dan gudang dulu.') : __('Tidak ada stok yang bisa dikonversi di gudang ini.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @error('form.inputs') <div class="text-danger small px-3 pb-2">{{ $message }}</div> @enderror
    </div>

    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div><strong>{{ __('Hasil') }}</strong> <span class="small text-muted">{{ __('output boleh item lain; sisa selalu item input induknya') }}</span></div>
            <div class="btn-group btn-group-sm" role="group" aria-label="{{ __('Tambah hasil') }}">
                @foreach ($kinds as $nilai => $label)
                    <button class="btn btn-outline-primary" type="button" wire:click="tambahHasil('{{ $nilai }}')">+ {{ $label }}</button>
                @endforeach
            </div>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Jenis') }}</th>
                        <th scope="col">{{ __('Input induk') }}</th>
                        <th scope="col">{{ __('Item') }}</th>
                        <th scope="col">{{ __('Jumlah / panjang') }}</th>
                        <th scope="col">{{ __('× potong') }}</th>
                        <th scope="col">{{ __('Bin / lot / alasan') }}</th>
                        <th scope="col"><span class="visually-hidden">{{ __('Hapus') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($outputs as $i => $o)
                        <tr wire:key="cnv-hasil-{{ $i }}">
                            <td style="min-width: 9rem">
                                <select class="form-select form-select-sm" wire:model.live="outputs.{{ $i }}.kind" aria-label="{{ __('Jenis hasil') }}">
                                    @foreach ($kinds as $nilai => $label)
                                        <option value="{{ $nilai }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td style="min-width: 10rem">
                                <select class="form-select form-select-sm" wire:model.live="outputs.{{ $i }}.parent" aria-label="{{ __('Input induk') }}">
                                    <option value="">{{ __('Input pertama') }}</option>
                                    @foreach ($dipilih as $k => $d)
                                        <option value="{{ $k }}">{{ $d['item_code'] }} @if ($d['tracking'] !== '') · {{ $d['tracking'] }} @endif</option>
                                    @endforeach
                                </select>
                            </td>
                            <td style="min-width: 11rem">
                                @if ($o['kind'] === 'output')
                                    <select class="form-select form-select-sm" wire:model.live="outputs.{{ $i }}.item_id" aria-label="{{ __('Item output') }}">
                                        <option value="">{{ __('Pilih item…') }}</option>
                                        @foreach ($items as $it)
                                            <option value="{{ $it->id }}">{{ $it->code }} — {{ $it->name }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <span class="small text-muted">{{ __('item input induk') }}</span>
                                @endif
                            </td>
                            <td style="max-width: 8rem">
                                <input class="form-control form-control-sm" type="number" step="0.0001" min="0" wire:model.live.debounce.500ms="outputs.{{ $i }}.qty_base" aria-label="{{ __('Jumlah') }}">
                            </td>
                            <td style="max-width: 5rem">
                                <input class="form-control form-control-sm" type="number" step="1" min="1" max="100" wire:model.live.debounce.500ms="outputs.{{ $i }}.count" aria-label="{{ __('Jumlah potong') }}" @disabled($o['kind'] === 'kerf')>
                            </td>
                            <td style="min-width: 11rem">
                                @if (in_array($o['kind'], ['output', 'offcut'], true))
                                    <select class="form-select form-select-sm mb-1" wire:model="outputs.{{ $i }}.bin_id" aria-label="{{ __('Bin tujuan') }}">
                                        <option value="">{{ __('Bin input induk') }}</option>
                                        @foreach ($bins as $b)
                                            <option value="{{ $b->id }}">{{ $b->code }}</option>
                                        @endforeach
                                    </select>
                                    @if ($o['kind'] === 'output')
                                        <input class="form-control form-control-sm" type="text" maxlength="60" wire:model="outputs.{{ $i }}.lot_no" placeholder="{{ __('Nomor lot (item berlot lain)') }}" aria-label="{{ __('Nomor lot') }}">
                                    @endif
                                @elseif ($o['kind'] === 'waste')
                                    <select class="form-select form-select-sm" wire:model="outputs.{{ $i }}.reason" aria-label="{{ __('Alasan waste') }}">
                                        <option value="">{{ __('Alasan (opsional)') }}</option>
                                        @foreach ($alasanWaste as $kode => $label)
                                            <option value="{{ $kode }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <span class="small text-muted">{{ __('rugi potong, tanpa pergerakan stok') }}</span>
                                @endif
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-danger" type="button" wire:click="hapusHasil({{ $i }})" aria-label="{{ __('Hapus baris') }}"><i class="bi bi-x-lg"></i></button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-muted py-4" colspan="7">{{ __('Tambahkan minimal satu output atau offcut.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @error('form.outputs') <div class="text-danger small px-3 pb-2">{{ $message }}</div> @enderror
        <div class="card-footer small">
            {{ __('Neraca ukuran') }}:
            {{ __('input') }} <strong>{{ number_format($neraca['input'], 4, ',', '.') }}</strong>
            · {{ __('output + offcut + waste + kerf') }} <strong>{{ number_format($neraca['hasil'], 4, ',', '.') }}</strong>
            · {{ __('selisih') }}
            <strong class="{{ abs($neraca['selisih']) > 0.00005 ? 'text-danger' : 'text-success' }}">{{ number_format($neraca['selisih'], 4, ',', '.') }}</strong>
            <span class="text-muted">— {{ __('wajib nol untuk potong & ganti kemasan; offcut di bawah panjang minimum otomatis menjadi waste.') }}</span>
        </div>
    </div>

    <button class="btn btn-primary" type="button" wire:click="simpan">{{ $nomor ? __('Simpan draf') : __('Simpan sebagai draf') }}</button>
</div>
