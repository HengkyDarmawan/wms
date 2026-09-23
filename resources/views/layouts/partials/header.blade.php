<header class="nx-header">
    <button class="nx-icon-btn" id="nxSidebarToggle" type="button" aria-label="{{ __('Buka/tutup navigasi') }}">
        <i class="bi bi-list"></i>
    </button>

    <div class="ms-auto d-flex align-items-center gap-1">
        <button class="nx-icon-btn" id="nxThemeToggle" type="button" aria-label="{{ __('Ganti tema gelap/terang') }}">
            <i class="bi bi-moon-stars"></i>
        </button>

        <div class="dropdown">
            <a class="nx-header-user" href="#" role="button" data-bs-toggle="dropdown"
               aria-expanded="false" aria-label="{{ __('Menu pengguna') }}">
                <span class="nx-user-meta-wrap d-none d-lg-block">
                    <span class="nx-user-name d-block">{{ auth()->user()?->name }}</span>
                    <span class="nx-user-role">{{ auth()->user()?->status()->label() }}</span>
                </span>
                <i class="bi bi-person-circle fs-4 ms-2"></i>
            </a>
            <div class="dropdown-menu dropdown-menu-end" style="min-width:220px">
                <div class="px-3 py-2 small text-muted">{{ auth()->user()?->email }}</div>
                <div class="dropdown-divider"></div>
                @if (! auth()->user()?->isClient())
                    <a class="dropdown-item" href="{{ route('profile.edit') }}">
                        <i class="bi bi-person"></i> {{ __('Profil') }}
                    </a>
                @endif
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="dropdown-item text-danger" type="submit">
                        <i class="bi bi-box-arrow-right"></i> {{ __('Keluar') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
