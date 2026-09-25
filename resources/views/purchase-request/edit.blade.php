@extends('layouts.app')

@section('title', __('Tinjau draf PRQ'))

@section('content')
    @livewire('purchase-request.purchase-request-form', ['purchaseRequest' => $prq])
@endsection
