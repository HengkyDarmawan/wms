<div>
    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <strong>{{ __('Rencana material') }}</strong>
                @if ($berlaku)
                    <span class="small text-muted">
                        {{ __('Versi :v · diunggah :nama :tanggal', ['v' => $berlaku->version, 'nama' => $berlaku->creator?->name ?? '-', 'tanggal' => $berlaku->created_at?->lokal()->format('d/m/Y H:i')]) }}
                        @if ($berlaku->source_file) · {{ $berlaku->source_file }} @endif
                    </span>
                @endif
            </div>
            <button class="btn btn-sm btn-outline-secondary" type="button" wire:click="unduhTemplat">
                <i class="bi bi-download" aria-hidden="true"></i> {{ __('Unduh templat') }}
            </button>
        </div>

        @if ($bisaUnggah)
            <div class="card-body border-bottom">
                <div class="row g-2 align-items-end">
                    <div class="col-md-7">
                        <label class="form-label" for="rencana-berkas">{{ __('Unggah versi baru') }} <span class="wajib">*</span></label>
                        <input class="form-control @error('berkas') is-invalid @enderror" id="rencana-berkas" type="file" accept=".xlsx,.xls,.csv" wire:model="berkas">
                        @error('berkas') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-5 d-flex gap-2">
                        <button class="btn btn-primary" type="button" wire:click="unggah" wire:loading.attr="disabled" wire:target="berkas,unggah">{{ __('Simpan sebagai versi baru') }}</button>
                    </div>
                </div>
                <div class="form-text">{{ __('Isi kode item dan jumlah dalam satuan dasar. Semua baris harus benar; bila ada yang salah, tidak ada yang disimpan. Versi lama tetap tersimpan di riwayat.') }}</div>
                @if ($galatBaris !== '')
                    <pre class="small text-danger mt-2 mb-0" style="white-space: pre-wrap">{{ $galatBaris }}</pre>
                @endif
            </div>
        @endif

        @if ($baris->isEmpty())
            <div class="card-body text-muted small">
                {{ __('Belum ada rencana material untuk proyek ini. Unduh templat, isi kode item dan jumlah, lalu unggah.') }}
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Item') }}</th>
                            <th class="text-end">{{ __('Rencana') }}</th>
                            <th class="text-end">{{ __('Diminta') }}</th>
                            <th class="text-end">{{ __('Terkirim') }}</th>
                            <th class="text-end">{{ __('Terpakai') }}</th>
                            <th class="text-end">{{ __('Sisa rencana') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php($angka = fn ($n) => $n === null ? '-' : \App\Domain\Master\Support\QtyFormat::number($n))
                        @foreach ($baris as $r)
                            <tr wire:key="rencana-{{ $r['item_id'] }}">
                                <td>
                                    {{ $r['item_code'] }} · {{ $r['item_name'] }}
                                    <span class="text-muted small">({{ $r['uom'] }})</span>
                                    @if ($r['melebihi']) <span class="badge text-bg-danger">{{ __('Melebihi') }}</span> @endif
                                    @if ($r['rencana'] === null) <span class="badge text-bg-secondary">{{ __('Tanpa rencana') }}</span> @endif
                                </td>
                                <td class="text-end">{{ $angka($r['rencana']) }}</td>
                                <td class="text-end">{{ $angka($r['diminta']) }}</td>
                                <td class="text-end">{{ $angka($r['terkirim']) }}</td>
                                <td class="text-end">{{ $angka($r['terpakai']) }}</td>
                                <td class="text-end {{ $r['melebihi'] ? 'text-danger fw-semibold' : '' }}">{{ $angka($r['sisa']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if ($versi->isNotEmpty())
        <div class="card">
            <div class="card-header"><strong>{{ __('Riwayat versi') }}</strong></div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>{{ __('Versi') }}</th><th>{{ __('Item') }}</th><th>{{ __('Diunggah') }}</th><th>{{ __('Berkas') }}</th></tr></thead>
                    <tbody>
                        @foreach ($versi as $v)
                            <tr>
                                <td>{{ $v->version }} @if ($v->berlaku) <span class="badge text-bg-success">{{ __('Berlaku') }}</span> @endif</td>
                                <td>{{ $v->jumlah }}</td>
                                <td class="small">{{ $nama[$v->oleh] ?? '-' }} · {{ \Illuminate\Support\Carbon::parse($v->waktu)->lokal()->format('d/m/Y H:i') }}</td>
                                <td class="small">{{ $v->berkas ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
