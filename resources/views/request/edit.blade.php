@extends('layouts.app')

@section('title', __('Ubah permintaan').' — '.$req->number)

@section('content')
    @livewire('request.request-form', ['request' => $req])
@endsection
