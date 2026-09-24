@extends('layouts.app')

@section('title', $adj->number)

@section('content')
    @livewire('adjustment.adjustment-detail', ['stockAdjustment' => $adj])
@endsection
