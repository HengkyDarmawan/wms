{{-- A-260: spanduk "Masuk sebagai" — siapa yang sedang dipakai, ganti peran sekali klik, kembali ke Admin. --}}
@if (\App\Domain\Access\Support\Impersonation::active() && auth()->check())
    @php($DemoFlows = \App\Domain\Access\Support\DemoFlows::class)
    @php($dipakai = auth()->user())
    @php($admin = \App\Domain\Access\Support\Impersonation::impersonator())
    @php($namaAdmin = \App\Domain\Access\Support\Impersonation::impersonatorName())
    @php($kodeUtama = $dipakai->roleCodes()[0] ?? null)
    @php($ganti = \App\Domain\Access\Support\Impersonation::allowed()
        ? $DemoFlows::perRole($DemoFlows::candidates($admin)->whereNull('reason')->values())
        : [])
    @php($namaRole = \App\Domain\Access\Models\Role::query()->pluck('name', 'code'))

    <div class="nx-imp-banner" role="status" aria-live="polite">
        <div class="nx-imp-banner-inner">
            <span class="nx-imp-avatar nx-imp-tone-{{ $DemoFlows::tone($kodeUtama) }}" aria-hidden="true">{{ $DemoFlows::initials($dipakai->name) }}</span>
            <div class="nx-imp-banner-text">
                <span class="nx-imp-banner-kicker"><i class="bi bi-easel2"></i> {{ __('Mode presentasi — Anda sedang masuk sebagai') }}</span>
                <span class="nx-imp-banner-who">
                    <strong>{{ $dipakai->name }}</strong>
                    <span class="nx-imp-banner-roles">{{ implode(' · ', $DemoFlows::describe($dipakai)) }}</span>
                </span>
            </div>

            <div class="nx-imp-banner-actions">
                @if ($ganti !== [])
                    <div class="dropdown">
                        <button class="btn btn-sm nx-imp-banner-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-arrow-left-right"></i><span class="d-none d-sm-inline"> {{ __('Ganti peran') }}</span>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end nx-imp-switch-menu">
                            <div class="dropdown-header">{{ __('Pindah langsung ke peran lain') }}</div>
                            @foreach ($ganti as $kode => $calon)
                                @if ($calon->is($dipakai))
                                    <span class="dropdown-item d-flex align-items-center gap-2 active" aria-current="true">
                                        <span class="nx-imp-avatar nx-imp-avatar-xs nx-imp-tone-{{ $DemoFlows::tone($kode) }}" aria-hidden="true">{{ $DemoFlows::initials($calon->name) }}</span>
                                        <span class="flex-grow-1"><span class="d-block small fw-semibold">{{ $namaRole[$kode] ?? $kode }}</span><span class="d-block small opacity-75">{{ $calon->name }}</span></span>
                                        <i class="bi bi-check2"></i>
                                    </span>
                                @else
                                    <form method="POST" action="{{ route('impersonate.store', $calon) }}">
                                        @csrf
                                        <button class="dropdown-item d-flex align-items-center gap-2" type="submit">
                                            <span class="nx-imp-avatar nx-imp-avatar-xs nx-imp-tone-{{ $DemoFlows::tone($kode) }}" aria-hidden="true">{{ $DemoFlows::initials($calon->name) }}</span>
                                            <span class="flex-grow-1"><span class="d-block small fw-semibold">{{ $namaRole[$kode] ?? $kode }}</span><span class="d-block small text-muted">{{ $calon->name }}</span></span>
                                        </button>
                                    </form>
                                @endif
                            @endforeach
                            @unless ($dipakai->isClient())
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item small" href="{{ route('impersonate.index') }}">
                                    <i class="bi bi-grid-3x3-gap"></i> {{ __('Panduan alur & semua pengguna') }}
                                </a>
                            @endunless
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('impersonate.leave') }}">
                    @csrf
                    <button class="btn btn-sm btn-light nx-imp-banner-back" type="submit">
                        <i class="bi bi-box-arrow-left"></i>
                        {{ __('Kembali ke :nama', ['nama' => $namaAdmin]) }}
                    </button>
                </form>
            </div>
        </div>
    </div>
@endif
