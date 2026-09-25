@extends('layouts.app')

@section('title', __('Purchase Request'))

@section('content')
    @livewire('purchase-request.purchase-request-list')
@endsection
