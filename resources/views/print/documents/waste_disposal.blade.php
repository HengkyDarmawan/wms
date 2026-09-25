@extends('print.layout')
@php use App\Domain\Template\Support\PrintFormat; @endphp

@section('isi')
    <table class="info">
        <tr>
            <td class="k">{{ __('Proyek') }}</td>
            <td>{{ $wst->project?->code }} — {{ $wst->project?->name }}</td>
            <td class="k">{{ __('Gudang') }}</td>
            <td>{{ $wst->warehouse?->code }} — {{ $wst->warehouse?->name }}</td>
        </tr>
        <tr>
            <td class="k">{{ __('Disposisi') }}</td>
            <td>
                {{ $wst->disposition->label() }}
                @if ($wst->targetBin) · {{ __('ke bin') }} {{ $wst->targetBin->code }} @endif
            </td>
            <td class="k">{{ __('Bukti') }}</td>
            <td>{{ $wst->evidence_note ?? ($wst->evidence_path ? __('Foto terlampir di sistem') : '—') }}</td>
        </tr>
    </table>

    <table class="baris">
        <thead>
            <tr>
                <th style="width: 4%;">#</th>
                <th style="width: 15%;">{{ __('Bin') }}</th>
                <th style="width: 13%;">{{ __('Kode') }}</th>
                <th>{{ __('Barang') }}</th>
                <th style="width: 15%;">{{ __('Lot / Serial / Potongan') }}</th>
                <th style="width: 9%;">{{ __('Kondisi') }}</th>
                <th class="r" style="width: 9%;">{{ __('Jumlah') }}</th>
                <th style="width: 7%;">{{ __('Satuan') }}</th>
                <th style="width: 14%;">{{ __('Alasan') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lines as $i => $l)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $l->bin?->code }}</td>
                    <td>{{ $l->item?->code }}</td>
                    <td>{{ $l->item?->name }}</td>
                    <td>{{ PrintFormat::tracking($l->lot, $l->serial, $l->piece) }}</td>
                    <td>{{ $l->stock_status->label() }}</td>
                    <td class="r">{{ PrintFormat::qty($l->qty_base) }}</td>
                    <td>{{ $l->item?->baseUom?->code }}</td>
                    <td>{{ $l->reason?->label }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($wst->notes)
        <p class="catatan"><strong>{{ __('Catatan') }}:</strong> {{ $wst->notes }}</p>
    @endif
@endsection
