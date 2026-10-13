@extends('layouts.app')

@section('title', __('Setup awal company'))

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Setup awal company') }}</h1>
            <p class="text-muted mb-0">{{ __('Ikuti langkah berikut agar company siap dipakai. Langkah dicentang otomatis begitu datanya ada.') }}</p>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            {{-- A-414: pemandu di layar. --}}
            <x-tur kunci="setup" :langkah="[
                ['el' => '', 'judul' => __('Setup awal'), 'isi' => __('Daftar ini urutan menyiapkan company dari nol. Kerjakan dari atas ke bawah; tiap langkah tercentang sendiri begitu datanya ada.')],
                ['el' => '[data-langkah]', 'judul' => __('Satu langkah'), 'isi' => __('Judul = apa yang dikerjakan, tulisan kecil = caranya, Dianggap selesai bila = tanda langkah beres. Klik tombol di kanan untuk membuka layarnya.')],
                ['el' => '[data-langkah] .btn', 'judul' => __('Tombol Kerjakan'), 'isi' => __('Membuka layar yang tepat. Di layar itu ada tombol Tunjukkan caranya lagi bila bingung.')],
                ['el' => '', 'judul' => __('Impor Excel tidak wajib'), 'isi' => __('Semua data bisa diisi satu per satu. Impor Excel hanya jalan pintas bila datanya banyak.')],
                ['el' => '[data-tandai-selesai]', 'judul' => __('Terakhir'), 'isi' => __('Bila semua langkah wajib tercentang, klik Tandai setup selesai.')],
            ]" />
            <span class="badge text-bg-{{ $completed ? 'success' : 'secondary' }} fs-6">{{ $progress['done'] }}/{{ $progress['total'] }}</span>
        </div>
    </div>

    @if ($errors->any()) <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div> @endif

    <div class="card mb-3">
        <ol class="list-group list-group-flush list-group-numbered">
            @php($langkahTur = collect($steps)->first(fn ($s) => ! $s['done'] && $s['url'])['key'] ?? ($steps[0]['key'] ?? null))
            @foreach ($steps as $s)
                <li class="list-group-item d-flex justify-content-between align-items-start gap-2" @if ($s['key'] === $langkahTur) data-langkah @endif>
                    <div class="ms-2 me-auto">
                        <div class="fw-semibold">
                            {{ __($s['label']) }}
                            @if ($s['optional']) <span class="badge text-bg-light">{{ __('opsional') }}</span> @endif
                        </div>
                        <div class="small text-muted">{{ __($s['hint']) }}</div>
                        {{-- A-402: kalimat awam kapan langkah dianggap selesai + keadaan saat ini. --}}
                        @if (($s['selesai_bila'] ?? '') !== '')
                            <div class="small mt-1" data-selesai-bila><span class="text-muted">{{ __('Dianggap selesai bila') }}:</span> {{ __($s['selesai_bila']) }}</div>
                        @endif
                        @if (! empty($s['catatan']))
                            <div class="small mt-1 text-{{ $s['done'] ? 'muted' : 'warning-emphasis' }}" data-catatan-langkah>
                                <i class="bi bi-info-circle"></i> {{ $s['catatan']['teks'] }}
                                @if ($s['catatan']['url']) <a href="{{ $s['catatan']['url'] }}">{{ __('Buka') }}</a> @endif
                            </div>
                        @endif
                        @if (($s['links'] ?? []) !== [])
                            <div class="d-flex flex-wrap gap-2 mt-1">
                                @foreach ($s['links'] as $tautan)
                                    <a class="small" href="{{ $tautan['url'] }}">{{ __($tautan['label']) }}</a>
                                @endforeach
                            </div>
                        @endif
                        @if ($s['key'] === 'terms' && ! $s['done'])
                            <form class="mt-2" method="POST" action="{{ route('setup.terms') }}">
                                @csrf
                                <div class="small border rounded p-2 mb-2 bg-body-tertiary" style="max-height:10rem;overflow:auto">
                                    {{ __('Versi sementara (:versi). Data pribadi pengguna, klien, dan penerima barang hanya dipakai untuk operasional gudang company ini, diakses sesuai role, dapat diekspor atau dihapus atas permintaan, dan tidak dibagikan ke pihak lain di luar penyedia layanan platform. Naskah final ketentuan layanan & kebijakan privasi menyusul (O-11).', ['versi' => $termsVersion]) }}
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="setuju" name="agree" value="1">
                                    <label class="form-check-label small" for="setuju">{{ __('Saya menyetujui atas nama company') }}</label>
                                </div>
                                <button class="btn btn-sm btn-primary" type="submit">{{ __('Setujui') }}</button>
                            </form>
                        @endif
                    </div>
                    @if ($s['done'])
                        <div class="text-end">
                            <span class="badge text-bg-success"><i class="bi bi-check2"></i> {{ __('Selesai') }}</span>
                            {{-- A-402: langkah selesai tetap bisa dibuka lagi (menambah data). --}}
                            @if ($s['url']) <div><a class="small" href="{{ $s['url'] }}">{{ __('Buka') }}</a></div> @endif
                        </div>
                    @elseif ($s['url'])
                        <a class="btn btn-sm btn-outline-primary text-nowrap" href="{{ $s['url'] }}">{{ __('Kerjakan') }}</a>
                    @endif
                </li>
            @endforeach
        </ol>
        <div class="card-footer">
            @if ($completed)
                <span class="text-success small">{{ __('Setup awal sudah ditandai selesai.') }}</span>
            @else
                <form method="POST" action="{{ route('setup.complete') }}">
                    @csrf
                    <button class="btn btn-primary" type="submit" @disabled($progress['required_left'] > 0) data-tandai-selesai>{{ __('Tandai setup selesai') }}</button>
                    @if ($progress['required_left'] > 0) <span class="small text-muted ms-2">{{ __(':n langkah wajib tersisa', ['n' => $progress['required_left']]) }}</span> @endif
                </form>
            @endif
        </div>
    </div>
@endsection
