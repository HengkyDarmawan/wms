@extends('layouts.app')

@section('title', __('Bin'))

@section('content')
    @livewire('warehouse.bin-list')
@endsection
