@php($angka = fn ($n) => \App\Domain\Conversion\Support\ConversionPlanner::angka((float) $n))
<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ $nomor ? __('Ubah draf').' '.$nomor : __('Konversi baru') }}</h1>
            <p class="text-muted mb-0">{{ __('Pilih jenis konversi, tunjuk stok yang dipakai, lalu isi hasilnya. Sisa dan rugi potong dihitung otomatis; stok baru bergerak saat konversi selesai.') }}</p>
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
            <div class="col-md-4">
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
            <div class="col-md-4">
                <label class="form-label" for="cnv-gudang">{{ __('Gudang') }} <span class="wajib">*</span></label>
                <select class="form-select @error('form.warehouse_id') is-invalid @enderror" id="cnv-gudang" wire:model.live="form.warehouse_id" @disabled($nomor)>
                    <option value="">{{ __('Pilih gudang…') }}</option>
                    @foreach ($warehouses as $g)
                        <option value="{{ $g->id }}">{{ $g->code }} — {{ $g->name }}</option>
                    @endforeach
                </select>
                @error('form.warehouse_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="cnv-ket">{{ __('Keterangan') }}</label>
                <input class="form-control" id="cnv-ket" type="text" wire:model="form.notes" placeholder="{{ __('Opsional') }}">
            </div>
            <div class="col-12">
                <div class="form-label mb-1">{{ __('Jenis konversi') }} <span class="wajib">*</span></div>
                <div class="row g-2">
                    @foreach ($types as $t)
                        @php($ket = match ($t) {
                            \App\Domain\Conversion\Enums\ConversionType::Cut => __('Satu atau beberapa batang dipotong menjadi beberapa ukuran; sisa dan rugi potong dihitung per batang.'),
                            \App\Domain\Conversion\Enums\ConversionType::Repack => __('Isi kemasan dipindah ke item kemasan lain dengan satuan dasar sama; susut menjadi waste.'),
                            \App\Domain\Conversion\Enums\ConversionType::Assemble => __('Beberapa barang dirakit menjadi barang lain; tanpa neraca ukuran.'),
                            \App\Domain\Conversion\Enums\ConversionType::Disassemble => __('Satu barang dibongkar menjadi bagian-bagiannya; tanpa neraca ukuran.'),
                        })
                        <div class="col-6 col-lg-3">
                            <input class="btn-check" type="radio" id="cnv-jenis-{{ $t->value }}" value="{{ $t->value }}" wire:model.live="form.conversion_type" autocomplete="off">
                            <label class="btn btn-outline-primary w-100 h-100 text-start py-2" for="cnv-jenis-{{ $t->value }}">
                                <span class="fw-semibold d-block">{{ $t->label() }}</span>
                                <span class="small d-block" style="white-space: normal">{{ $ket }}</span>
                            </label>
                        </div>
                    @endforeach
                </div>
                @error('form.conversion_type') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>
        </div>
    </div>

    @if ($jenis === \App\Domain\Conversion\Enums\ConversionType::Cut)
        {{-- ===== Potong: satu batang → ukuran × jumlah ===== --}}
        <div class="card mb-3">
            <div class="card-header"><strong>{{ __('Batang yang dipotong') }}</strong> <span class="small text-muted">{{ __('potongan utuh dari stok Tersedia bin penyimpanan') }}</span></div>
            <div class="card-body row g-3">
                <div class="col-md-7">
                    <label class="form-label" for="cnv-batang">{{ __('Batang') }} <span class="wajib">*</span></label>
                    <select class="form-select @error('rencana.batang') is-invalid @enderror" id="cnv-batang" wire:model.live="batang" @disabled($form['warehouse_id'] === '')>
                        <option value="">{{ $form['warehouse_id'] === '' ? __('Pilih proyek dan gudang dulu…') : __('Pilih batang…') }}</option>
                        @foreach ($batangCalon as $kunci => $c)
                            <option value="{{ $kunci }}" @disabled($c['frozen'])>
                                {{ $c['item_code'] }} · {{ $c['tracking'] }} · {{ $angka($c['balance']) }} {{ $c['uom'] }} · {{ $c['bin_code'] }}@if ($c['is_offcut']) · offcut @endif @if ($c['frozen']) · {{ __('dibeku') }} @endif
                            </option>
                        @endforeach
                    </select>
                    @error('rencana.batang') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    @if ($form['warehouse_id'] !== '' && $batangCalon->isEmpty())
                        <div class="form-text text-danger">{{ __('Tidak ada potongan yang bisa dipotong di gudang ini (item harus ditandai Bisa dipotong).') }}</div>
                    @endif
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="cnv-pindai">{{ __('Pindai label potongan') }}</label>
                    <input class="form-control @error('kodePindai') is-invalid @enderror" id="cnv-pindai" type="text" data-scan autocomplete="off"
                           wire:model="kodePindai" wire:keydown.enter.prevent="pindai" placeholder="{{ __('Nomor potongan') }}" @disabled($form['warehouse_id'] === '')>
                    @error('kodePindai') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                @if ($batangTerpilih)
                    <div class="col-12 small text-muted">
                        {{ $batangTerpilih['item_name'] }} · {{ __('panjang') }} <strong>{{ $angka($batangTerpilih['balance']) }} {{ $batangTerpilih['uom'] }}</strong>
                        · {{ __('kerf per potongan') }} <strong>{{ $batangTerpilih['kerf'] === null ? __('belum diatur') : $angka($batangTerpilih['kerf']).' '.$batangTerpilih['uom'] }}</strong>
                        · {{ __('sisa minimal jadi offcut') }} <strong>{{ $batangTerpilih['min_offcut'] === null ? '—' : $angka($batangTerpilih['min_offcut']).' '.$batangTerpilih['uom'] }}</strong>
                        ({{ __('di bawah itu otomatis waste') }})
                    </div>
                @endif
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div><strong>{{ __('Hasil potong') }}</strong> <span class="small text-muted">{{ __('satu baris = satu ukuran; item hasil boleh item lain bersatuan sama') }}</span></div>
                <button class="btn btn-sm btn-outline-primary" type="button" wire:click="tambahPotong">+ {{ __('Tambah ukuran') }}</button>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col" style="width: 40%">{{ __('Item hasil') }}</th>
                            <th scope="col" style="width: 22%">{{ __('Panjang') }} ({{ $batangTerpilih['uom'] ?? __('satuan dasar') }}) <span class="wajib">*</span></th>
                            <th scope="col" style="width: 18%">{{ __('Jumlah potongan') }} <span class="wajib">*</span></th>
                            <th scope="col" class="text-end">{{ __('Subtotal') }}</th>
                            <th scope="col"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($potong as $i => $p)
                            @php($galat = $errors->first('rencana.potong.'.$p['id']))
                            <tr wire:key="potong-{{ $p['id'] }}" @class(['table-danger' => $galat !== ''])>
                                <td>
                                    <select class="form-select form-select-sm" wire:model.live="potong.{{ $i }}.item_id" aria-label="{{ __('Item hasil') }}">
                                        <option value="">{{ $batangTerpilih ? __('Sama dengan batang').' ('.$batangTerpilih['item_code'].')' : __('Sama dengan batang') }}</option>
                                        @foreach ($items as $it)
                                            <option value="{{ $it->id }}">{{ $it->code }} — {{ $it->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><input class="form-control form-control-sm" type="number" step="0.0001" min="0" wire:model.live.debounce.500ms="potong.{{ $i }}.length" aria-label="{{ __('Panjang') }}"></td>
                                <td><input class="form-control form-control-sm" type="number" step="1" min="1" max="100" wire:model.live.debounce.500ms="potong.{{ $i }}.count" aria-label="{{ __('Jumlah potongan') }}"></td>
                                <td class="text-end">{{ is_numeric($p['length']) && is_numeric($p['count']) ? $angka((float) $p['length'] * (int) $p['count']) : '—' }}</td>
                                <td class="text-end"><button class="btn btn-sm btn-outline-danger" type="button" wire:click="hapusPotong('{{ $p['id'] }}')" aria-label="{{ __('Hapus baris') }}"><i class="bi bi-x-lg"></i></button></td>
                            </tr>
                            @if ($galat !== '')
                                <tr wire:key="potong-galat-{{ $p['id'] }}"><td class="text-danger small pt-0" colspan="5">{{ $galat }}</td></tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
            @error('rencana.potong') <div class="card-footer text-danger small">{{ $message }}</div> @enderror
        </div>

        {{-- A-253: batang lain dalam CNV yang sama — pola sama (salin) atau berbeda. --}}
        @foreach ($tambahan as $bi => $b)
            @php($calonB = $batangCalon[$b['key']] ?? null)
            @php($kunciAsli = $calonB['key'] ?? '')
            <div class="card mb-3" wire:key="batang-{{ $b['id'] }}">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <strong>{{ __('Batang :n', ['n' => $bi + 2]) }}</strong>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-outline-primary" type="button" wire:click="tambahPotongBatang('{{ $b['id'] }}')">+ {{ __('Tambah ukuran') }}</button>
                        <button class="btn btn-sm btn-outline-danger" type="button" wire:click="hapusBatang('{{ $b['id'] }}')">{{ __('Hapus batang') }}</button>
                    </div>
                </div>
                <div class="card-body pb-0">
                    <select class="form-select form-select-sm mb-2" wire:model.live="tambahan.{{ $bi }}.key" aria-label="{{ __('Batang') }} {{ $bi + 2 }}">
                        <option value="">{{ __('Pilih batang…') }}</option>
                        @foreach ($batangCalon as $kunci => $c)
                            <option value="{{ $kunci }}" @disabled($c['frozen'])>{{ $c['item_code'] }} · {{ $c['tracking'] }} · {{ $angka($c['balance']) }} {{ $c['uom'] }} · {{ $c['bin_code'] }}</option>
                        @endforeach
                    </select>
                    @if ($kunciAsli !== '' && $errors->first('rencana.batang@'.$kunciAsli) !== '') <div class="text-danger small mb-2">{{ $errors->first('rencana.batang@'.$kunciAsli) }}</div> @endif
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <tbody>
                            @foreach ($b['potong'] as $pi => $p)
                                @php($galat = $errors->first('rencana.potong.'.$p['id']))
                                <tr wire:key="batang-{{ $b['id'] }}-{{ $p['id'] }}" @class(['table-danger' => $galat !== ''])>
                                    <td style="width: 40%">
                                        <select class="form-select form-select-sm" wire:model.live="tambahan.{{ $bi }}.potong.{{ $pi }}.item_id" aria-label="{{ __('Item hasil') }}">
                                            <option value="">{{ __('Sama dengan batang') }}</option>
                                            @foreach ($items as $it) <option value="{{ $it->id }}">{{ $it->code }} — {{ $it->name }}</option> @endforeach
                                        </select>
                                    </td>
                                    <td style="width: 22%"><input class="form-control form-control-sm" type="number" step="0.0001" min="0" wire:model.live.debounce.500ms="tambahan.{{ $bi }}.potong.{{ $pi }}.length" aria-label="{{ __('Panjang') }}"></td>
                                    <td style="width: 18%"><input class="form-control form-control-sm" type="number" step="1" min="1" max="100" wire:model.live.debounce.500ms="tambahan.{{ $bi }}.potong.{{ $pi }}.count" aria-label="{{ __('Jumlah potongan') }}"></td>
                                    <td class="text-end">{{ is_numeric($p['length']) && is_numeric($p['count']) ? $angka((float) $p['length'] * (int) $p['count']) : '—' }}</td>
                                    <td class="text-end"><button class="btn btn-sm btn-outline-danger" type="button" wire:click="hapusPotongBatang('{{ $b['id'] }}', '{{ $p['id'] }}')" aria-label="{{ __('Hapus baris') }}"><i class="bi bi-x-lg"></i></button></td>
                                </tr>
                                @if ($galat !== '')
                                    <tr><td class="text-danger small pt-0" colspan="5">{{ $galat }}</td></tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($kunciAsli !== '' && $errors->first('rencana.potong@'.$kunciAsli) !== '') <div class="card-footer text-danger small">{{ $errors->first('rencana.potong@'.$kunciAsli) }}</div> @endif
            </div>
        @endforeach

        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <button class="btn btn-sm btn-outline-primary" type="button" wire:click="tambahBatang" @disabled($form['warehouse_id'] === '')>+ {{ __('Tambah batang (pola berbeda)') }}</button>
            <span class="small text-muted">{{ __('atau') }}</span>
            <div class="input-group input-group-sm" style="max-width: 22rem">
                <span class="input-group-text">{{ __('Salin pola batang 1 ke') }}</span>
                <input class="form-control @error('jumlahSalin') is-invalid @enderror" type="number" min="1" max="100" wire:model="jumlahSalin" aria-label="{{ __('Jumlah batang') }}">
                <button class="btn btn-outline-primary" type="button" wire:click="salinPola" @disabled(! $batangTerpilih)>{{ __('batang (FIFO)') }}</button>
            </div>
            @error('jumlahSalin') <div class="small text-danger w-100">{{ $message }}</div> @enderror
        </div>
    @else
        {{-- ===== Ganti kemasan / Rakit / Bongkar: input dari stok, hasil bebas ===== --}}
        <div class="card mb-3">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div><strong>{{ __('Input') }}</strong> <span class="small text-muted">{{ __('stok Tersedia di bin penyimpanan; item ditandai Bisa dipotong/dikonversi') }}</span></div>
                <div class="d-flex gap-2">
                    <input class="form-control form-control-sm" type="search" wire:model.live.debounce.400ms="cari" placeholder="{{ __('Cari item / bin…') }}" aria-label="{{ __('Cari input') }}" style="max-width: 12rem">
                    <input class="form-control form-control-sm @error('kodePindai') is-invalid @enderror" type="text" data-scan autocomplete="off" wire:model="kodePindai" wire:keydown.enter.prevent="pindai" placeholder="{{ __('Pindai') }}" aria-label="{{ __('Pindai') }}" style="max-width: 10rem">
                </div>
            </div>
            @error('kodePindai') <div class="px-3 pt-2 text-danger small">{{ $message }}</div> @enderror
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Item') }}</th>
                            <th scope="col">{{ __('Bin') }}</th>
                            <th class="text-end" scope="col">{{ __('Tersedia') }}</th>
                            <th scope="col" style="width: 11rem">{{ __('Jumlah input') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($calon as $kunci => $c)
                            <tr wire:key="cnv-calon-{{ $kunci }}" @class(['table-active' => is_numeric($qty[$kunci] ?? null) && (float) $qty[$kunci] > 0])>
                                <td>
                                    {{ $c['item_code'] }}
                                    <div class="small text-muted">{{ $c['item_name'] }} @if ($c['tracking'] !== '') · {{ $c['tracking'] }} @endif @if ($c['is_offcut']) <span class="badge text-bg-info">offcut</span> @endif</div>
                                </td>
                                <td class="small">{{ $c['bin_code'] }} @if ($c['frozen']) <span class="badge text-bg-warning">{{ __('Dibeku') }}</span> @endif</td>
                                <td class="text-end">{{ $angka($c['max']) }} <span class="small text-muted">{{ $c['uom'] }}</span></td>
                                <td>
                                    @if ($c['piece_id'] !== null)
                                        <div class="form-check">
                                            <input class="form-check-input" id="cnv-ambil-{{ $kunci }}" type="checkbox" value="1" wire:model.live="qty.{{ $kunci }}" @disabled($c['frozen'])>
                                            <label class="form-check-label small" for="cnv-ambil-{{ $kunci }}">{{ __('Pakai utuh') }}</label>
                                        </div>
                                    @else
                                        <input class="form-control form-control-sm" type="number" step="0.0001" min="0" wire:model.live.debounce.500ms="qty.{{ $kunci }}" @disabled($c['frozen']) aria-label="{{ __('Jumlah input') }} {{ $c['item_code'] }}">
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="text-center text-muted py-4" colspan="4">
                                    {{ $form['warehouse_id'] === '' ? __('Pilih proyek dan gudang dulu.') : __('Tidak ada stok yang cocok.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @error('rencana.inputs') <div class="card-footer text-danger small">{{ $message }}</div> @enderror
        </div>

        <div class="card mb-3">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div><strong>{{ __('Hasil') }}</strong>
                    <span class="small text-muted">{{ $jenis === \App\Domain\Conversion\Enums\ConversionType::Repack ? __('item kemasan tujuan bersatuan sama; susut dihitung otomatis') : __('item yang dihasilkan; boleh menambah baris waste bila ada bagian rusak') }}</span>
                </div>
                <div class="btn-group btn-group-sm" role="group">
                    <button class="btn btn-outline-primary" type="button" wire:click="tambahHasil('output')">+ {{ __('Item hasil') }}</button>
                    @if ($jenis !== \App\Domain\Conversion\Enums\ConversionType::Repack)
                        <button class="btn btn-outline-secondary" type="button" wire:click="tambahHasil('waste')">+ {{ __('Waste') }}</button>
                    @endif
                </div>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col" style="width: 12%">{{ __('Jenis') }}</th>
                            <th scope="col" style="width: 34%">{{ __('Item') }}</th>
                            <th scope="col" style="width: 16%">{{ __('Jumlah') }} <span class="wajib">*</span></th>
                            <th scope="col" style="width: 18%">{{ __('Bin tujuan') }}</th>
                            <th scope="col" style="width: 15%">{{ __('Nomor lot') }}</th>
                            <th scope="col"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($hasil as $i => $h)
                            @php($galat = $errors->first('rencana.hasil.'.$h['id']))
                            <tr wire:key="hasil-{{ $h['id'] }}" @class(['table-danger' => $galat !== ''])>
                                <td class="small">{{ $h['kind'] === 'waste' ? __('Waste') : __('Hasil') }}</td>
                                <td>
                                    @if ($h['kind'] === 'waste')
                                        <span class="small text-muted">{{ __('item input pertama, ke bin Waste') }}</span>
                                    @else
                                        <select class="form-select form-select-sm" wire:model.live="hasil.{{ $i }}.item_id" aria-label="{{ __('Item hasil') }}">
                                            <option value="">{{ __('Pilih item…') }}</option>
                                            @foreach ($items as $it)
                                                <option value="{{ $it->id }}">{{ $it->code }} — {{ $it->name }} ({{ $it->baseUom?->code }})</option>
                                            @endforeach
                                        </select>
                                    @endif
                                </td>
                                <td><input class="form-control form-control-sm" type="number" step="0.0001" min="0" wire:model.live.debounce.500ms="hasil.{{ $i }}.qty" aria-label="{{ __('Jumlah') }}"></td>
                                <td>
                                    @if ($h['kind'] !== 'waste')
                                        <select class="form-select form-select-sm" wire:model="hasil.{{ $i }}.bin_id" aria-label="{{ __('Bin tujuan') }}">
                                            <option value="">{{ __('Bin input pertama') }}</option>
                                            @foreach ($bins as $b)
                                                <option value="{{ $b->id }}">{{ $b->code }}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                </td>
                                <td>
                                    @php($berlot = $h['kind'] !== 'waste' && ($items->firstWhere('id', (int) $h['item_id'])?->tracking_mode) === \App\Domain\Master\Enums\TrackingMode::Lot)
                                    @if ($berlot)
                                        <input class="form-control form-control-sm" type="text" maxlength="60" wire:model="hasil.{{ $i }}.lot_no" placeholder="{{ __('Lot baru / warisan') }}" aria-label="{{ __('Nomor lot') }}">
                                    @endif
                                </td>
                                <td class="text-end"><button class="btn btn-sm btn-outline-danger" type="button" wire:click="hapusHasil('{{ $h['id'] }}')" aria-label="{{ __('Hapus baris') }}"><i class="bi bi-x-lg"></i></button></td>
                            </tr>
                            @if ($galat !== '')
                                <tr wire:key="hasil-galat-{{ $h['id'] }}"><td class="text-danger small pt-0" colspan="6">{{ $galat }}</td></tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
            @error('rencana.hasil') <div class="card-footer text-danger small">{{ $message }}</div> @enderror
        </div>
    @endif

    {{-- ===== Ringkasan otomatis dari perencana (sama dengan yang disimpan) ===== --}}
    <div class="card mb-3 border-primary">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <strong>{{ __('Ringkasan (otomatis)') }}</strong>
            @if ($jenis === \App\Domain\Conversion\Enums\ConversionType::Cut || $jenis === \App\Domain\Conversion\Enums\ConversionType::Repack)
                <div class="d-flex align-items-center gap-2 small">
                    <label class="mb-0" for="cnv-bin-hasil">{{ __('Bin hasil') }}</label>
                    <select class="form-select form-select-sm" id="cnv-bin-hasil" wire:model.live="form.bin_id" style="max-width: 14rem">
                        <option value="">{{ __('Sama dengan bin input') }}</option>
                        @foreach ($bins as $b)
                            <option value="{{ $b->id }}">{{ $b->code }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
        </div>
        <div class="card-body">
            @php($r = $rencana['summary'])
            @if ($r['kalimat'] !== '')
                <p class="fs-5 mb-2">{{ $r['kalimat'] }}</p>
            @endif
            @if ($jenis === \App\Domain\Conversion\Enums\ConversionType::Cut && ($r['jumlah_batang'] ?? 0) > 1)
                {{-- A-253: ringkasan per batang. --}}
                <ul class="small mb-2">
                    @foreach ($r['batang'] as $n => $bg)
                        <li>{{ __('Batang :n', ['n' => $n + 1]) }} ({{ $bg['item_code'] }} {{ $bg['tracking'] }}): {{ $bg['kalimat'] }}</li>
                    @endforeach
                    <li>{{ __('Total') }}: {{ __('dipakai') }} {{ $angka($r['dipakai']) }} {{ $r['uom'] }} ({{ $r['potongan'] }} {{ __('potongan') }}), {{ __('kerf') }} {{ $angka($r['kerf']) }} {{ $r['uom'] }}, {{ __('sisa') }} {{ $angka($r['sisa']) }} {{ $r['uom'] }}</li>
                </ul>
            @elseif ($jenis === \App\Domain\Conversion\Enums\ConversionType::Cut && ($r['panjang'] ?? 0) > 0)
                <ul class="small mb-2">
                    <li>{{ __('Dipakai') }}: {{ $angka($r['dipakai']) }} {{ $r['uom'] }} ({{ $r['potongan'] }} {{ __('potongan') }})</li>
                    <li>{{ __('Rugi potong (kerf)') }}: {{ $r['potongan'] }} × {{ $r['kerf_per_potong'] === null ? '0' : $angka($r['kerf_per_potong']) }} = {{ $angka($r['kerf']) }} {{ $r['uom'] }}</li>
                    <li>
                        {{ __('Sisa') }}: {{ $angka($r['sisa']) }} {{ $r['uom'] }}
                        @if ($r['sisa_jenis'] === 'offcut') → <span class="badge text-bg-success">{{ __('OFFCUT') }}</span> {{ __('kembali ke stok') }}{{ $r['min_offcut'] !== null ? ' (≥ '.$angka($r['min_offcut']).' '.$r['uom'].')' : '' }}
                        @elseif ($r['sisa_jenis'] === 'waste') → <span class="badge text-bg-danger">{{ __('WASTE') }}</span> {{ __('di bawah sisa minimal') }} {{ $angka($r['min_offcut']) }} {{ $r['uom'] }}
                        @elseif ($r['sisa'] < 0) → <span class="badge text-bg-danger">{{ __('melebihi batang') }}</span>
                        @else → {{ __('habis tanpa sisa') }}
                        @endif
                    </li>
                </ul>
            @elseif ($jenis === \App\Domain\Conversion\Enums\ConversionType::Repack && ($r['input'] ?? 0) > 0)
                <ul class="small mb-2">
                    <li>{{ __('Input') }}: {{ $angka($r['input']) }} {{ $r['uom'] }} · {{ __('Hasil') }}: {{ $angka($r['output']) }} {{ $r['uom'] }}</li>
                    <li>{{ __('Susut') }}: {{ $angka($r['susut']) }} {{ $r['uom'] }} @if ($r['susut'] > 0) → <span class="badge text-bg-danger">{{ __('WASTE') }}</span> {{ __('otomatis ke bin Waste') }} @endif</li>
                </ul>
            @endif
            @foreach ($r['peringatan'] as $w)
                <div class="small text-warning-emphasis"><i class="bi bi-exclamation-triangle"></i> {{ $w }}</div>
            @endforeach
            @if ($rencana['errors'] !== [])
                <div class="small text-danger mt-1"><i class="bi bi-x-circle"></i> {{ reset($rencana['errors']) }}</div>
            @elseif ($r['kalimat'] !== '')
                <div class="small text-success mt-1"><i class="bi bi-check-circle"></i> {{ __('Neraca seimbang; siap disimpan.') }}</div>
            @endif
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2">
        <button class="btn btn-outline-primary" type="button" wire:click="simpan">{{ __('Simpan draf') }}</button>
        <button class="btn btn-primary" type="button" wire:click="simpanDanSelesaikan"
                wire:confirm="{{ $butuhApproval ? __('Simpan dan ajukan ke approval?') : __('Simpan dan selesaikan? Input keluar dan hasil masuk stok.') }}">
            {{ $butuhApproval ? __('Simpan & ajukan') : __('Simpan & selesaikan') }}
        </button>
        <span class="small text-muted align-self-center">{{ $butuhApproval ? __('Ada aturan approval untuk konversi di gudang/proyek ini.') : __('Tanpa aturan approval: stok langsung bergerak saat diselesaikan.') }}</span>
    </div>
</div>
