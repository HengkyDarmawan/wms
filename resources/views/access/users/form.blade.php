@extends('layouts.app')

@section('title', $userId ? __('Ubah pengguna') : __('Tambah pengguna'))

@section('content')
    @livewire('access.user-form', ['userId' => $userId])
@endsection
