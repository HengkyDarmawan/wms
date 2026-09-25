{{-- Rel kartu fitur pembeda (riset §2.7, Blueprint §1). Hanya fitur Fase 1; yang menyusul ditandai. --}}
@php
    $features = [
        ['bi-scissors', __('Konversi & potongan'), __('Pipa 6 m dipotong jadi 2,5 m: setiap potongan punya ID dan ukuran, sisa menjadi offcut atau waste, lengkap dengan silsilahnya.'), __('Pembeda')],
        ['bi-geo-alt', __('Stok per proyek & Gudang Site'), __('Gudang utama, cabang, sampai titik di lokasi proyek. Stok on-site terlihat per proyek, termasuk aset yang sedang dipinjam.'), null],
        ['bi-hammer', __('Pemakaian material'), __('Barang habis pakai di Gudang Site dicatat terpakai oleh proyek, sehingga diminta vs terkirim vs terpakai selalu bisa dibandingkan.'), null],
        ['bi-tools', __('Aset dipinjamkan'), __('Mesin & alat punya siklus hidup sendiri: dipinjam, kembali, diperiksa dengan grade dan skor kondisi, plus peringatan jatuh tempo.'), null],
        ['bi-people', __('Portal klien'), __('Klien pemilik proyek mengajukan permintaan — termasuk barang di luar katalog — melacak pengiriman, dan mengonfirmasi penerimaan.'), null],
        ['bi-diagram-3', __('Approval berlapis'), __('Mengikuti struktur organisasi Anda, lengkap dengan delegasi dan eskalasi. Approval lewat WhatsApp menyusul.'), null],
        ['bi-clipboard-check', __('Stock opname hitung buta'), __('Penghitung tidak melihat angka sistem. Toleransi selisih, hitung ulang, dan rekonsiliasi langsung dari aplikasi.'), null],
        ['bi-qr-code-scan', __('PWA & scan kamera'), __('Pasang di HP tanpa toko aplikasi, pindai barcode/QR dengan kamera, dan simpan draf bukti terima di perangkat.'), null],
    ];
@endphp
<section class="lp-section" id="fitur" aria-labelledby="featuresTitle" x-data="rail">
    <div class="container">
        <div class="lp-rail-head lp-section-head mw-100">
            <div style="max-width: 46rem">
                <span class="lp-eyebrow">{{ __('Kenapa khusus proyek') }}</span>
                <h2 class="lp-title" id="featuresTitle">{{ __('Dibuat untuk material yang dipotong, dipinjam, dan dipakai di site') }}</h2>
                <p class="lp-lead mb-0">{{ __('WMS umum berhenti di rak gudang. Proyek butuh lebih: stok yang mengikuti lokasi kerja, potongan dengan ukuran, dan alat yang harus kembali.') }}</p>
            </div>
            <div class="lp-rail-nav">
                <button type="button" @click="go(-1)" :disabled="atStart" aria-label="{{ __('Geser ke fitur sebelumnya') }}" aria-controls="lpFeatureRail"><i class="bi bi-arrow-left" aria-hidden="true"></i></button>
                <button type="button" @click="go(1)" :disabled="atEnd" aria-label="{{ __('Geser ke fitur berikutnya') }}" aria-controls="lpFeatureRail"><i class="bi bi-arrow-right" aria-hidden="true"></i></button>
            </div>
        </div>

        <ul class="lp-rail-track" id="lpFeatureRail" x-ref="track" tabindex="0" aria-label="{{ __('Daftar fitur, geser ke samping') }}">
            @foreach ($features as $i => [$icon, $title, $text, $tag])
                <li>
                    <article class="lp-card">
                        <div class="lp-card-media lp-media-{{ $i + 1 }}" aria-hidden="true">
                            @if ($tag)
                                <span class="lp-tag">{{ $tag }}</span>
                            @endif
                            <i class="bi {{ $icon }}"></i>
                        </div>
                        <div class="lp-card-body">
                            <h3>{{ $title }}</h3>
                            <p>{{ $text }}</p>
                        </div>
                    </article>
                </li>
            @endforeach
        </ul>
    </div>
</section>
