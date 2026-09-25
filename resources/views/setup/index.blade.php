@extends('layouts.app')

@section('title', __('Setup awal company'))

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Setup awal company') }}</h1>
            <p class="text-muted mb-0">{{ __('Ikuti langkah berikut agar company siap dipakai. Langkah dicentang otomatis begitu datanya ada.') }}</p>
        </div>
        <span class="badge text-bg-{{ $completed ? 'success' : 'secondary' }} fs-6">{{ $progress['done'] }}/{{ $progress['total'] }}</span>
    </div>

    @if ($errors->any()) <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div> @endif

    <div class="card mb-3">
        <ol class="list-group list-group-flush list-group-numbered">
            @foreach ($steps as $s)
                <li class="list-group-item d-flex justify-content-between align-items-start gap-2">
                    <div class="ms-2 me-auto">
                        <div class="fw-semibold">
                            {{ __($s['label']) }}
                            @if ($s['optional']) <span class="badge text-bg-light">{{ __('opsional') }}</span> @endif
                        </div>
                        <div class="small text-muted">{{ __($s['hint']) }}</div>
                        @if ($s['key'] === 'terms' && ! $s['done'])
                            <form class="mt-2" method="POST" action="{{ route('setup.terms') }}">
                                @csrf
                                <div class="small border rounded p-2 mb-2 bg-body-tertiary" style="max-height:10rem;overflow:auto">
                                    {{ __('Versi sementara (:versi). Data pribadi pengguna, klien, dan penerima barang hanya dipakai untuk operasional gudang company ini, diakses sesuai role, dapat diekspor atau dihapus atas permintaan, dan tidak dibagikan ke pihak lain di luar penyedia layanan platform. Naskah final ketentuan layanan & kebijakan privasi menyusul (O-11).', ['versi' => $termsVersion]) }}
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="setuju" name="agree" value="1">
                                    <label class="form-check-label small" for="setuju">{{ __('Saya menyetujui atas nama company') }}</label>
                                </div>
                                <button class="btn btn-sm btn-primary" type="submit">{{ __('Setujui') }}</button>
                            </form>
                        @endif
                    </div>
                    @if ($s['done'])
                        <span class="badge text-bg-success"><i class="bi bi-check2"></i> {{ __('Selesai') }}</span>
                    @elseif ($s['url'])
                        <a class="btn btn-sm btn-outline-primary" href="{{ $s['url'] }}">{{ __('Kerjakan') }}</a>
                    @endif
                </li>
            @endforeach
        </ol>
        <div class="card-footer">
            @if ($completed)
                <span class="text-success small">{{ __('Setup awal sudah ditandai selesai.') }}</span>
            @else
                <form method="POST" action="{{ route('setup.complete') }}">
                    @csrf
                    <button class="btn btn-primary" type="submit" @disabled($progress['required_left'] > 0)>{{ __('Tandai setup selesai') }}</button>
                    @if ($progress['required_left'] > 0) <span class="small text-muted ms-2">{{ __(':n langkah wajib tersisa', ['n' => $progress['required_left']]) }}</span> @endif
                </form>
            @endif
        </div>
    </div>
@endsection
