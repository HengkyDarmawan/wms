@extends('layouts.auth')

@section('title', __('Tautan tidak berlaku'))

@section('content')
    <div class="text-center">
        <i class="bi bi-link-45deg text-muted fs-1" aria-hidden="true"></i>
        <h1 class="h4 mt-2 mb-2">{{ __('Tautan tidak berlaku') }}</h1>
        <p class="text-muted mb-0">{{ __('Tautan bukti terima ini sudah kedaluwarsa, sudah dipakai, atau tidak dikenal. Minta driver menerbitkan tautan baru.') }}</p>
    </div>
@endsection
