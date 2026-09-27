@php($DemoFlows = \App\Domain\Access\Support\DemoFlows::class)
@php($sedangDipakai = auth()->id())
<div>
    {{-- ===== Pembuka ===== --}}
    <section class="nx-imp-hero mb-4">
        <div class="nx-imp-hero-icon" aria-hidden="true"><i class="bi bi-people"></i></div>
        <div class="flex-grow-1">
            <h1 class="h3 mb-1">{{ __('Masuk sebagai') }}</h1>
            <p class="mb-0 text-muted">
                {{ __('Tunjukkan alur lintas peran tanpa keluar-masuk akun. Pilih langkah alur atau pengguna, lalu kembali ke akun Admin kapan saja dari spanduk di atas halaman.') }}
            </p>
        </div>
        <div class="nx-imp-hero-meta">
            <span class="badge rounded-pill text-bg-light"><i class="bi bi-person-lines-fill"></i> {{ __(':n pengguna', ['n' => $total]) }}</span>
            <span class="badge rounded-pill nx-imp-badge-audit" title="{{ __('Setiap perubahan tercatat di audit log beserta nama Admin asli.') }}">
                <i class="bi bi-shield-check"></i> {{ __('Tercatat di audit log') }}
            </span>
        </div>
    </section>

    {{-- ===== Panduan alur demo ===== --}}
    <div class="d-flex align-items-end justify-content-between mb-2">
        <div>
            <h2 class="h5 mb-0">{{ __('Panduan alur demo') }}</h2>
            <p class="small text-muted mb-0">{{ __('Ikuti langkah berurutan; setiap tombol langsung masuk sebagai pengguna yang memegang peran tersebut.') }}</p>
        </div>
    </div>

    <div class="row g-3 mb-4">
        @foreach ($flows as $flow)
            <div class="col-12 col-xl-6" wire:key="flow-{{ $flow['key'] }}">
                <article class="card nx-imp-flow h-100">
                    <div class="card-body">
                        <header class="d-flex align-items-start gap-3 mb-3">
                            <span class="nx-imp-flow-icon" aria-hidden="true"><i class="bi {{ $flow['icon'] }}"></i></span>
                            <div>
                                <h3 class="h6 mb-1">{{ __($flow['title']) }}</h3>
                                <p class="small text-muted mb-0">{{ __($flow['summary']) }}</p>
                            </div>
                        </header>

                        <ol class="nx-imp-steps">
                            @foreach ($flow['steps'] as $i => $step)
                                <li class="nx-imp-step" wire:key="flow-{{ $flow['key'] }}-{{ $i }}">
                                    <span class="nx-imp-step-no nx-imp-tone-{{ $DemoFlows::tone($step['role']) }}">{{ $i + 1 }}</span>
                                    <div class="nx-imp-step-body">
                                        <span class="nx-imp-step-role">
                                            <i class="bi {{ $DemoFlows::icon($step['role']) }}"></i> {{ $step['roleName'] }}
                                        </span>
                                        <span class="nx-imp-step-action">{{ __($step['action']) }}</span>
                                    </div>
                                    <div class="nx-imp-step-go">
                                        @if ($step['user'] === null)
                                            <span class="badge text-bg-light">{{ __('Belum ada pengguna') }}</span>
                                        @elseif ($step['user']->id === $sedangDipakai)
                                            <span class="badge nx-imp-badge-current"><i class="bi bi-check2"></i> {{ __('Sedang dipakai') }}</span>
                                        @else
                                            <form method="POST" action="{{ route('impersonate.store', $step['user']) }}">
                                                @csrf
                                                <button class="btn btn-sm btn-outline-primary nx-imp-go-btn" type="submit"
                                                        title="{{ __('Masuk sebagai :nama', ['nama' => $step['user']->name]) }}">
                                                    <span class="nx-imp-avatar nx-imp-avatar-xs nx-imp-tone-{{ $DemoFlows::tone($step['role']) }}" aria-hidden="true">{{ $DemoFlows::initials($step['user']->name) }}</span>
                                                    <span>{{ \Illuminate\Support\Str::before($step['user']->name, ' ') ?: $step['user']->name }}</span>
                                                    <i class="bi bi-arrow-right-short"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                </article>
            </div>
        @endforeach
    </div>

    {{-- ===== Semua pengguna ===== --}}
    <div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-2">
        <div>
            <h2 class="h5 mb-0">{{ __('Semua pengguna') }}</h2>
            <p class="small text-muted mb-0">{{ __('Admin Company lain, user nonaktif, terkunci, atau tanpa penugasan role tidak bisa dipilih.') }}</p>
        </div>
        <div class="nx-imp-search">
            <i class="bi bi-search" aria-hidden="true"></i>
            <input type="search" class="form-control" placeholder="{{ __('Cari nama atau email…') }}"
                   aria-label="{{ __('Cari nama atau email') }}" wire:model.live.debounce.300ms="search">
        </div>
    </div>

    <div class="nx-imp-chips mb-3" role="group" aria-label="{{ __('Saring per role') }}">
        <button type="button" class="nx-imp-chip {{ $roleFilter === '' ? 'active' : '' }}" wire:click="$set('roleFilter', '')">
            {{ __('Semua') }}
        </button>
        @foreach ($roles as $role)
            <button type="button" wire:key="chip-{{ $role->code }}"
                    class="nx-imp-chip {{ $roleFilter === $role->code ? 'active' : '' }}"
                    wire:click="$set('roleFilter', '{{ $role->code }}')">
                <span class="nx-imp-dot nx-imp-tone-{{ $DemoFlows::tone($role->code) }}" aria-hidden="true"></span>{{ $role->name }}
            </button>
        @endforeach
    </div>

    <div class="row g-3" wire:loading.class="opacity-50">
        @forelse ($cards as $card)
            @php($u = $card['user'])
            @php($utama = $card['codes'][0] ?? null)
            <div class="col-12 col-sm-6 col-lg-4 col-xxl-3" wire:key="user-{{ $u->id }}">
                <article class="card nx-imp-user h-100 {{ $card['reason'] !== null ? 'is-disabled' : '' }} {{ $u->id === $sedangDipakai ? 'is-current' : '' }}">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <span class="nx-imp-avatar nx-imp-tone-{{ $DemoFlows::tone($utama) }}" aria-hidden="true">{{ $DemoFlows::initials($u->name) }}</span>
                            <div class="min-w-0">
                                <div class="fw-semibold text-truncate">{{ $u->name }}</div>
                                <div class="small text-muted text-truncate">{{ $u->email }}</div>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-1 mb-3">
                            @forelse ($card['roles'] as $r)
                                <span class="nx-imp-role-pill nx-imp-tone-{{ $DemoFlows::tone($r['code']) }}">
                                    <i class="bi {{ $DemoFlows::icon($r['code']) }}"></i> {{ $r['name'] }}
                                    <span class="opacity-75">· {{ $r['scopes'] }}</span>
                                </span>
                            @empty
                                <span class="small text-danger">{{ __('Belum ada penugasan') }}</span>
                            @endforelse
                        </div>

                        <div class="mt-auto">
                            @if ($u->id === $sedangDipakai)
                                <span class="btn btn-sm w-100 nx-imp-badge-current disabled"><i class="bi bi-check2"></i> {{ __('Sedang dipakai') }}</span>
                            @elseif ($card['reason'] !== null)
                                <div class="small text-muted"><i class="bi bi-slash-circle"></i> {{ $card['reason'] }}</div>
                            @else
                                <form method="POST" action="{{ route('impersonate.store', $u) }}">
                                    @csrf
                                    <button class="btn btn-primary btn-sm w-100" type="submit">
                                        <i class="bi bi-box-arrow-in-right"></i> {{ __('Masuk sebagai') }}
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </article>
            </div>
        @empty
            <div class="col-12">
                <div class="card"><div class="card-body text-center text-muted py-5">
                    <i class="bi bi-person-x fs-2 d-block mb-2"></i>
                    {{ __('Tidak ada pengguna yang cocok.') }}
                    <button type="button" class="btn btn-link btn-sm" wire:click="resetFilters">{{ __('Hapus filter') }}</button>
                </div></div>
            </div>
        @endforelse
    </div>
</div>
