@extends('layouts.auth')

@section('title', __('Verifikasi tanda tangan'))

@section('content')
    @if ($segel === null)
        <div class="text-center">
            <i class="bi bi-x-octagon text-danger fs-1" aria-hidden="true"></i>
            <h1 class="h4 mt-2">{{ __('Segel tidak dikenal') }}</h1>
            <p class="text-muted">{{ __('Kode QR ini tidak tercatat di sistem. Dokumen mungkin bukan cetakan asli atau QR-nya rusak.') }}</p>
        </div>
    @else
        <div class="text-center mb-3">
            @if ($utuh && ! $batal)
                <i class="bi bi-patch-check-fill text-success fs-1" aria-hidden="true"></i>
                <h1 class="h4 mt-2 mb-1">{{ __('Tanda tangan tercatat di sistem') }}</h1>
            @elseif ($batal)
                <i class="bi bi-exclamation-triangle-fill text-warning fs-1" aria-hidden="true"></i>
                <h1 class="h4 mt-2 mb-1">{{ __('Dokumen ini sudah dibatalkan') }}</h1>
            @else
                <i class="bi bi-shield-exclamation text-danger fs-1" aria-hidden="true"></i>
                <h1 class="h4 mt-2 mb-1">{{ __('Data segel tidak cocok') }}</h1>
            @endif
            <p class="text-muted mb-0">{{ $company }}</p>
        </div>

        <table class="table table-sm mb-3">
            <tbody>
                <tr><th class="w-50">{{ __('Dokumen') }}</th><td>{{ $segel->document_type->label() }}</td></tr>
                <tr><th>{{ __('Nomor') }}</th><td class="font-monospace">{{ $segel->document_number }}</td></tr>
                @if ($status)
                    <tr><th>{{ __('Status sekarang') }}</th><td>{{ $status }}</td></tr>
                @endif
                @foreach ($ringkasan as $kunci => $nilai)
                    <tr><th>{{ $kunci }}</th><td>{{ $nilai }}</td></tr>
                @endforeach
            </tbody>
        </table>

        <h2 class="h6">{{ __('Penanda tangan') }}</h2>
        <table class="table table-sm mb-3">
            <tbody>
                <tr><th class="w-50">{{ __('Nama') }}</th><td>{{ $segel->signer_name }}</td></tr>
                @if ($segel->signer_title)
                    <tr><th>{{ __('Jabatan') }}</th><td>{{ $segel->signer_title }}</td></tr>
                @endif
                <tr><th>{{ __('Sebagai') }}</th><td>{{ $segel->block_label }}</td></tr>
                <tr><th>{{ __('Waktu tindakan') }}</th><td>{{ $segel->acted_at?->lokal()->format('d/m/Y H:i') ?? __('tidak tercatat') }}</td></tr>
                <tr><th>{{ __('Segel diterbitkan') }}</th><td>{{ $segel->sealed_at->lokal()->format('d/m/Y H:i') }}</td></tr>
                <tr><th>{{ __('Kode segel') }}</th><td class="font-monospace">{{ $segel->shortCode() }} @if ($utuh) <span class="badge text-bg-success">{{ __('utuh') }}</span> @else <span class="badge text-bg-danger">{{ __('tidak cocok') }}</span> @endif</td></tr>
            </tbody>
        </table>

        @if ($lain->isNotEmpty())
            <h2 class="h6">{{ __('Tanda tangan lain di dokumen ini') }}</h2>
            <ul class="list-unstyled small mb-3">
                @foreach ($lain as $s)
                    <li>{{ $s->block_label }}: <strong>{{ $s->signer_name }}</strong> · {{ ($s->acted_at ?? $s->sealed_at)->lokal()->format('d/m/Y H:i') }}</li>
                @endforeach
            </ul>
        @endif

        <p class="small text-muted">{{ __('Cocokkan kode segel dan nama di atas dengan yang tercetak di kertas. Isi barang, jumlah, dan harga hanya bisa dilihat pengguna yang berhak setelah masuk.') }}</p>

        @if ($tautan)
            <a class="btn btn-outline-primary w-100" href="{{ $tautan }}">{{ __('Buka dokumen (perlu masuk)') }}</a>
        @endif
    @endif
@endsection
