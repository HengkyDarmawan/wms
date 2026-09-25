@extends('print.layout')
@php use App\Domain\Template\Support\PrintFormat; @endphp

{{-- Bukti Penerimaan Barang / GRN (19-receipt-putaway §6, A-232): tanpa nilai uang (D-07). --}}
@section('isi')
    <table class="info">
        <tr>
            <td class="k">{{ __('Gudang') }}</td>
            <td>{{ $grn->warehouse?->code }} — {{ $grn->warehouse?->name }}</td>
            <td class="k">{{ __('Jenis') }}</td>
            <td>{{ $grn->receipt_type->label() }}</td>
        </tr>
        <tr>
            <td class="k">{{ __('Sumber') }}</td>
            <td>{{ $grn->sourceLabel() }}@if ($grn->vendor_doc_no) · {{ __('Surat jalan vendor') }} {{ $grn->vendor_doc_no }}@endif@if ($grn->po_ref) · {{ __('PO') }} {{ $grn->po_ref }}@endif</td>
            <td class="k">{{ __('Diterima') }}</td>
            <td>{{ $grn->received_at?->lokal()->format('d/m/Y H:i') ?? '—' }} @if ($grn->receiver) · {{ $grn->receiver->name }} @endif</td>
        </tr>
        <tr>
            <td class="k">{{ __('Selesai') }}</td>
            <td colspan="3">{{ $grn->completed_at?->lokal()->format('d/m/Y H:i') ?? '—' }}</td>
        </tr>
    </table>

    <table class="baris">
        <thead>
            <tr>
                <th style="width: 4%;">#</th>
                <th style="width: 14%;">{{ __('Kode') }}</th>
                <th>{{ __('Barang') }}</th>
                <th style="width: 18%;">{{ __('Lot / Serial / Potongan') }}</th>
                <th style="width: 14%;">{{ __('Bin terima') }}</th>
                <th class="r" style="width: 10%;">{{ __('Jumlah') }}</th>
                <th style="width: 7%;">{{ __('Satuan') }}</th>
                <th style="width: 11%;">{{ __('QC') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lines as $i => $l)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $l->item?->code }}</td>
                    <td>{{ $l->item?->name }}@if ($l->notes) <br><small>{{ $l->notes }}</small>@endif</td>
                    <td>{{ $l->trackingLabel() ?: '—' }}</td>
                    <td>{{ $l->receivingBin?->code ?? '—' }}</td>
                    <td class="r">{{ PrintFormat::qty($l->qty_received) }}</td>
                    <td>{{ $l->item?->baseUom?->code }}</td>
                    <td>{{ $l->qc_result?->label() ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($grn->notes)
        <p class="catatan"><strong>{{ __('Catatan') }}:</strong> {{ $grn->notes }}</p>
    @endif
@endsection
