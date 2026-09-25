<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('BA waste baru') }}</h1>
            <p class="text-muted mb-0">{{ __('Pilih isi bin Waste yang ditutup. Potongan dan serial ditutup utuh. Setelah disetujui, BA ditutup dengan foto atau nomor berita acara bertanda tangan.') }}</p>
        </div>
        <a class="btn btn-outline-secondary" href="{{ route('waste-disposals.index') }}">{{ __('Kembali') }}</a>
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
                <label class="form-label" for="wst-gudang">{{ __('Gudang') }} <span class="wajib">*</span></label>
                <select class="form-select @error('form.warehouse_id') is-invalid @enderror" id="wst-gudang" wire:model.live="form.warehouse_id">
                    <option value="">{{ __('Pilih gudang…') }}</option>
                    @foreach ($warehouses as $g)
                        <option value="{{ $g->id }}">{{ $g->code }} — {{ $g->name }}</option>
                    @endforeach
                </select>
                @error('form.warehouse_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3">
                <label class="form-label" for="wst-proyek">{{ __('Proyek') }} <span class="wajib">*</span></label>
                <select class="form-select @error('form.project_id') is-invalid @enderror" id="wst-proyek" wire:model="form.project_id">
                    <option value="">{{ __('Pilih proyek…') }}</option>
                    @foreach ($projects as $p)
                        <option value="{{ $p->id }}">{{ $p->code }} — {{ $p->name }}</option>
                    @endforeach
                </select>
                @error('form.project_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text">{{ __('Waste gudang umum memakai Proyek Internal.') }}</div>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="wst-disposisi">{{ __('Disposisi') }} <span class="wajib">*</span></label>
                <select class="form-select @error('form.disposition') is-invalid @enderror" id="wst-disposisi" wire:model.live="form.disposition">
                    <option value="">{{ __('Pilih disposisi…') }}</option>
                    @foreach ($dispositions as $nilai => $label)
                        <option value="{{ $nilai }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('form.disposition') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3">
                @if ($form['disposition'] === 'reused')
                    <label class="form-label" for="wst-bin">{{ __('Bin tujuan') }} <span class="wajib">*</span></label>
                    <select class="form-select @error('form.target_bin_id') is-invalid @enderror" id="wst-bin" wire:model="form.target_bin_id">
                        <option value="">{{ __('Pilih bin penyimpanan…') }}</option>
                        @foreach ($bins as $b)
                            <option value="{{ $b->id }}">{{ $b->code }}</option>
                        @endforeach
                    </select>
                    @error('form.target_bin_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                @else
                    <label class="form-label" for="wst-ket">{{ __('Keterangan') }}</label>
                    <input class="form-control" id="wst-ket" type="text" wire:model="form.notes" placeholder="{{ __('Opsional') }}">
                @endif
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><strong>{{ __('Isi bin Waste') }}</strong></div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Item') }}</th>
                        <th scope="col">{{ __('Bin') }}</th>
                        <th scope="col">{{ __('Kondisi') }}</th>
                        <th class="text-end" scope="col">{{ __('Bisa ditutup') }}</th>
                        <th scope="col">{{ __('Jumlah') }}</th>
                        <th scope="col">{{ __('Alasan') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($calon as $kunci => $c)
                        <tr wire:key="wst-calon-{{ $kunci }}">
                            <td>{{ $c['item_code'] }} <div class="small text-muted">{{ $c['item_name'] }} @if ($c['tracking'] !== '') · {{ $c['tracking'] }} @endif</div></td>
                            <td class="small">
                                {{ $c['bin_code'] }}
                                @if ($c['frozen']) <span class="badge text-bg-warning">{{ __('Dibeku') }}</span> @endif
                            </td>
                            <td class="small">{{ $c['stock_status']->label() }}</td>
                            <td class="text-end">
                                {{ number_format((float) $c['max'], 2, ',', '.') }} <span class="small text-muted">{{ $c['uom'] }}</span>
                                @if ($c['held'] > 0) <div class="small text-muted">{{ __('dipegang BA lain') }} {{ number_format((float) $c['held'], 2, ',', '.') }}</div> @endif
                            </td>
                            <td style="max-width: 9rem">
                                @if ($c['whole'])
                                    <div class="form-check">
                                        <input class="form-check-input" id="wst-ambil-{{ $kunci }}" type="checkbox" value="1" wire:model="qty.{{ $kunci }}" @disabled($c['frozen'])>
                                        <label class="form-check-label small" for="wst-ambil-{{ $kunci }}">{{ __('Tutup utuh') }}</label>
                                    </div>
                                @else
                                    <input class="form-control form-control-sm" type="number" step="0.0001" min="0" wire:model="qty.{{ $kunci }}" @disabled($c['frozen'])>
                                @endif
                            </td>
                            <td style="min-width: 10rem">
                                <select class="form-select form-select-sm" wire:model="reason.{{ $kunci }}" aria-label="{{ __('Alasan waste') }}">
                                    <option value="">{{ __('Opsional') }}</option>
                                    @foreach ($alasanWaste as $kode => $label)
                                        <option value="{{ $kode }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-muted py-4" colspan="6">
                                {{ $form['warehouse_id'] === '' ? __('Pilih gudang dulu.') : __('Bin Waste gudang ini kosong atau seluruh isinya sudah dipegang BA lain.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @error('form.lines') <div class="text-danger small px-3 pb-2">{{ $message }}</div> @enderror
    </div>

    <button class="btn btn-primary" type="button" wire:click="simpan">{{ __('Ajukan BA waste') }}</button>
</div>
