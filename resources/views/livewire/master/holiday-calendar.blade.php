<div class="card mb-3">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <strong>{{ __('Kalender libur') }}</strong>
            <div class="small text-muted">
                {{ __('Hari kerja :n per minggu', ['n' => $hariKerja]) }} ({{ [5 => __('Senin–Jumat'), 6 => __('Senin–Sabtu'), 7 => __('setiap hari')][$hariKerja] }}) {{ __('dikurangi libur aktif di bawah. Dipakai menghitung SLA tinjau permintaan.') }}
            </div>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <label class="visually-hidden" for="libur-tahun">{{ __('Tahun') }}</label>
            <input class="form-control form-control-sm" id="libur-tahun" type="number" min="2000" max="2100" style="width: 6rem" wire:model.live.debounce.500ms="tahun">
            @if ($bolehUbah && $tersedia)
                <button class="btn btn-sm btn-outline-primary" type="button" wire:click="isiNasional">{{ __('Isi libur nasional :tahun', ['tahun' => $tahun]) }}</button>
            @endif
        </div>
    </div>
    <div class="card-body">
        @unless ($tersedia)
            <div class="alert alert-info py-2 small">{{ __('Data libur nasional tahun ini belum tersedia di aplikasi (tersedia: :tahun). Tambahkan manual atau tunggu pembaruan setelah SKB 3 Menteri terbit.', ['tahun' => implode(', ', $tahunData)]) }}</div>
        @endunless
        @if ($ruleError !== '') <div class="alert alert-danger py-2 small">{{ $ruleError }}</div> @endif

        <div class="table-responsive">
            <table class="table table-sm align-middle mb-3">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Tanggal') }}</th>
                        <th scope="col">{{ __('Keterangan') }}</th>
                        <th scope="col">{{ __('Jenis') }}</th>
                        <th scope="col" class="text-end">{{ __('Libur?') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($libur as $h)
                        <tr wire:key="libur-{{ $h->id }}" @class(['text-muted' => ! $h->is_active])>
                            <td class="text-nowrap">{{ $h->date->translatedFormat('D, d/m/Y') }}</td>
                            <td>{{ $h->name }} @unless ($h->is_active) <span class="small">({{ __('tetap bekerja') }})</span> @endunless</td>
                            <td><span class="badge {{ $h->kind->badge() }}">{{ $h->kind->label() }}</span></td>
                            <td class="text-end">
                                <div class="form-check form-switch d-inline-block mb-0">
                                    <input class="form-check-input" id="libur-aktif-{{ $h->id }}" type="checkbox" @checked($h->is_active) @disabled(! $bolehUbah)
                                           wire:click="aturAktif({{ $h->id }}, {{ $h->is_active ? 'false' : 'true' }})" aria-label="{{ __('Libur') }} {{ $h->date->format('d/m/Y') }}">
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td class="text-muted" colspan="4">{{ __('Belum ada libur untuk tahun ini.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($bolehUbah)
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small" for="libur-tanggal">{{ __('Tanggal') }} <span class="wajib">*</span></label>
                    <input class="form-control form-control-sm @error('form.date') is-invalid @enderror" id="libur-tanggal" type="date" wire:model="form.date">
                    @error('form.date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-5">
                    <label class="form-label small" for="libur-nama">{{ __('Nama libur company') }} <span class="wajib">*</span></label>
                    <input class="form-control form-control-sm @error('form.name') is-invalid @enderror" id="libur-nama" type="text" maxlength="120" wire:model="form.name" placeholder="{{ __('mis. Libur akhir tahun kantor') }}">
                    @error('form.name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <button class="btn btn-sm btn-primary w-100" type="button" wire:click="tambah">{{ __('Tambah libur') }}</button>
                </div>
            </div>
        @endif
    </div>
</div>
