@extends('layouts.app')

@section('title', __('Detail pengguna'))

@section('content')
    @livewire('access.user-detail', ['userId' => $userId])
@endsection
