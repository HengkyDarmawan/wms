{{-- Role bawaan Blueprint §4.2 (nama sesuai glosarium) dan kanal kerjanya. --}}
@php
    $roles = [
        ['bi-person-badge', __('Kepala Gudang'), __('Menyetujui permintaan, transfer, dan penyesuaian; mengawasi gudang yang ditugaskan; menyelesaikan selisih pengiriman.'), [__('Web'), __('PWA')]],
        ['bi-box-seam', __('Staf Gudang'), __('Penerimaan, QC, put-away, picking, konversi, pemakaian material di site, dan hitung stok — cukup dari HP.'), [__('PWA'), __('Web')]],
        ['bi-truck', __('Driver'), __('Menerima tugas kirim, membawa surat jalan digital, dan mengambil bukti terima berupa foto dan tanda tangan.'), [__('PWA')]],
        ['bi-person-workspace', __('Pemohon Internal'), __('Engineer atau PIC proyek mengajukan permintaan, mengonfirmasi terima, dan mengajukan retur dari proyek.'), [__('Web'), __('PWA')]],
        ['bi-buildings', __('Klien'), __('Pemilik proyek mengajukan permintaan lewat portal, melacak status dan tanggal janji, lalu mengonfirmasi atau mengajukan keberatan terima.'), [__('Portal klien')]],
        ['bi-graph-up-arrow', __('Manajemen'), __('Dashboard dan laporan seluruh gudang, material per proyek, waste, dan akurasi stok; penyetuju tingkat atas.'), [__('Web')]],
    ];
@endphp
<section class="lp-section" id="untuk-siapa" aria-labelledby="rolesTitle">
    <div class="container">
        <div class="lp-section-head">
            <span class="lp-eyebrow">{{ __('Untuk siapa') }}</span>
            <h2 class="lp-title" id="rolesTitle">{{ __('Satu sistem, setiap orang melihat bagiannya') }}</h2>
            <p class="lp-lead">{{ __('Role bawaan bisa diubah Admin Company. Hak akses melekat pada penugasan — misalnya Kepala Gudang untuk satu gudang dan Staf Gudang untuk gudang lain.') }}</p>
        </div>
        <div class="row g-4">
            @foreach ($roles as [$icon, $name, $text, $channels])
                <div class="col-md-6 col-lg-4">
                    <article class="lp-role">
                        <span class="lp-role-icon" aria-hidden="true"><i class="bi {{ $icon }}"></i></span>
                        <h3>{{ $name }}</h3>
                        <p>{{ $text }}</p>
                        <ul class="lp-chips" aria-label="{{ __('Kanal') }}">
                            @foreach ($channels as $channel)
                                <li>{{ $channel }}</li>
                            @endforeach
                        </ul>
                    </article>
                </div>
            @endforeach
        </div>
    </div>
</section>
