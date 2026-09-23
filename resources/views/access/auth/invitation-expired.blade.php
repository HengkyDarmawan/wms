@extends('layouts.auth')

@section('title', __('Undangan Kedaluwarsa'))

@section('content')
    <div class="text-center">
        <div class="display-6 mb-3"><i class="bi bi-hourglass-bottom text-warning"></i></div>
        <h1 class="h3 mb-2">{{ __('Undangan sudah kedaluwarsa') }}</h1>
        <p class="text-muted mb-4">
            {{ __('Tautan undangan berlaku :jam jam. Minta Admin Company mengirim ulang undangan untuk Anda.', [
                'jam' => config('access.invitation.valid_hours'),
            ]) }}
        </p>
        <a class="btn btn-outline-secondary" href="{{ route('login') }}">{{ __('Kembali ke halaman masuk') }}</a>
    </div>
@endsection
