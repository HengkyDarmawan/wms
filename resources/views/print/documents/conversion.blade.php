@extends('print.layout')
@php use App\Domain\Template\Support\PrintFormat; @endphp

@section('isi')
    <table class="info">
        <tr>
            <td class="k">{{ __('Proyek') }}</td>
            <td>{{ $cnv->project?->code }} — {{ $cnv->project?->name }}</td>
            <td class="k">{{ __('Gudang') }}</td>
            <td>{{ $cnv->warehouse?->code }} — {{ $cnv->warehouse?->name }}</td>
        </tr>
        <tr>
            <td class="k">{{ __('Jenis') }}</td>
            <td>
                {{ $cnv->conversion_type->label() }}
                @if ($cnv->reversalOf) · {{ __('Pembalik') }} {{ $cnv->reversalOf->number }} · {{ $cnv->reason?->label }} @endif
            </td>
            <td class="k">{{ __('Selesai') }}</td>
            <td>{{ $cnv->completer?->name ?? '—' }} {{ $cnv->completed_at?->lokal()->format('d/m/Y H:i') }}</td>
        </tr>
    </table>

    <p><strong>{{ __('Input') }}</strong></p>
    <table class="baris">
        <thead>
            <tr>
                <th style="width: 4%;">#</th>
                <th style="width: 16%;">{{ __('Bin') }}</th>
                <th style="width: 14%;">{{ __('Kode') }}</th>
                <th>{{ __('Barang') }}</th>
                <th style="width: 18%;">{{ __('Lot / Potongan') }}</th>
                <th class="r" style="width: 10%;">{{ __('Jumlah') }}</th>
                <th style="width: 7%;">{{ __('Satuan') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($inputs as $i => $l)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $l->bin?->code }}</td>
                    <td>{{ $l->item?->code }}</td>
                    <td>{{ $l->item?->name }}</td>
                    <td>{{ PrintFormat::tracking($l->lot, null, $l->piece) }}</td>
                    <td class="r">{{ PrintFormat::qty($l->qty_base) }}</td>
                    <td>{{ $l->item?->baseUom?->code }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p><strong>{{ __('Hasil') }}</strong></p>
    <table class="baris">
        <thead>
            <tr>
                <th style="width: 4%;">#</th>
                <th style="width: 11%;">{{ __('Jenis') }}</th>
                <th style="width: 13%;">{{ __('Bin') }}</th>
                <th style="width: 13%;">{{ __('Kode') }}</th>
                <th>{{ __('Barang') }}</th>
                <th style="width: 15%;">{{ __('Lot / Potongan') }}</th>
                <th class="r" style="width: 9%;">{{ __('Jumlah') }}</th>
                <th style="width: 7%;">{{ __('Satuan') }}</th>
                <th style="width: 12%;">{{ __('Dari') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($outputs as $i => $o)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $o->output_kind->label() }}</td>
                    <td>{{ $o->bin?->code ?? '—' }}</td>
                    <td>{{ $o->item?->code }}</td>
                    <td>{{ $o->item?->name }}</td>
                    <td>{{ PrintFormat::tracking($o->lot, null, $o->newPiece) ?: $o->lot_no }}</td>
                    <td class="r">{{ PrintFormat::qty($o->qty_base) }}</td>
                    <td>{{ $o->item?->baseUom?->code }}</td>
                    <td>{{ $o->parentInput?->piece?->piece_no }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="catatan">
        <strong>{{ __('Neraca') }}:</strong>
        {{ __('input') }} {{ PrintFormat::qty($cnv->total_input) }} =
        {{ __('output') }} {{ PrintFormat::qty($cnv->total_output) }} +
        {{ __('offcut') }} {{ PrintFormat::qty($cnv->total_offcut) }} +
        {{ __('waste') }} {{ PrintFormat::qty($cnv->total_waste) }} +
        {{ __('kerf') }} {{ PrintFormat::qty($cnv->total_kerf) }}
    </p>

    @if ($cnv->notes)
        <p class="catatan"><strong>{{ __('Catatan') }}:</strong> {{ $cnv->notes }}</p>
    @endif
@endsection
