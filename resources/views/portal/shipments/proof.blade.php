@extends('layouts.app')

@section('title', __('Bukti terima').' '.$sj->number)

@section('content')
    @livewire('shipment.portal-delivery-proof', ['shipment' => $sj])
@endsection
