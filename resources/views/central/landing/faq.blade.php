{{-- FAQ: akordeon Alpine. Tanpa JavaScript semua jawaban tetap terbaca. --}}
@php
    $faqs = [
        [__('Apakah ada harga barang di WMS?'), __('Tidak. WMS hanya mencatat kuantitas: berapa, di mana, milik proyek apa, dan lewat dokumen apa. Harga modal, nilai persediaan, dan tagihan dikelola modul Akuntansi yang terpisah, sehingga WMS bisa dipakai tanpa akuntansi.')],
        [__('Bagaimana cara mulai berlangganan?'), __('Hubungi kami lewat tombol Minta demo. Tim kami membuat company Anda beserta akun Admin Company, lalu Anda memulai masa trial. Wizard setup membantu mengisi gudang, rak & bin, item, proyek, dan user pertama.')],
        [__('Apakah dibatasi jumlah user, gudang, atau proyek?'), __('Tidak. Langganan dihitung flat per company per bulan. Batas paket hanya untuk kuota penyimpanan berkas dan kuota pesan WhatsApp saat fitur itu hadir.')],
        [__('Apakah data company kami terpisah dari company lain?'), __('Ya. Setiap company punya database sendiri dan diakses lewat subdomainnya sendiri. Tim platform hanya bisa membuka data Anda bila Admin Company memberi akses dukungan sementara, dan akses itu tercatat.')],
        [__('Apakah bisa dipakai di HP dan di lokasi proyek?'), __('Bisa. Aplikasi dapat dipasang di HP sebagai PWA, memindai barcode/QR dengan kamera, dan menyimpan draf bukti terima di perangkat saat sinyal putus. Mode offline penuh dengan sinkronisasi menyusul di fase berikutnya.')],
        [__('Apakah klien kami ikut memakai sistem?'), __('Ya, lewat portal klien. Klien mengajukan permintaan untuk proyeknya, melacak status dan pengiriman, melihat stok on-site proyeknya, lalu mengonfirmasi atau mengajukan keberatan atas barang yang diterima.')],
        [__('Bagaimana dengan pembelian ke vendor?'), __('Saat stok kurang, WMS menerbitkan Purchase Request dan mencatat pemesanan ke vendor atau toko online. Purchase Order dengan harga beli dan approval nilai menjadi bagian modul Purchasing.')],
        [__('Bisakah data lama dari Excel dipindahkan?'), __('Bisa. Item, proyek beserta klien, vendor, dan saldo awal stok dapat diimpor dari templat Excel. Setiap berkas diperiksa utuh: bila ada baris yang salah, tidak ada yang tersimpan dan semua kesalahan ditampilkan.')],
        [__('Bagaimana cara membayar langganan?'), __('Tagihan terbit otomatis setiap periode. Admin Company mengunggah bukti transfer dari aplikasi, lalu tim kami memverifikasinya dan masa aktif diperpanjang.')],
    ];
@endphp
<section class="lp-section" id="faq" aria-labelledby="faqTitle">
    <div class="container">
        <div class="row g-4 g-lg-5">
            <div class="col-lg-4">
                <span class="lp-eyebrow">{{ __('FAQ') }}</span>
                <h2 class="lp-title" id="faqTitle">{{ __('Pertanyaan yang sering muncul') }}</h2>
                <p class="lp-lead">{{ __('Belum terjawab? Tanyakan langsung saat demo.') }}</p>
            </div>
            <div class="col-lg-8">
                <div class="lp-faq" x-data="{ open: 0 }">
                    @foreach ($faqs as $i => [$q, $a])
                        <div class="lp-faq-item">
                            <h3 class="lp-faq-q">
                                <button type="button" id="faqQ{{ $i }}" aria-controls="faqA{{ $i }}"
                                        aria-expanded="{{ $i === 0 ? 'true' : 'false' }}"
                                        :aria-expanded="(open === {{ $i }}).toString()"
                                        @click="open = open === {{ $i }} ? null : {{ $i }}">
                                    <span>{{ $q }}</span>
                                    <i class="bi bi-plus-lg" aria-hidden="true"></i>
                                </button>
                            </h3>
                            <div class="lp-faq-a" id="faqA{{ $i }}" role="region" aria-labelledby="faqQ{{ $i }}" x-show="open === {{ $i }}">
                                {{ $a }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>
