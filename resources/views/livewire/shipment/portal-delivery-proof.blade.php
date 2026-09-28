<div>
    <div class="mb-3">
        <h1 class="h3 mb-1">{{ __('Bukti terima') }} {{ $sj->number }}</h1>
        <p class="text-muted mb-0">
            {{ $sj->warehouse?->code }} → {{ $sj->destinationLabel() }}
            · {{ $sj->shipment_method->label() }}: {{ $sj->carrierLabel() ?: '—' }}
            @if ($sj->shipped_at) · {{ __('Berangkat') }} {{ $sj->shipped_at->lokal()->format('d/m/Y H:i') }} @endif
            @if ($poKlien !== []) · {{ __('No. PO klien') }} {{ implode(', ', $poKlien) }} @endif
        </p>
    </div>

    @if ($ruleError !== '')
        <div class="alert alert-danger" role="alert">
            {{ $ruleError }}
            @if ($ruleCode !== '') <span class="badge text-bg-dark ms-1">{{ $ruleCode }}</span> @endif
        </div>
    @endif

    <div class="alert alert-light border small">
        {{ __('Periksa barang bersama pengantar. Isi jumlah baik, rusak, atau kurang per baris; foto wajib untuk yang rusak. Unggah foto surat jalan yang sudah Anda tanda tangani dan cap.') }}
    </div>

    @include('shipment.partials.proof-form', ['batal' => 'kembali'])
</div>
