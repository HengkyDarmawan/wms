@extends('layouts.app')

@section('title', __('PRQ manual'))

@section('content')
    @livewire('purchase-request.purchase-request-form')
@endsection
