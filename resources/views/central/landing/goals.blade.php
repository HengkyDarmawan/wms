{{-- Target dari Blueprint §2 — ditulis sebagai sasaran, bukan klaim hasil (A-223). --}}
@php
    $goals = [
        ['≥ 98%', __('Akurasi stok hasil opname'), __('Sasaran 6 bulan pertama, ≥ 99% sesudahnya')],
        ['100%', __('Material tertelusur'), __('Barang keluar, terpakai, retur, konversi, dan waste terikat ke proyek & dokumen')],
        ['0', __('Permintaan lewat chat atau kertas'), __('Semua lewat sistem atau portal klien')],
        [__('Harian'), __('Aset lewat jatuh tempo terdeteksi'), __('Peringatan otomatis sebelum aset hilang jejak')],
    ];
@endphp
<section class="lp-section pb-0" id="tujuan" aria-labelledby="goalsTitle">
    <div class="container">
        <div class="lp-section-head">
            <span class="lp-eyebrow">{{ __('Sasaran bersama') }}</span>
            <h2 class="lp-title" id="goalsTitle">{{ __('Ukuran keberhasilan yang kami kejar bersama Anda') }}</h2>
            <p class="lp-lead">{{ __('Angka di bawah adalah target penerapan, bukan janji hasil. Semuanya bisa dipantau langsung dari laporan di aplikasi.') }}</p>
        </div>
        <ul class="lp-goals">
            @foreach ($goals as [$value, $label, $note])
                <li class="lp-goal">
                    <span class="lp-goal-value">{{ $value }}</span>
                    <span class="lp-goal-label">{{ $label }}</span>
                    <span class="lp-goal-note">{{ $note }}</span>
                </li>
            @endforeach
        </ul>
    </div>
</section>
