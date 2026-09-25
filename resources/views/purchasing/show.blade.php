@extends('layouts.app')

@section('title', $po->number)

@section('content')
    @livewire('purchasing.purchase-order-detail', ['purchaseOrder' => $po])
@endsection
