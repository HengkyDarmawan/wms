@extends('print.layout')
@php use App\Domain\Template\Support\PrintFormat; @endphp

@section('isi')
    <table class="info">
        <tr>
            <td class="k">{{ __('Gudang') }}</td>
            <td>{{ $pck->warehouse?->code }} — {{ $pck->warehouse?->name }}</td>
            <td class="k">{{ __('Permintaan') }}</td>
            <td>{{ $req ?? '—' }}</td>
        </tr>
        <tr>
            <td class="k">{{ __('Picker') }}</td>
            <td>{{ $pck->assignee?->name ?? '—' }}</td>
            <td class="k">{{ __('Mulai') }}</td>
            <td>{{ $pck->started_at?->lokal()->format('d/m/Y H:i') ?? '—' }}</td>
        </tr>
    </table>

    <p class="muted" style="margin: 6px 0 0;">{{ __('Urut per bin. Pindai bin lalu barang, bawa ke Loading Area.') }}</p>

    <table class="baris">
        <thead>
            <tr>
                <th style="width: 4%;">#</th>
                <th style="width: 20%;">{{ __('Bin') }}</th>
                <th style="width: 14%;">{{ __('Kode') }}</th>
                <th>{{ __('Barang') }}</th>
                <th style="width: 18%;">{{ __('Lot / Serial / Potongan') }}</th>
                <th class="r" style="width: 9%;">{{ __('Ambil') }}</th>
                <th style="width: 7%;">{{ __('Satuan') }}</th>
                <th class="c" style="width: 8%;">{{ __('Diambil') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lines as $i => $l)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td><strong>{{ $l->bin?->code }}</strong></td>
                    <td>{{ $l->item?->code }}</td>
                    <td>{{ $l->item?->name }}</td>
                    <td>{{ PrintFormat::tracking($l->lot, $l->serial, $l->piece) }}</td>
                    <td class="r">{{ PrintFormat::qty($l->qty_allocated) }}</td>
                    <td>{{ $l->item?->baseUom?->code }}</td>
                    <td class="c">{{ $l->qty_picked !== null ? PrintFormat::qty($l->qty_picked) : '☐' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($pck->notes)
        <p class="catatan"><strong>{{ __('Catatan') }}:</strong> {{ $pck->notes }}</p>
    @endif
@endsection
