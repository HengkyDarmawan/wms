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

    {{-- A-287: GRN vendor mencatat Dikirim vendor / Baik / Rusak / Kurang; kolom QC hanya bila ada hasil QC lama. --}}
    @php($kondisi = $grn->receipt_type->value === 'vendor' && $lines->contains(fn ($l) => $l->qty_vendor !== null || (float) $l->qty_damaged > 0))
    @php($adaQc = $lines->contains(fn ($l) => $l->qc_result !== null))
    <table class="baris">
        <thead>
            <tr>
                <th style="width: 4%;">#</th>
                <th style="width: 12%;">{{ __('Kode') }}</th>
                <th>{{ __('Barang') }}</th>
                <th style="width: 14%;">{{ __('Lot / Serial / Potongan') }}</th>
                <th style="width: 11%;">{{ __('Bin terima') }}</th>
                @if ($kondisi)
                    <th class="r" style="width: 9%;">{{ __('Dikirim vendor') }}</th>
                    <th class="r" style="width: 8%;">{{ __('Baik') }}</th>
                    <th class="r" style="width: 8%;">{{ __('Rusak') }}</th>
                    <th class="r" style="width: 8%;">{{ __('Kurang') }}</th>
                @else
                    <th class="r" style="width: 10%;">{{ __('Jumlah') }}</th>
                @endif
                <th style="width: 9%;">{{ __('Satuan') }}</th>
                @if ($adaQc)
                    <th style="width: 9%;">{{ __('QC') }}</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach ($lines as $i => $l)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $l->item?->code }}</td>
                    <td>{{ $l->item?->name }}@if ($l->is_bonus) <strong>({{ __('bonus vendor') }})</strong>@endif @if ($l->notes) <br><small>{{ $l->notes }}</small>@endif
                        @if ((float) $l->qty_damaged > 0) <br><small>{{ __('Rusak') }}: {{ $l->damageReason?->label }} → {{ $l->damagedBin?->code }}</small>@endif</td>
                    <td>{{ $l->trackingLabel() ?: '—' }}</td>
                    <td>{{ $l->receivingBin?->code ?? '—' }}</td>
                    @if ($kondisi)
                        <td class="r">{{ $l->qty_vendor === null ? '—' : PrintFormat::qty($l->qty_vendor) }}@if ($l->typedQuantity()) <br><small>{{ $l->typedQuantity() }}</small>@endif</td>
                        <td class="r">{{ PrintFormat::qty($l->qty_received) }}</td>
                        <td class="r">{{ (float) $l->qty_damaged > 0 ? PrintFormat::qty($l->qty_damaged) : '—' }}</td>
                        <td class="r">{{ (float) $l->qty_short > 0 ? PrintFormat::qty($l->qty_short) : '—' }}</td>
                    @else
                        <td class="r">{{ PrintFormat::qty($l->qty_received) }}</td>
                    @endif
                    <td>{{ $l->item?->baseUom?->code }}@if ($kemasan = PrintFormat::kemasan($l->item, $l->qty_received)) <br><small>{{ $kemasan }}</small>@endif</td>
                    @if ($adaQc)
                        <td>{{ $l->qc_result?->label() ?? '—' }}</td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($grn->notes)
        <p class="catatan"><strong>{{ __('Catatan') }}:</strong> {{ $grn->notes }}</p>
    @endif
@endsection
