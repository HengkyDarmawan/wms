@extends('print.layout')
@php use App\Domain\Template\Support\PrintFormat; @endphp

@section('isi')
    <table class="info">
        <tr>
            <td class="k">{{ __('Aset') }}</td>
            <td>{{ $ast->item?->code }} — {{ $ast->item?->name }} · {{ __('SN') }} {{ $ast->serial?->serial_no }}</td>
            <td class="k">{{ __('Proyek peminjam') }}</td>
            <td>{{ $ast->project?->code }} — {{ $ast->project?->name }}</td>
        </tr>
        <tr>
            <td class="k">{{ __('Gudang') }}</td>
            <td>{{ $ast->warehouse?->code }} — {{ $ast->warehouse?->name }}</td>
            <td class="k">{{ __('Surat jalan / retur') }}</td>
            <td>{{ $ast->shipment?->number ?? '—' }} @if ($ast->goodsReturn) / {{ $ast->goodsReturn->number }} @endif</td>
        </tr>
    </table>

    <table class="baris">
        <thead>
            <tr>
                <th>{{ __('Keterangan') }}</th>
                <th style="width: 25%;">{{ __('Keluar') }}</th>
                <th style="width: 25%;">{{ __('Kembali') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ __('Tanggal') }}</td>
                <td>{{ $ast->checked_out_at?->lokal()->format('d/m/Y H:i') }}</td>
                <td>{{ $ast->returned_at?->lokal()->format('d/m/Y H:i') ?? '—' }}</td>
            </tr>
            <tr>
                <td>{{ __('Jatuh tempo kembali') }}</td>
                <td>{{ $ast->due_return_date?->format('d/m/Y') ?? '—' }}</td>
                <td>{{ $ast->usage_days !== null ? $ast->usage_days.' '.__('hari pakai') : '—' }}</td>
            </tr>
            <tr>
                <td>{{ __('Kondisi') }}</td>
                <td>{{ $ast->condition_out ?? '—' }}</td>
                <td>{{ $periksa ? $periksa->condition_grade->value.' · '.$periksa->condition_score.' %' : '—' }}</td>
            </tr>
            <tr>
                <td>{{ __('Meter') }} ({{ $ast->serial?->meter_unit?->label() }})</td>
                <td class="r">{{ $ast->meter_out !== null ? PrintFormat::qty($ast->meter_out) : '—' }}</td>
                <td class="r">{{ $ast->meter_in !== null ? PrintFormat::qty($ast->meter_in) : '—' }}</td>
            </tr>
        </tbody>
    </table>

    @if ($periksa && ($periksa->component_notes ?? []) !== [])
        <p class="catatan"><strong>{{ __('Catatan komponen') }}:</strong>
            @foreach ($periksa->component_notes as $k) {{ $k['component'] }}: {{ $k['note'] }}@if (! $loop->last); @endif @endforeach
        </p>
    @endif

    @if ($ast->notes)
        <p class="catatan"><strong>{{ __('Catatan') }}:</strong> {{ $ast->notes }}</p>
    @endif
@endsection
