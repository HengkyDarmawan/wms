@extends('layouts.app')

@section('title', $req->number)

@section('content')
    @livewire('request.portal-request-detail', ['request' => $req])
@endsection
