{{-- Kaki halaman. Tautan kebijakan privasi & ketentuan layanan menyusul O-11. --}}
<footer class="lp-footer">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-5">
                <a class="lp-brand mb-3" href="#hero" style="color: var(--lp-text)">
                    <img src="{{ asset('img/logo.svg') }}" alt="" width="30" height="30">
                    <span>{{ $appName }}</span>
                </a>
                <p class="mb-0" style="max-width: 26rem">{{ __('Warehouse management system untuk perusahaan penyedia material dan alat proyek. Stok yang bisa dipercaya, dari gudang sampai site.') }}</p>
            </div>
            <nav class="col-6 col-lg-3" aria-labelledby="footProduk">
                <h2 id="footProduk">{{ __('Produk') }}</h2>
                <ul>
                    @foreach ($navLinks as $href => $label)
                        <li><a href="{{ $href }}">{{ $label }}</a></li>
                    @endforeach
                </ul>
            </nav>
            <div class="col-6 col-lg-4">
                <h2>{{ __('Kontak') }}</h2>
                <ul>
                    <li><a href="{{ $demoHref }}">{{ __('Minta demo') }}</a></li>
                    <li><a class="text-break" href="mailto:{{ $salesEmail }}">{{ $salesEmail }}</a></li>
                    <li><a href="#masuk">{{ __('Masuk ke company Anda') }}</a></li>
                </ul>
            </div>
        </div>
        <p class="mt-4 mb-0 small">&copy; {{ now()->year }} {{ $appName }}. {{ __('Semua hak dilindungi.') }}</p>
    </div>
</footer>
