@extends('layouts.app')

@section('title', $cnv->number)

@section('content')
    @livewire('conversion.conversion-detail', ['conversion' => $cnv])
@endsection
