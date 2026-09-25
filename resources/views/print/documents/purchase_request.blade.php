@extends('print.layout')
@php use App\Domain\Template\Support\PrintFormat; @endphp

{{-- Purchase Request (26-purchase-request §6, A-232): jumlah saja — nilai uang hanya di PO (D-07, A-217). --}}
@section('isi')
    <table class="info">
        <tr>
            <td class="k">{{ __('Gudang') }}</td>
            <td>{{ $prq->warehouse?->code }} — {{ $prq->warehouse?->name }}</td>
            <td class="k">{{ __('Proyek') }}</td>
            <td>{{ $prq->project ? $prq->project->code.' — '.$prq->project->name : '—' }}</td>
        </tr>
        <tr>
            <td class="k">{{ __('Asal') }}</td>
            <td>{{ $prq->origin->label() }}@if ($prq->materialRequest) · {{ $prq->materialRequest->number }}@endif</td>
            <td class="k">{{ __('Diajukan') }}</td>
            <td>{{ $prq->submitted_at?->lokal()->format('d/m/Y H:i') ?? '—' }}</td>
        </tr>
        <tr>
            <td class="k">{{ __('Disetujui') }}</td>
            <td>{{ $prq->approved_at?->lokal()->format('d/m/Y H:i') ?? '—' }}</td>
            <td class="k">{{ __('Diteruskan ke pembelian') }}</td>
            <td>{{ $prq->forwarded_at?->lokal()->format('d/m/Y H:i') ?? '—' }}</td>
        </tr>
    </table>

    <table class="baris">
        <thead>
            <tr>
                <th style="width: 4%;">#</th>
                <th style="width: 15%;">{{ __('Kode') }}</th>
                <th>{{ __('Barang') }}</th>
                <th class="r" style="width: 11%;">{{ __('Diminta') }}</th>
                <th class="r" style="width: 11%;">{{ __('Dipesan') }}</th>
                <th class="r" style="width: 11%;">{{ __('Diterima') }}</th>
                <th style="width: 8%;">{{ __('Satuan') }}</th>
                <th style="width: 12%;">{{ __('Dibutuhkan') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lines as $i => $l)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $l->item?->code }}</td>
                    <td>{{ $l->item?->name }}@if ($l->requestLine?->request) <br><small>{{ __('REQ') }} {{ $l->requestLine->request->number }}</small>@endif@if ($l->notes) <br><small>{{ $l->notes }}</small>@endif</td>
                    <td class="r">{{ PrintFormat::qty($l->qty_base) }}</td>
                    <td class="r">{{ PrintFormat::qty($l->qty_ordered) }}</td>
                    <td class="r">{{ PrintFormat::qty($l->qty_received) }}</td>
                    <td>{{ $l->item?->baseUom?->code }}</td>
                    <td>{{ $l->required_date?->format('d/m/Y') ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($orders->isNotEmpty())
        <table class="baris" style="margin-top: 8px;">
            <thead>
                <tr>
                    <th>{{ __('Catatan pemesanan') }}</th>
                    <th style="width: 22%;">{{ __('Vendor') }}</th>
                    <th style="width: 18%;">{{ __('No. PO eksternal') }}</th>
                    <th style="width: 14%;">{{ __('ETA') }}</th>
                    <th style="width: 16%;">{{ __('Dipesan') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($orders as $o)
                    <tr>
                        <td>{{ $o->lines->map(fn ($ol) => ($ol->line?->item?->code ?? '').' × '.PrintFormat::qty($ol->qty_ordered))->implode(', ') }}</td>
                        <td>{{ $o->vendor?->name ?? '—' }}</td>
                        <td>{{ $o->external_po_no ?? '—' }}</td>
                        <td>{{ $o->eta_date?->format('d/m/Y') ?? '—' }}</td>
                        <td>{{ $o->ordered_at?->lokal()->format('d/m/Y') ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($prq->notes)
        <p class="catatan"><strong>{{ __('Catatan') }}:</strong> {{ $prq->notes }}</p>
    @endif
@endsection
