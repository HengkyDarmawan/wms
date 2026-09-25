{{-- Alur utama Blueprint §7: dokumen niat → pergerakan fisik → pemakaian di site. --}}
@php
    $steps = [
        ['REQ', __('Permintaan Material'), __('Pemohon internal atau klien mengajukan kebutuhan untuk satu proyek, lengkap dengan tanggal dibutuhkan. Barang di luar katalog pun bisa diminta.')],
        [null, __('Approval'), __('Permintaan mengalir ke penyetuju sesuai struktur organisasi dan aturan yang Anda atur sendiri, berlapis bila perlu.')],
        ['PCK', __('Tugas Picking'), __('Stok dialokasikan dari bin yang tepat. Staf gudang mengambil barang sambil memindai bin dan item.')],
        ['SJ', __('Surat Jalan'), __('Barang dikirim dengan kendaraan sendiri, ekspedisi, atau diantar. Satu surat jalan boleh memuat beberapa permintaan ke tujuan yang sama.')],
        [null, __('Bukti Terima'), __('Driver atau penerima mencatat jumlah baik, rusak, atau kurang per baris dengan foto dan tanda tangan. Selisih langsung ditindaklanjuti.')],
        ['ISU', __('Pemakaian Material'), __('Barang di Gudang Site dicatat terpakai oleh proyek. Sisa bisa diretur, ditransfer, atau dipotong ulang.')],
    ];
@endphp
<section class="lp-section lp-section-alt" id="cara-kerja" aria-labelledby="stepsTitle">
    <div class="container">
        <div class="lp-section-head">
            <span class="lp-eyebrow">{{ __('Cara kerja') }}</span>
            <h2 class="lp-title" id="stepsTitle">{{ __('Dari permintaan sampai terpakai di site') }}</h2>
            <p class="lp-lead">{{ __('Setiap langkah adalah dokumen bernomor dengan timeline: siapa, kapan, lewat kanal apa. Stok hanya bergerak lewat kartu stok, jadi saldo selalu bisa ditelusuri.') }}</p>
        </div>
        <ol class="lp-steps">
            @foreach ($steps as $i => [$code, $title, $text])
                <li class="lp-step">
                    <span class="lp-step-num" aria-hidden="true">{{ $i + 1 }}</span>
                    <h3>{{ $title }} @if ($code)<span class="lp-code">{{ $code }}</span>@endif</h3>
                    <p>{{ $text }}</p>
                </li>
            @endforeach
        </ol>
    </div>
</section>
