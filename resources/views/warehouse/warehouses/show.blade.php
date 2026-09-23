@extends('layouts.app')

@section('title', $warehouse->name)

@section('content')
    @livewire('warehouse.warehouse-detail', ['warehouse' => $warehouse])
@endsection
