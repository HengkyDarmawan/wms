@extends('layouts.app')

@section('title', $trf->number)

@section('content')
    @livewire('transfer.transfer-detail', ['transfer' => $trf])
@endsection
