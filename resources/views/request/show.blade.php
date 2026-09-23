@extends('layouts.app')

@section('title', $req->number)

@section('content')
    @livewire('request.request-detail', ['request' => $req])
@endsection
