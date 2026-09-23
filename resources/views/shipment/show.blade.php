@extends('layouts.app')

@section('title', $sj->number)

@section('content')
    @livewire('shipment.shipment-detail', ['shipment' => $sj])
@endsection
