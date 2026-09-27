{{--
    Dokumen terkait (A-252): asal (hulu) dan turunan (hilir) satu dokumen,
    hanya yang boleh dilihat pengguna. Pemakaian: <x-related-documents :document="$sj" />
--}}
@props(['document'])
@php($rantai = app(\App\Domain\Shared\Support\DocumentLineage::class)->for($document))
{{-- A-263: riwayat cetak dokumen ini (dan bukti terimanya untuk SJ). --}}
@php($cetak = app(\App\Domain\Template\Support\PrintHistory::class)->forModel($document))
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
@if ($cetak->isNotEmpty())
    <div class="card mb-3">
        <details>
            <summary class="card-header d-flex justify-content-between" style="cursor: pointer;">
                <strong>{{ __('Riwayat cetak') }}</strong>
                <span class="small text-muted">{{ __(':n kali · terakhir :waktu oleh :nama', ['n' => $cetak->count(), 'waktu' => $cetak->first()->printed_at->lokal()->format('d/m/Y H:i'), 'nama' => $cetak->first()->printer?->name ?? '—']) }}</span>
            </summary>
            <div class="table-responsive">
                <table class="table table-sm mb-0 small">
                    <thead><tr><th>{{ __('Cetakan') }}</th><th>{{ __('Jenis') }}</th><th>{{ __('Waktu') }}</th><th>{{ __('Oleh') }}</th></tr></thead>
                    <tbody>
                        @foreach ($cetak as $c)
                            <tr>
                                <td>{{ __('ke-:n', ['n' => $c->copy_no]) }} @if ($c->copy_no > 1 && \App\Domain\Template\Support\PrintHistory::marked($c->document_type)) <span class="badge text-bg-danger">{{ __('cetak ulang') }}</span> @endif</td>
                                <td>{{ $c->document_type->label() }}</td>
                                <td>{{ $c->printed_at->lokal()->format('d/m/Y H:i') }}</td>
                                <td>{{ $c->printer?->name ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    </div>
@endif
