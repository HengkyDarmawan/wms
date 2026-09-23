<div>
    <div class="mb-3">
        <h1 class="h3 mb-1">{{ __('Perangkat') }}</h1>
        <p class="text-muted mb-0">
            {{ $lihatSemua
                ? __('Perangkat yang terdaftar untuk PWA di seluruh company. Mencabut perangkat tidak menghapus datanya.')
                : __('Perangkat yang Anda pakai untuk PWA. Mencabut perangkat membuatnya harus didaftarkan ulang saat login.') }}
        </p>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-2">
                <div class="col-12 col-md-8">
                    <label class="form-label" for="cari-perangkat">{{ __('Cari perangkat') }}</label>
                    <input class="form-control" id="cari-perangkat" type="search"
                           wire:model.live.debounce.400ms="search"
                           placeholder="{{ $lihatSemua ? __('Nama perangkat, id, atau nama user…') : __('Nama perangkat atau id…') }}">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label" for="status-perangkat">{{ __('Status') }}</label>
                    <select class="form-select" id="status-perangkat" wire:model.live="statusFilter">
                        <option value="">{{ __('Semua') }}</option>
                        <option value="aktif">{{ __('Aktif') }}</option>
                        <option value="dicabut">{{ __('Dicabut') }}</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Perangkat') }}</th>
                        @if ($lihatSemua)
                            <th>{{ __('Pemilik') }}</th>
                        @endif
                        <th>{{ __('Platform') }}</th>
                        <th>{{ __('Terakhir terlihat') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-end">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($devices as $device)
                        <tr wire:key="device-{{ $device->id }}">
                            <td>
                                <div class="fw-semibold">{{ $device->name ?? __('Tanpa nama') }}</div>
                                <div class="small text-muted"><code>{{ $device->device_uid }}</code></div>
                            </td>
                            @if ($lihatSemua)
                                <td class="small">{{ $device->user?->name ?? '—' }}</td>
                            @endif
                            <td class="small text-muted">{{ $device->platform ?? '—' }}</td>
                            <td class="small text-muted">
                                {{ $device->last_seen_at?->timezone(tenant()?->timezone ?? 'Asia/Jakarta')->format('d M Y H:i') ?? __('Belum dipakai') }}
                            </td>
                            <td>
                                <span class="badge text-bg-{{ $device->is_active ? 'success' : 'secondary' }}">
                                    {{ $device->is_active ? __('Aktif') : __('Dicabut') }}
                                </span>
                            </td>
                            <td class="text-end">
                                @can('revoke', $device)
                                    @if ($device->is_active)
                                        <button class="btn btn-sm btn-outline-danger" type="button"
                                                wire:click="cabut({{ $device->id }})">{{ __('Cabut') }}</button>
                                    @else
                                        <button class="btn btn-sm btn-outline-success" type="button"
                                                wire:click="aktifkan({{ $device->id }})">{{ __('Aktifkan') }}</button>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $lihatSemua ? 6 : 5 }}" class="text-center text-muted py-4">
                                {{ __('Belum ada perangkat terdaftar.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($devices->hasPages())
            <div class="card-body border-top">{{ $devices->links() }}</div>
        @endif
    </div>
</div>
