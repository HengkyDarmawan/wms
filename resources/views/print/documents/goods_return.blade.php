@extends('print.layout')
@php use App\Domain\Template\Support\PrintFormat; @endphp

{{-- Bukti Retur dari proyek (22-retur-transfer §6, A-232): tanpa nilai uang (D-07). --}}
@section('isi')
    <table class="info">
        <tr>
            <td class="k">{{ __('Proyek') }}</td>
            <td>{{ $ret->project?->code }} — {{ $ret->project?->name }}</td>
            <td class="k">{{ __('Pemohon') }}</td>
            <td>{{ $ret->requester?->name ?? '—' }}@if ($ret->isFromClient()) <small>({{ __('klien') }})</small>@endif</td>
        </tr>
        <tr>
            <td class="k">{{ __('Dari') }}</td>
            <td>{{ $ret->fromWarehouse ? $ret->fromWarehouse->code.' — '.$ret->fromWarehouse->name : __('Lokasi klien') }}</td>
            <td class="k">{{ __('Ke gudang') }}</td>
            <td>{{ $ret->toWarehouse?->code }} — {{ $ret->toWarehouse?->name }}</td>
        </tr>
        <tr>
            <td class="k">{{ __('Cara kirim') }}</td>
            <td>{{ $ret->self_delivered ? __('Diantar sendiri') : ($ret->returnShipment?->number ?? __('Dijemput gudang')) }}</td>
            <td class="k">{{ __('SJ asal') }}</td>
            <td>{{ $ret->originShipment?->number ?? '—' }}</td>
        </tr>
        <tr>
            <td class="k">{{ __('Diterima') }}</td>
            <td>{{ $ret->received_at?->lokal()->format('d/m/Y H:i') ?? '—' }}</td>
            <td class="k">{{ __('Dipilah') }}</td>
            <td>{{ $ret->sorted_at?->lokal()->format('d/m/Y H:i') ?? '—' }}</td>
        </tr>
    </table>

    <table class="baris">
        <thead>
            <tr>
                <th style="width: 4%;">#</th>
                <th style="width: 14%;">{{ __('Kode') }}</th>
                <th>{{ __('Barang') }}</th>
                <th style="width: 18%;">{{ __('Lot / Serial / Potongan') }}</th>
                <th style="width: 14%;">{{ __('Asal') }}</th>
                <th class="r" style="width: 10%;">{{ __('Diretur') }}</th>
                <th class="r" style="width: 10%;">{{ __('Diterima') }}</th>
                <th style="width: 7%;">{{ __('Satuan') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lines as $i => $l)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $l->item?->code }}</td>
                    <td>{{ $l->item?->name }}@if ($l->reason) <br><small>{{ __('Alasan') }}: {{ $l->reason->label }}</small>@endif@if ($l->notes) <br><small>{{ $l->notes }}</small>@endif</td>
                    <td>{{ $l->trackingLabel() ?: '—' }}</td>
                    <td>{{ $l->source()->label() }}</td>
                    <td class="r">{{ PrintFormat::qty($l->qty_base) }}</td>
                    <td class="r">{{ PrintFormat::qty($l->qty_received) }}</td>
                    <td>{{ $l->item?->baseUom?->code }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($ret->notes)
        <p class="catatan"><strong>{{ __('Catatan') }}:</strong> {{ $ret->notes }}</p>
    @endif
@endsection
