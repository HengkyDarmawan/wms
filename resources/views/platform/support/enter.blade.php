@extends('layouts.auth')

@section('title', __('Akses dukungan'))

@section('content')
    <h1 class="h2 mb-1">{{ __('Akses dukungan') }}</h1>
    <p class="text-muted mb-4">
        {{ __('Anda akan membuka data :company atas izin Admin Company. Sesi ini hanya-baca, tercatat di jejak audit company, dan berakhir bersama izinnya.', ['company' => tenant()?->name]) }}
    </p>

    <form method="POST" action="{{ $action }}">
        @csrf
        <button class="btn btn-primary w-100" type="submit">{{ __('Masuk sebagai dukungan') }}</button>
    </form>
@endsection
