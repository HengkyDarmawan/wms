{{-- Sorotan pembeda utama: konversi material berbasis ukuran (Blueprint §6.7, riset §2.7). --}}
<section class="lp-section pt-0" id="konversi" aria-labelledby="conversionTitle">
    <div class="container">
        <div class="lp-spotlight">
            <div class="row g-4 g-lg-5 align-items-center">
                <div class="col-lg-5">
                    <span class="lp-eyebrow">{{ __('Pembeda utama') }}</span>
                    <h2 class="lp-title" id="conversionTitle">{{ __('Setiap potongan punya asal-usul') }}</h2>
                    <p class="lp-lead">{{ __('Dokumen Konversi mencatat batang yang dipotong, hasil potongannya, dan sisanya. Neraca ukuran wajib seimbang, dan semuanya terikat ke proyek.') }}</p>
                    <ul class="lp-check-list">
                        <li><i class="bi bi-check2-circle" aria-hidden="true"></i><span>{{ __('Sisa di atas panjang minimum kembali ke stok sebagai offcut.') }}</span></li>
                        <li><i class="bi bi-check2-circle" aria-hidden="true"></i><span>{{ __('Sisa di bawahnya menjadi waste dan ditutup lewat Berita Acara Waste.') }}</span></li>
                        <li><i class="bi bi-check2-circle" aria-hidden="true"></i><span>{{ __('Silsilah dua arah: dari hasil ke batang asal, dan sebaliknya.') }}</span></li>
                        <li><i class="bi bi-check2-circle" aria-hidden="true"></i><span>{{ __('Persentase waste per proyek dan per material tersedia di laporan.') }}</span></li>
                    </ul>
                </div>
                <div class="col-lg-7">
                    <svg class="lp-cut" viewBox="0 0 600 270" role="img" aria-labelledby="cutTitle cutDesc">
                        <title id="cutTitle">{{ __('Contoh konversi pipa') }}</title>
                        <desc id="cutDesc">{{ __('Batang pipa 6,00 meter dipotong menjadi dua potongan 2,50 meter dan sisa 1,00 meter yang kembali ke stok sebagai offcut. Offcut itu kemudian dipotong 0,90 meter dan sisa 0,10 meter menjadi waste.') }}</desc>

                        <text x="30" y="26" class="lp-cut-label">{{ __('Batang #P-000123 · 6,00 m') }}</text>
                        <rect x="30" y="38" width="548" height="30" rx="8" class="lp-cut-src"/>

                        <path d="M304 76 V100" class="lp-cut-arrow"/>
                        <path d="M296 92 L304 102 L312 92" class="lp-cut-arrow"/>

                        <rect x="30" y="110" width="225" height="30" rx="8" class="lp-cut-out"/>
                        <rect x="259" y="110" width="225" height="30" rx="8" class="lp-cut-out"/>
                        <rect x="488" y="110" width="90" height="30" rx="8" class="lp-cut-off"/>
                        <text x="142" y="164" text-anchor="middle" class="lp-cut-label">2,50 m</text>
                        <text x="371" y="164" text-anchor="middle" class="lp-cut-label">2,50 m</text>
                        <text x="533" y="164" text-anchor="middle" class="lp-cut-label">{{ __('Offcut') }} 1,00 m</text>

                        <path d="M533 172 C533 200 300 190 262 214" class="lp-cut-arrow lp-cut-dash"/>

                        <rect x="210" y="218" width="81" height="30" rx="8" class="lp-cut-out"/>
                        <rect x="295" y="218" width="12" height="30" rx="4" class="lp-cut-waste"/>
                        <text x="30" y="238" class="lp-cut-label lp-cut-small">{{ __('Dipakai lagi:') }}</text>
                        <text x="318" y="232" class="lp-cut-label lp-cut-small">0,90 m</text>
                        <text x="318" y="252" class="lp-cut-label lp-cut-small lp-cut-muted">{{ __('Waste') }} 0,10 m</text>
                    </svg>
                </div>
            </div>
        </div>
    </div>
</section>
