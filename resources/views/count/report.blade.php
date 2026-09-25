<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $count->number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #222; }
        h1 { font-size: 14px; margin: 0 0 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #999; padding: 3px 4px; }
        th { background: #eee; text-align: left; }
        .r { text-align: right; }
        .muted { color: #666; }
    </style>
</head>
<body>
    {{-- Kop layout induk (18-template-dokumen-label §5.1). --}}
    @include('print.partials.kop', ['judul' => __('Laporan Stock Opname'), 'nomor' => $count->number, 'status' => $count->status->label(), 'qr' => null])
    <div class="muted">
        {{ $count->count_type->label() }} · {{ $count->status->label() }} ·
        {{ __('Gudang') }}: {{ $count->warehouses->pluck('code')->implode(', ') }} ·
        {{ $count->freeze_bins ? __('bin dibeku') : __('tanpa pembekuan') }}<br>
        {{ __('Dibuat') }}: {{ $count->creator?->name }} ·
        {{ __('Mulai') }}: {{ $count->started_at?->lokal()->format('d/m/Y H:i') }} ·
        {{ __('Rekonsiliasi') }}: {{ $count->submitter?->name }} {{ $count->reconciled_at?->lokal()->format('d/m/Y H:i') }} ·
        {{ __('Disetujui') }}: {{ $count->approver?->name ?? __('sistem') }} {{ $count->approved_at?->lokal()->format('d/m/Y H:i') }} ·
        {{ __('Ditutup') }}: {{ $count->closed_at?->lokal()->format('d/m/Y H:i') }}
        @if ($count->lock_date_set) · {{ __('Periode stok dikunci sampai') }} {{ $count->lock_date_set->format('d/m/Y') }} @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>{{ __('Bin') }}</th>
                <th>{{ __('Item') }}</th>
                <th>{{ __('Lot/Serial/Potongan') }}</th>
                <th>{{ __('Kondisi') }}</th>
                <th class="r">{{ __('Sistem') }}</th>
                <th class="r">{{ __('Hitung 1') }}</th>
                <th class="r">{{ __('Hitung 2') }}</th>
                <th class="r">{{ __('Akhir') }}</th>
                <th class="r">{{ __('Selisih') }}</th>
                <th>{{ __('Kelas') }}</th>
                <th>{{ __('Akar masalah') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lines as $l)
                <tr>
                    <td>{{ $l->bin?->code }}</td>
                    <td>{{ $l->item?->code }} {{ $l->item?->name }}</td>
                    <td>{{ $l->trackingLabel() }}</td>
                    <td>{{ $l->stock_status->label() }}</td>
                    <td class="r">{{ number_format((float) $l->system_qty, 2, ',', '.') }}</td>
                    <td class="r">{{ $l->counted_qty_r1 !== null ? number_format((float) $l->counted_qty_r1, 2, ',', '.') : '' }}</td>
                    <td class="r">{{ $l->counted_qty_r2 !== null ? number_format((float) $l->counted_qty_r2, 2, ',', '.') : '' }}</td>
                    <td class="r">{{ $l->final_qty !== null ? number_format((float) $l->final_qty, 2, ',', '.') : '' }}</td>
                    <td class="r">{{ $l->variance_qty !== null ? number_format((float) $l->variance_qty, 2, ',', '.') : '' }}</td>
                    <td>{{ $l->variance_class?->label() }}</td>
                    <td>{{ $l->root_cause?->label() }} {{ $l->note }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($adjustments->isNotEmpty())
        <p><strong>{{ __('Penyesuaian stok') }}:</strong>
            @foreach ($adjustments as $a) {{ $a->number }} ({{ $a->status->label() }})@if (! $loop->last), @endif @endforeach
        </p>
    @endif

    <p class="muted">{{ __('Dicetak') }} {{ now()->lokal()->format('d/m/Y H:i') }} · {{ __('tanpa nilai uang') }} {{-- D-07 --}}</p>
</body>
</html>
