@extends('print.layout')
@php use App\Domain\Purchasing\Support\Money; use App\Domain\Template\Support\PrintFormat; @endphp

@section('isi')
    <table class="info">
        <tr>
            <td class="k">{{ __('Kepada') }}</td>
            <td>
                <strong>{{ $po->vendor?->name }}</strong><br>
                {{ $po->vendor?->address }}
                @if ($po->vendor?->contact_name || $po->vendor?->phone)
                    <br>{{ trim(($po->vendor?->contact_name ?? '').' '.($po->vendor?->phone ?? '')) }}
                @endif
            </td>
            <td class="k">{{ __('Tanggal PO') }}</td>
            <td>{{ $po->order_date?->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="k">{{ __('Kirim ke') }}</td>
            <td>{{ $po->warehouse?->code }} — {{ $po->warehouse?->name }}<br>{{ $po->warehouse?->address }}</td>
            <td class="k">{{ __('Perkiraan datang') }}</td>
            <td>{{ $po->eta_date?->format('d/m/Y') ?? '—' }}</td>
        </tr>
        <tr>
            <td class="k">{{ __('Termin') }}</td>
            <td>{{ $po->payment_terms ?? '—' }}</td>
            <td class="k">{{ __('Mata uang') }}</td>
            <td>{{ $po->currency }}</td>
        </tr>
    </table>

    <table class="baris">
        <thead>
            <tr>
                <th style="width: 4%;">#</th>
                <th style="width: 14%;">{{ __('Kode') }}</th>
                <th>{{ __('Barang') }}</th>
                <th class="r" style="width: 10%;">{{ __('Jumlah') }}</th>
                <th style="width: 7%;">{{ __('Satuan') }}</th>
                <th class="r" style="width: 15%;">{{ __('Harga satuan') }}</th>
                <th class="r" style="width: 16%;">{{ __('Jumlah harga') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lines as $i => $l)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $l->item?->code }}</td>
                    <td>{{ $l->item?->name }} <span class="muted">({{ $l->requestLine?->purchaseRequest?->number }})</span></td>
                    <td class="r">{{ PrintFormat::qty($l->qty_base) }}</td>
                    <td>{{ $l->item?->baseUom?->code }}</td>
                    <td class="r">{{ Money::format($l->unit_price) }}</td>
                    <td class="r">{{ Money::format($l->line_amount) }}</td>
                </tr>
            @endforeach
            <tr>
                <th class="r" colspan="6">{{ __('Nilai PO') }}</th>
                <th class="r">{{ Money::format($po->total_amount) }}</th>
            </tr>
        </tbody>
    </table>

    <p class="catatan muted">{{ __('Harga belum termasuk pajak. Mohon cantumkan nomor PO pada surat jalan dan faktur.') }}</p>

    @if ($po->notes)
        <p class="catatan"><strong>{{ __('Catatan') }}:</strong> {{ $po->notes }}</p>
    @endif
@endsection
