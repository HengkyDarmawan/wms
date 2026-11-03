<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Sesi opname baru') }}</h1>
            <p class="text-muted mb-0">{{ __('Pilih jenis, cakupan, dan tim. Bin berpenanda hitung (short pick/selisih) didahulukan.') }}</p>
        </div>
        <a wire:navigate class="btn btn-outline-secondary" href="{{ route('counts.index') }}">{{ __('Kembali') }}</a>
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
                            <label class="form-check-label" for="opn-g-{{ $g->id }}">{{ $g->code }} · {{ $g->name }}</label>
                        </div>
                    @endforeach
                </div>
                @error('form.warehouse_ids') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>
            @if ($abc !== null)
                <div class="col-12" data-pratinjau-abc>
                    <div class="border rounded p-3">
                        <div class="fw-semibold mb-1">{{ __('Item yang akan dihitung') }}</div>
                        <p class="small text-muted mb-2">{{ __('Kelas dari seringnya barang bergerak 90 hari terakhir. Jadwal hitung: A tiap 30 hari, B tiap 90 hari, C tiap 180 hari. Maksimal :n item per sesi, kelas A dulu.', ['n' => $abc['maks']]) }}</p>
                        @if (! $abc['satuGudang'])
                            <p class="mb-0 text-danger small">{{ __('Pilih tepat satu gudang.') }}</p>
                        @elseif ($abc['item']->isEmpty())
                            <p class="mb-0 small">{{ __('Tidak ada item yang jatuh tempo hitung di gudang ini.') }}</p>
                        @else
                            <p class="small mb-2">
                                {{ __('Jatuh tempo') }}:
                                <span class="badge text-bg-primary">A {{ $abc['perKelas']['A'] }}</span>
                                <span class="badge text-bg-info">B {{ $abc['perKelas']['B'] }}</span>
                                <span class="badge text-bg-secondary">C {{ $abc['perKelas']['C'] }}</span>
                            </p>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead><tr><th>{{ __('Kelas') }}</th><th>{{ __('Item') }}</th><th>{{ __('Terakhir dihitung') }}</th></tr></thead>
                                    <tbody>
                                        @foreach ($abc['item'] as $r)
                                            <tr>
                                                <td>{{ $r['kelas'] }}</td>
                                                <td>{{ $r['kode'] }} · {{ $r['nama'] }}</td>
                                                <td>{{ $r['terakhir'] ? \Illuminate\Support\Carbon::parse($r['terakhir'])->format('d/m/Y') : __('belum pernah') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
            @if ($abc === null && $zones->isNotEmpty())
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
            @if ($abc === null && $bins->isNotEmpty())
                <div class="col-md-6">
                    <label class="form-label" for="opn-bin">{{ __('Bin tertentu (opsional; wajib bin/item untuk pemeriksaan mendadak)') }}</label>
                    <x-pilih-tag model="form.bin_ids" id="opn-bin" :options="$bins->mapWithKeys(fn ($b) => [$b->id => $b->code.($b->count_flag ? ' ⚑ '.__('perlu dihitung') : '')])->all()" />
                    @error('form.bin_ids') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
            @endif
            @if ($abc === null)
                <div class="col-md-6">
                    <label class="form-label" for="opn-item">{{ __('Item tertentu (opsional)') }}</label>
                    <x-pilih-tag model="form.item_ids" id="opn-item" :options="$items->mapWithKeys(fn ($i) => [$i->id => $i->code.' · '.$i->name])->all()" />
                </div>
            @endif
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

    <button class="btn btn-primary" type="button" wire:click="simpan" @disabled($abc !== null && $abc['item']->isEmpty())>{{ __('Rencanakan sesi') }}</button>
</div>
