@extends('layouts.app')

@section('title', __('Impor dari Excel'))

@section('content')
    <div class="mb-3">
        <h1 class="h3 mb-1">{{ __('Impor dari Excel') }}</h1>
        <p class="text-muted mb-0">{{ __('Unduh templat, isi satu data per baris, lalu unggah. Bila ada satu baris salah, tidak ada yang disimpan dan semua kesalahan ditampilkan. Impor hanya menambah data baru.') }}</p>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            {{ $errors->first() }} @if (session('ruleCode')) <span class="badge text-bg-dark">{{ session('ruleCode') }}</span> @endif
            @if (session('rowErrors')) <pre class="small mb-0 mt-2" style="white-space:pre-wrap">{{ session('rowErrors') }}</pre> @endif
        </div>
    @endif

    @foreach ([
        'items' => ['judul' => __('Item'), 'izin' => 'item.create', 'kolom' => $items, 'catatan' => __('Kode kategori dan satuan harus sudah ada di master. Vendor tetap dan konversi satuan diisi lewat form item.')],
        'projects' => ['judul' => __('Proyek & klien'), 'izin' => 'project.create', 'kolom' => $projects, 'catatan' => __('Klien dicari dari kodenya; bila belum ada dan nama klien diisi, klien dibuat (butuh izin tambah klien). Gudang Site dibuat terpisah per proyek.')],
        'vendors' => ['judul' => __('Vendor'), 'izin' => 'vendor.create', 'kolom' => $vendors, 'catatan' => __('Jenis dan status boleh ditulis sebagai kode atau label (mis. "Toko online", "Sementara"). Vendor aktif butuh telepon atau email; bila belum lengkap, isi status Sementara. Vendor tetap per item diatur lewat form item. Tanpa harga.')],
        'opening-stock' => ['judul' => __('Saldo awal stok'), 'izin' => 'adjustment.create', 'kolom' => $opening, 'catatan' => __('Satu baris = satu bin × item (× lot/serial/potongan). Setiap gudang menjadi satu penyesuaian stok beralasan Saldo awal yang menunggu approval; stok baru masuk kartu stok setelah disetujui. Serial selalu 1 unit; potongan diisi panjangnya.')],
    ] as $jenis => $d)
        @can($d['izin'])
            <div class="card mb-3" id="impor-{{ $jenis }}">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>{{ $d['judul'] }}</strong>
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('imports.template', $jenis) }}"><i class="bi bi-download"></i> {{ __('Unduh templat') }}</a>
                </div>
                <div class="card-body row g-3">
                    <form class="col-lg-5" method="POST" action="{{ route('imports.store', $jenis) }}" enctype="multipart/form-data">
                        @csrf
                        <label class="form-label" for="berkas-{{ $jenis }}">{{ __('Berkas Excel (.xlsx, maks :n baris, 5 MB)', ['n' => $max[$jenis]]) }} <span class="wajib">*</span></label>
                        <input class="form-control mb-2" id="berkas-{{ $jenis }}" name="file" type="file" accept=".xlsx,.xls,.csv" required>
                        <button class="btn btn-primary" type="submit">{{ __('Impor') }} {{ mb_strtolower($d['judul']) }}</button>
                    </form>
                    <div class="col-lg-7">
                        <ul class="list-unstyled small mb-2">
                            @foreach ($d['kolom'] as $kunci => $judul)
                                <li><code>{{ $kunci }}</code> — {{ $judul }}</li>
                            @endforeach
                        </ul>
                        <div class="small text-muted">{{ $d['catatan'] }}</div>
                    </div>
                </div>
            </div>
        @endcan
    @endforeach
@endsection
