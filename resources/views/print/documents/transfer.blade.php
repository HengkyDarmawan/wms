@extends('print.layout')
@php use App\Domain\Template\Support\PrintFormat; @endphp

{{-- Surat Transfer (22-retur-transfer §6, A-232): tanpa nilai uang (D-07). --}}
@section('isi')
    <table class="info">
        <tr>
            <td class="k">{{ __('Dari gudang') }}</td>
            <td>{{ $trf->fromWarehouse?->code }} — {{ $trf->fromWarehouse?->name }}@if ($trf->fromProject) <br><small>{{ __('Proyek') }} {{ $trf->fromProject->code }} — {{ $trf->fromProject->name }}</small>@endif</td>
            <td class="k">{{ __('Jenis') }}</td>
            <td>{{ $trf->kind()->label() }}</td>
        </tr>
        <tr>
            <td class="k">{{ __('Ke gudang') }}</td>
            <td>{{ $trf->toWarehouse?->code }} — {{ $trf->toWarehouse?->name }}@if ($trf->toProject) <br><small>{{ __('Proyek') }} {{ $trf->toProject->code }} — {{ $trf->toProject->name }}</small>@endif</td>
            <td class="k">{{ __('Disetujui') }}</td>
            <td>{{ $trf->approved_at?->lokal()->format('d/m/Y H:i') ?? '—' }}</td>
        </tr>
        <tr>
            <td class="k">{{ __('Rujukan') }}</td>
            <td>{{ $rujukan ?: '—' }}</td>
            <td class="k">{{ __('Selesai') }}</td>
            <td>{{ $trf->completed_at?->lokal()->format('d/m/Y H:i') ?? '—' }}</td>
        </tr>
    </table>

    <table class="baris">
        <thead>
            <tr>
                <th style="width: 4%;">#</th>
                <th style="width: 15%;">{{ __('Kode') }}</th>
                <th>{{ __('Barang') }}</th>
                <th class="r" style="width: 11%;">{{ __('Diminta') }}</th>
                <th class="r" style="width: 11%;">{{ __('Dikirim') }}</th>
                <th class="r" style="width: 11%;">{{ __('Diterima') }}</th>
                <th style="width: 8%;">{{ __('Satuan') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lines as $i => $l)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $l->item?->code }}</td>
                    <td>{{ $l->item?->name }}@if ($l->notes) <br><small>{{ $l->notes }}</small>@endif</td>
                    <td class="r">{{ PrintFormat::qty($l->qty_base) }}</td>
                    <td class="r">{{ PrintFormat::qty($l->qty_shipped) }}</td>
                    <td class="r">{{ PrintFormat::qty($l->qty_received) }}</td>
                    <td>{{ $l->item?->baseUom?->code }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($trf->notes)
        <p class="catatan"><strong>{{ __('Catatan') }}:</strong> {{ $trf->notes }}</p>
    @endif
@endsection
