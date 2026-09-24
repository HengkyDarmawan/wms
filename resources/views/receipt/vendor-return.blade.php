@extends('layouts.app')

@section('title', $rtv->number)

@section('content')
    @livewire('receipt.vendor-return-detail', ['vendorReturn' => $rtv])
@endsection
