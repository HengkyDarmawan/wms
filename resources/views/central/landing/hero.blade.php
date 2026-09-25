{{-- Hero penuh layar (D-06). Salin pemasaran diambil dari layout auth (layouts/auth.blade.php). --}}
<section class="lp-hero" id="hero" aria-labelledby="heroTitle">
    <div class="container">
        <div class="row align-items-center g-4 g-lg-5">
            <div class="col-lg-6 lp-rise">
                <span class="lp-hero-badge"><i class="bi bi-cone-striped" aria-hidden="true"></i> {{ __('WMS khusus material proyek') }}</span>
                <h1 id="heroTitle">{{ __('Stok yang bisa dipercaya,') }} <em>{{ __('dari gudang sampai site.') }}</em></h1>
                <p class="lp-lead">
                    {{ __('Untuk perusahaan penyedia material, mesin, dan peralatan proyek pembangunan. Permintaan, pengiriman, pemakaian, retur, konversi potongan, dan aset dipinjamkan — semuanya tercatat per proyek, tanpa menghitung ulang di Excel.') }}
                </p>
                <div class="d-flex flex-wrap gap-3 mt-4">
                    <a class="lp-btn lp-btn-light" href="{{ $demoHref }}"><i class="bi bi-calendar2-check" aria-hidden="true"></i> {{ __('Minta demo') }}</a>
                    <a class="lp-btn lp-btn-ghost" href="#cara-kerja">{{ __('Lihat cara kerja') }} <i class="bi bi-arrow-down" aria-hidden="true"></i></a>
                </div>
                <ul class="lp-hero-points">
                    <li><i class="bi bi-check2-circle" aria-hidden="true"></i>{{ __('Langganan flat per company') }}</li>
                    <li><i class="bi bi-check2-circle" aria-hidden="true"></i>{{ __('User & gudang tanpa batas') }}</li>
                    <li><i class="bi bi-check2-circle" aria-hidden="true"></i>{{ __('Database terpisah per company') }}</li>
                </ul>
            </div>
            <div class="col-lg-6 lp-rise lp-rise-2">
                <img class="lp-hero-art" src="{{ asset('img/landing/hero.svg') }}" width="800" height="440"
                     alt="{{ __('Ilustrasi alur barang: gudang dengan rak, truk pengiriman, lalu lokasi proyek dengan crane.') }}">
            </div>
        </div>
    </div>
</section>
