@extends('layouts.auth')

@section('title', __('Bukti terima tersimpan'))

@section('content')
    <div class="text-center mb-3">
        <i class="bi bi-check-circle text-success fs-1" aria-hidden="true"></i>
        <h1 class="h4 mt-2 mb-1">{{ __('Bukti terima tersimpan') }}</h1>
        <p class="text-muted mb-0">{{ $sj->number }} · {{ __('diterima oleh') }} <strong>{{ $bukti?->received_by_name }}</strong>
            · {{ $bukti?->confirmed_at?->lokal()->format('d/m/Y H:i') }}</p>
    </div>

    <table class="table table-sm mb-3">
        <thead><tr><th>{{ __('Item') }}</th><th class="text-end">{{ __('Baik') }}</th><th class="text-end">{{ __('Rusak') }}</th><th class="text-end">{{ __('Kurang') }}</th></tr></thead>
        <tbody>
            @foreach ($bukti?->lines ?? [] as $b)
                @php($sl = $sj->lines->firstWhere('id', $b->shipment_line_id))
                <tr>
                    <td>{{ $sl?->pickTaskLine?->item?->code }}</td>
                    <td class="text-end">{{ (float) $b->qty_good }}</td>
                    <td class="text-end">{{ (float) $b->qty_damaged }}</td>
                    <td class="text-end">{{ (float) $b->qty_missing }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="small text-muted mb-0">{{ __('Tautan ini sudah dipakai dan tidak bisa dibuka lagi. Terima kasih.') }}</p>
@endsection
