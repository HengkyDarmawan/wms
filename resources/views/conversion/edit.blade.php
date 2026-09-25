@extends('layouts.app')

@section('title', __('Ubah').' '.$cnv->number)

@section('content')
    @livewire('conversion.conversion-form', ['conversion' => $cnv])
@endsection
