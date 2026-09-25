@extends('layouts.app')

@section('title', $prq->number)

@section('content')
    @livewire('purchase-request.purchase-request-detail', ['purchaseRequest' => $prq])
@endsection
