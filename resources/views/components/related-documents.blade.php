{{--
    Dokumen terkait (A-252): asal (hulu) dan turunan (hilir) satu dokumen,
    hanya yang boleh dilihat pengguna. Pemakaian: <x-related-documents :document="$sj" />
--}}
@props(['document'])
@php($rantai = app(\App\Domain\Shared\Support\DocumentLineage::class)->for($document))
@if ($rantai['asal']->isNotEmpty() || $rantai['turunan']->isNotEmpty())
    <div class="card mb-3">
        <div class="card-header"><strong>{{ __('Dokumen terkait') }}</strong></div>
        <div class="card-body small">
            @foreach (['asal' => __('Asal'), 'turunan' => __('Turunan')] as $arah => $judul)
                @if ($rantai[$arah]->isNotEmpty())
                    <div class="d-flex flex-wrap align-items-center gap-2 {{ $arah === 'asal' ? 'mb-2' : '' }}">
                        <span class="text-muted" style="min-width: 5rem">{{ $judul }}</span>
                        @foreach ($rantai[$arah] as $d)
                            <a class="badge text-bg-light border text-decoration-none" href="{{ $d['url'] }}">
                                <span class="text-muted">{{ $d['jenis'] }}</span> {{ $d['number'] }}
                                @if ($d['status']) <span class="badge {{ $d['badge'] }} ms-1">{{ $d['status'] }}</span> @endif
                            </a>
                        @endforeach
                    </div>
                @endif
            @endforeach
        </div>
    </div>
@endif
