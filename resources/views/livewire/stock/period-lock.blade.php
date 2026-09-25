<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Kunci periode stok') }}</h1>
            <p class="text-muted mb-0">
                {{ __('Setelah dikunci, mutasi dengan tanggal pada atau sebelum tanggal kunci ditolak — termasuk dokumen pembalik. Koreksi diposting di periode berjalan.') }}
            </p>
        </div>
    </div>

    @if ($ruleError !== '')
        <div class="alert alert-danger" role="alert">
            {{ $ruleError }}
            @if ($ruleCode !== '')
                <span class="badge text-bg-dark ms-1">{{ $ruleCode }}</span>
            @endif
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header"><strong>{{ __('Status sekarang') }}</strong></div>
                <div class="card-body">
                    @if ($terkunciSampai)
                        <p class="mb-1 text-muted small">{{ __('Terkunci sampai') }}</p>
                        <p class="h4 mb-0">{{ \Illuminate\Support\Carbon::parse($terkunciSampai)->format('d/m/Y') }}</p>
                    @else
                        <p class="mb-0 text-muted">{{ __('Belum ada periode yang dikunci.') }}</p>
                    @endif
                </div>
                <div class="card-body border-top">
                    <div class="mb-3">
                        <label class="form-label" for="kunci-tanggal">
                            {{ __('Kunci sampai tanggal') }} <span class="wajib">*</span>
                        </label>
                        <input class="form-control @error('tanggal') is-invalid @enderror" id="kunci-tanggal"
                               type="date" max="{{ now()->toDateString() }}" wire:model="tanggal">
                        @error('tanggal') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text">
                            {{ __('Tanggal kunci hanya bisa dimajukan dan tidak boleh melewati hari ini.') }}
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="kunci-catatan">{{ __('Catatan') }}</label>
                        <textarea class="form-control" id="kunci-catatan" rows="2" wire:model="catatan"
                                  placeholder="{{ __('Opsional') }}"></textarea>
                    </div>
                    <button class="btn btn-primary" type="button" wire:click="kunci">{{ __('Kunci periode') }}</button>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header"><strong>{{ __('Riwayat pemajuan') }}</strong></div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('Waktu') }}</th>
                                <th scope="col">{{ __('Dari') }}</th>
                                <th scope="col">{{ __('Ke') }}</th>
                                <th scope="col">{{ __('Oleh') }}</th>
                                <th scope="col">{{ __('Catatan') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($riwayat as $log)
                                <tr>
                                    <td class="text-nowrap">{{ $log->created_at?->lokal()->format('d/m/Y H:i') }}</td>
                                    <td>{{ $log->properties['dari'] ?? __('belum ada') }}</td>
                                    <td>{{ $log->properties['ke'] ?? '—' }}</td>
                                    <td>{{ $log->causer?->name ?? __('Sistem') }}</td>
                                    <td class="small text-muted">{{ $log->properties['notes'] ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="text-center text-muted py-4" colspan="5">
                                        {{ __('Belum pernah dikunci.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
