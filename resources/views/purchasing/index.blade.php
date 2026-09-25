@extends('layouts.app')

@section('title', __('Purchase Order'))

@section('content')
    @livewire('purchasing.purchase-order-list')
@endsection
