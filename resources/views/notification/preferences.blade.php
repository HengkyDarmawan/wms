@extends('layouts.app')

@section('title', __('Preferensi notifikasi'))

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Preferensi notifikasi') }}</h1>
            <p class="text-muted mb-0">{{ __('Pilih kanal per kejadian. WhatsApp tersedia di Fase 2.') }}</p>
        </div>
        <a class="btn btn-outline-secondary" href="{{ route('notifications.index') }}">{{ __('Kembali') }}</a>
    </div>


    <form class="card" method="POST" action="{{ route('notifications.preferences.save') }}">
        @csrf
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Kejadian') }}</th>
                        <th scope="col" class="text-center">{{ __('Lonceng') }}</th>
                        <th scope="col" class="text-center">{{ __('Email') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($events as $kunci => $e)
                        @php($p = $prefs->get($kunci))
                        <tr>
                            <td>{{ __($e['label']) }}</td>
                            <td class="text-center"><input class="form-check-input" type="checkbox" name="prefs[{{ $kunci }}][in_app]" value="1" aria-label="{{ __('Lonceng') }} {{ __($e['label']) }}" @checked($p?->in_app ?? true)></td>
                            <td class="text-center"><input class="form-check-input" type="checkbox" name="prefs[{{ $kunci }}][email]" value="1" aria-label="{{ __('Email') }} {{ __($e['label']) }}" @checked($p?->email ?? $e['email'])></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer"><button class="btn btn-primary" type="submit">{{ __('Simpan') }}</button></div>
    </form>
@endsection
