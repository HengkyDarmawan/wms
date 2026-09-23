<div>
    @php($status = $user->status())

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ $user->name }}</h1>
            <p class="text-muted mb-0">
                {{ $user->email }} · <span class="badge text-bg-{{ $status->badge() }}">{{ $status->label() }}</span>
            </p>
        </div>
        <div class="d-flex gap-2">
            @can('update', $user)
                <a class="btn btn-primary" href="{{ route('users.edit', $user) }}">
                    <i class="bi bi-pencil"></i> {{ __('Ubah') }}
                </a>
            @endcan
            <a class="btn btn-outline-secondary" href="{{ route('users.index') }}">{{ __('Kembali') }}</a>
        </div>
    </div>

    <ul class="nav nav-tabs mb-3">
        @foreach ([
            'ringkasan' => __('Ringkasan'),
            'role' => __('Penugasan Role'),
            'perangkat' => __('Perangkat'),
            'riwayat' => __('Riwayat'),
        ] as $key => $label)
            <li class="nav-item">
                <button class="nav-link {{ $tab === $key ? 'active' : '' }}" type="button"
                        wire:click="pilihTab('{{ $key }}')">{{ $label }}</button>
            </li>
        @endforeach
    </ul>

    <div class="card">
        <div class="card-body">
            @if ($tab === 'ringkasan')
                <dl class="row mb-0">
                    <dt class="col-sm-4 col-lg-3">{{ __('Unit organisasi') }}</dt>
                    <dd class="col-sm-8 col-lg-9">{{ $user->orgUnit?->name ?? '—' }}</dd>

                    <dt class="col-sm-4 col-lg-3">{{ __('Jabatan') }}</dt>
                    <dd class="col-sm-8 col-lg-9">{{ $user->position?->name ?? '—' }}</dd>

                    <dt class="col-sm-4 col-lg-3">{{ __('Atasan langsung') }}</dt>
                    <dd class="col-sm-8 col-lg-9">{{ $user->manager?->name ?? '—' }}</dd>

                    <dt class="col-sm-4 col-lg-3">{{ __('Nomor WhatsApp') }}</dt>
                    <dd class="col-sm-8 col-lg-9">{{ $user->phone ?? '—' }}</dd>

                    <dt class="col-sm-4 col-lg-3">{{ __('Jenis user') }}</dt>
                    <dd class="col-sm-8 col-lg-9">
                        {{ $user->isClient() ? __('Klien (portal)') : __('Internal company') }}
                    </dd>

                    <dt class="col-sm-4 col-lg-3">{{ __('Verifikasi dua langkah') }}</dt>
                    <dd class="col-sm-8 col-lg-9">
                        {{ $user->hasTwoFactorEnabled() ? __('Aktif') : __('Tidak aktif') }}
                    </dd>

                    <dt class="col-sm-4 col-lg-3">{{ __('Login terakhir') }}</dt>
                    <dd class="col-sm-8 col-lg-9">
                        {{ $user->last_login_at?->timezone(tenant()?->timezone ?? 'Asia/Jakarta')->format('d M Y H:i') ?? '—' }}
                    </dd>

                    <dt class="col-sm-4 col-lg-3">{{ __('Password diubah') }}</dt>
                    <dd class="col-sm-8 col-lg-9 mb-0">
                        {{ $user->password_changed_at?->timezone(tenant()?->timezone ?? 'Asia/Jakarta')->format('d M Y H:i') ?? '—' }}
                    </dd>
                </dl>
            @elseif ($tab === 'role')
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('Role') }}</th>
                                <th>{{ __('Cakupan') }}</th>
                                <th>{{ __('Berlaku') }}</th>
                                <th>{{ __('Ditugaskan oleh') }}</th>
                                <th>{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($user->roleAssignments as $assignment)
                                <tr wire:key="ra-{{ $assignment->id }}">
                                    <td>
                                        <div class="fw-semibold">{{ $assignment->role?->name }}</div>
                                        <div class="small text-muted">{{ $assignment->role?->code }}</div>
                                    </td>
                                    <td>{{ $assignment->describeScope() }}</td>
                                    <td class="small">
                                        {{ $assignment->valid_from?->format('d M Y') ?? __('sejak awal') }}
                                        &ndash;
                                        {{ $assignment->valid_until?->format('d M Y') ?? __('tanpa batas') }}
                                    </td>
                                    <td class="small text-muted">{{ $assignment->assignedBy?->name ?? '—' }}</td>
                                    <td>
                                        @if ($assignment->isValid())
                                            <span class="badge text-bg-success">{{ __('Berlaku') }}</span>
                                        @else
                                            <span class="badge text-bg-secondary">{{ __('Tidak berlaku') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        {{ __('Belum ada penugasan role. User tidak bisa masuk sampai diberi penugasan.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @elseif ($tab === 'perangkat')
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('Perangkat') }}</th>
                                <th>{{ __('Platform') }}</th>
                                <th>{{ __('Terakhir terlihat') }}</th>
                                <th>{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($user->devices as $device)
                                <tr wire:key="dev-{{ $device->id }}">
                                    <td>{{ $device->name ?? $device->device_uid }}</td>
                                    <td class="small text-muted">{{ $device->platform ?? '—' }}</td>
                                    <td class="small text-muted">
                                        {{ $device->last_seen_at?->diffForHumans() ?? __('Belum dipakai') }}
                                    </td>
                                    <td>
                                        <span class="badge text-bg-{{ $device->is_active ? 'success' : 'secondary' }}">
                                            {{ $device->is_active ? __('Aktif') : __('Dicabut') }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        {{ __('Belum ada perangkat terdaftar.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @else
                <ul class="list-unstyled mb-0">
                    @forelse ($riwayat as $catatan)
                        <li class="border-bottom py-2" wire:key="log-{{ $catatan->id }}">
                            <div class="d-flex justify-content-between gap-2">
                                <span>{{ $catatan->description }}</span>
                                <span class="small text-muted text-nowrap">
                                    {{ $catatan->created_at?->timezone(tenant()?->timezone ?? 'Asia/Jakarta')->format('d M Y H:i') }}
                                </span>
                            </div>
                            @if ($catatan->properties?->isNotEmpty())
                                <div class="small text-muted">{{ $catatan->properties->toJson() }}</div>
                            @endif
                        </li>
                    @empty
                        <li class="text-center text-muted py-4">{{ __('Belum ada riwayat.') }}</li>
                    @endforelse
                </ul>
            @endif
        </div>
    </div>
</div>
