@extends('print.layout')
@php use App\Domain\Template\Support\PrintFormat; @endphp

@section('isi')
    <table class="info">
        <tr>
            <td class="k">{{ __('Proyek') }}</td>
            <td>{{ $isu->project?->code }} — {{ $isu->project?->name }}</td>
            <td class="k">{{ __('Gudang Site') }}</td>
            <td>{{ $isu->warehouse?->code }} — {{ $isu->warehouse?->name }}</td>
        </tr>
        <tr>
            <td class="k">{{ __('Dikonfirmasi') }}</td>
            <td>{{ $isu->confirmer?->name ?? '—' }} {{ $isu->confirmed_at?->lokal()->format('d/m/Y H:i') }}</td>
            <td class="k">{{ __('Jenis') }}</td>
            <td>
                @if ($isu->reversalOf)
                    {{ __('Pembalik') }} {{ $isu->reversalOf->number }} · {{ $isu->reason?->label }}
                @else
                    {{ __('Pemakaian di proyek') }}
                @endif
            </td>
        </tr>
    </table>

    <table class="baris">
        <thead>
            <tr>
                <th style="width: 4%;">#</th>
                <th style="width: 16%;">{{ __('Bin') }}</th>
                <th style="width: 13%;">{{ __('Kode') }}</th>
                <th>{{ __('Barang') }}</th>
                <th style="width: 15%;">{{ __('Lot / Serial / Potongan') }}</th>
                <th class="r" style="width: 9%;">{{ __('Jumlah') }}</th>
                <th style="width: 7%;">{{ __('Satuan') }}</th>
                <th style="width: 20%;">{{ __('Keperluan') }}</th>
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
                    <td class="r">{{ PrintFormat::qty($l->qty_base) }}</td>
                    <td>{{ $l->item?->baseUom?->code }}</td>
                    <td>{{ $l->work_note }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($isu->notes)
        <p class="catatan"><strong>{{ __('Catatan') }}:</strong> {{ $isu->notes }}</p>
    @endif
@endsection
