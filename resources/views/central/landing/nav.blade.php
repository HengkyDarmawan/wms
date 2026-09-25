{{-- Navigasi lengket: transparan di atas hero, tembus pandang setelah digulir (D-06). --}}
<header class="lp-nav" x-data="landingNav" :class="{ 'is-solid': scrolled || open }" @keydown.escape.window="close()">
    <div class="container">
        <div class="lp-nav-inner">
            <a class="lp-brand" href="#hero">
                <img src="{{ asset('img/logo.svg') }}" alt="" width="34" height="34">
                <span>{{ $appName }}</span>
            </a>

            <nav aria-label="{{ __('Navigasi utama') }}" class="d-none d-lg-block ms-auto">
                <ul class="lp-nav-links">
                    @foreach ($navLinks as $href => $label)
                        <li><a href="{{ $href }}">{{ $label }}</a></li>
                    @endforeach
                </ul>
            </nav>

            <div class="lp-nav-actions">
                <button type="button" class="lp-icon-btn" id="lpThemeToggle" @click="toggleTheme()"
                        :aria-label="theme === 'dark' ? '{{ __('Pakai tema terang') }}' : '{{ __('Pakai tema gelap') }}'"
                        aria-label="{{ __('Ganti tema terang/gelap') }}">
                    <i class="bi bi-moon-stars" x-show="theme !== 'dark'" aria-hidden="true"></i>
                    <i class="bi bi-sun" x-show="theme === 'dark'" x-cloak aria-hidden="true"></i>
                </button>
                <a class="lp-btn lp-btn-ghost lp-btn-sm lp-hide-sm" href="#masuk">{{ __('Masuk') }}</a>
                <a class="lp-btn lp-btn-primary lp-btn-sm lp-hide-sm" href="{{ $demoHref }}">{{ __('Minta demo') }}</a>
                <button type="button" class="lp-icon-btn lp-nav-toggle d-lg-none" @click="open = !open"
                        :aria-expanded="open.toString()" aria-expanded="false" aria-controls="lpMobileMenu"
                        aria-label="{{ __('Buka menu') }}">
                    <i class="bi" :class="open ? 'bi-x-lg' : 'bi-list'" aria-hidden="true"></i>
                </button>
            </div>
        </div>

        <nav class="lp-mobile-menu" id="lpMobileMenu" x-show="open" x-cloak aria-label="{{ __('Navigasi utama (HP)') }}">
            <ul>
                @foreach ($navLinks as $href => $label)
                    <li><a href="{{ $href }}" @click="close()">{{ $label }}</a></li>
                @endforeach
            </ul>
            <div class="d-grid gap-2">
                <a class="lp-btn lp-btn-ghost" href="#masuk" @click="close()">{{ __('Masuk ke company Anda') }}</a>
                <a class="lp-btn lp-btn-primary" href="{{ $demoHref }}">{{ __('Minta demo') }}</a>
            </div>
        </nav>
    </div>
</header>
