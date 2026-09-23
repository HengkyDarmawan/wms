<div>
    <div class="mb-3">
        <h1 class="h3 mb-1">{{ __('Akses dukungan') }}</h1>
        <p class="text-muted mb-0">
            {{ __('Super Admin platform tidak bisa membuka data operasional company tanpa izin dari Anda. Izin selalu berperiode, punya alasan, dan tercatat di jejak audit.') }}
        </p>
    </div>

    <div class="row g-3">
        {{-- ===== Beri izin ===== --}}
        <div class="col-12 col-lg-5">
            <div class="card h-100">
                <div class="card-body">
                    <h2 class="h6 text-uppercase text-muted mb-3">{{ __('Beri izin baru') }}</h2>

                    <form wire:submit="beri">
                        <div class="mb-3">
                            <label class="form-label" for="platformUserId">
                                {{ __('Super Admin') }} <span class="wajib">*</span>
                            </label>
                            <select class="form-select @error('platformUserId') is-invalid @enderror"
                                    id="platformUserId" wire:model="platformUserId" required>
                                <option value="">{{ __('— Pilih —') }}</option>
                                @foreach ($superAdmins as $sa)
                                    <option value="{{ $sa->id }}">{{ $sa->name }} ({{ $sa->email }})</option>
                                @endforeach
                            </select>
                            @error('platformUserId')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-12 col-sm-6">
                                <label class="form-label" for="startsAt">
                                    {{ __('Mulai') }} <span class="wajib">*</span>
                                </label>
                                <input class="form-control @error('startsAt') is-invalid @enderror" id="startsAt"
                                       type="datetime-local" wire:model="startsAt" required>
                                @error('startsAt')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label" for="endsAt">
                                    {{ __('Selesai') }} <span class="wajib">*</span>
                                </label>
                                <input class="form-control @error('endsAt') is-invalid @enderror" id="endsAt"
                                       type="datetime-local" wire:model="endsAt" required>
                                @error('endsAt')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="reason">{{ __('Alasan') }} <span class="wajib">*</span></label>
                            <textarea class="form-control @error('reason') is-invalid @enderror" id="reason" rows="2"
                                      wire:model="reason" maxlength="255" required
                                      placeholder="{{ __('Mis. menelusuri selisih stok di gudang Cakung') }}"></textarea>
                            @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="alert alert-info py-2 small mb-3">
                            <i class="bi bi-info-circle"></i>
                            {{ __('Paling lama :n hari. Waktu memakai zona :zona.', [
                                'n' => $maksHari,
                                'zona' => tenant()?->timezone ?? 'Asia/Jakarta',
                            ]) }}
                        </div>

                        <button class="btn btn-primary w-100" type="submit">{{ __('Beri akses dukungan') }}</button>
                    </form>
                </div>
            </div>
        </div>

        {{-- ===== Sedang berlaku & riwayat ===== --}}
        <div class="col-12 col-lg-7">
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="h6 text-uppercase text-muted mb-3">{{ __('Sedang berlaku') }}</h2>

                    @forelse ($berlaku as $akses)
                        <div class="border rounded p-2 mb-2" wire:key="aktif-{{ $akses->id }}">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div>
                                    <div class="fw-semibold">{{ $akses->platformUser?->name ?? '—' }}</div>
                                    <div class="small text-muted">
                                        {{ $akses->starts_at->timezone(tenant()?->timezone ?? 'Asia/Jakarta')->format('d M Y H:i') }}
                                        &ndash;
                                        {{ $akses->ends_at->timezone(tenant()?->timezone ?? 'Asia/Jakarta')->format('d M Y H:i') }}
                                    </div>
                                    <div class="small">{{ $akses->reason }}</div>
                                </div>

                                @can('support_access.revoke')
                                    <button class="btn btn-sm btn-outline-danger" type="button"
                                            wire:click="cabut({{ $akses->id }})">{{ __('Cabut') }}</button>
                                @endcan
                            </div>
                        </div>
                    @empty
                        <p class="text-muted mb-0">
                            {{ __('Tidak ada akses dukungan yang sedang berlaku. Data company tertutup untuk Super Admin.') }}
                        </p>
                    @endforelse
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h2 class="h6 text-uppercase text-muted mb-3">{{ __('Riwayat pemberian') }}</h2>

                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('Super Admin') }}</th>
                                    <th>{{ __('Periode') }}</th>
                                    <th>{{ __('Alasan') }}</th>
                                    <th>{{ __('Status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($riwayat as $akses)
                                    <tr wire:key="riwayat-{{ $akses->id }}">
                                        <td class="small">{{ $akses->platformUser?->name ?? '—' }}</td>
                                        <td class="small text-muted">
                                            {{ $akses->starts_at->timezone(tenant()?->timezone ?? 'Asia/Jakarta')->format('d M Y H:i') }}
                                            &ndash;
                                            {{ $akses->ends_at->timezone(tenant()?->timezone ?? 'Asia/Jakarta')->format('d M Y H:i') }}
                                        </td>
                                        <td class="small">{{ $akses->reason }}</td>
                                        <td>
                                            @if ($akses->revoked_at)
                                                <span class="badge text-bg-secondary">{{ __('Dicabut') }}</span>
                                            @elseif ($akses->isActive())
                                                <span class="badge text-bg-success">{{ __('Berlaku') }}</span>
                                            @else
                                                <span class="badge text-bg-light">{{ __('Selesai') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-3">
                                            {{ __('Belum pernah memberi akses dukungan.') }}
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
</div>
