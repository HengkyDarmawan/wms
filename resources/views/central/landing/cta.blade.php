{{-- Ajakan penutup: company dibuat tim platform, jadi ajakannya "Minta demo" (A-220). --}}
<section class="lp-section pt-0" id="demo" aria-labelledby="ctaTitle">
    <div class="container">
        <div class="lp-cta">
            <div class="row g-4 align-items-center">
                <div class="col-lg-8">
                    <h2 id="ctaTitle">{{ __('Siap merapikan stok proyek Anda?') }}</h2>
                    <p class="mb-0 fs-5">{{ __('Ceritakan gudang dan proyek Anda. Kami siapkan demo dengan alur yang mirip pekerjaan harian tim Anda.') }}</p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <a class="lp-btn lp-btn-light" href="{{ $demoHref }}"><i class="bi bi-envelope" aria-hidden="true"></i> {{ __('Minta demo') }}</a>
                    <p class="small mt-3 mb-0">{{ __('atau email') }} <a class="text-white fw-semibold text-break" href="mailto:{{ $salesEmail }}">{{ $salesEmail }}</a></p>
                </div>
            </div>
        </div>
    </div>
</section>
