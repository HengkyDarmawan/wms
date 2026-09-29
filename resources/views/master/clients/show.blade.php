@extends('layouts.app')

@section('title', $client->code.' — '.$client->name)

@section('content')
    @livewire('master.client-detail', ['client' => $client])
@endsection
