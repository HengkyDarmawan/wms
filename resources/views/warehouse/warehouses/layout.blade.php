@extends('layouts.app')

@section('title', __('Denah gudang').' — '.$warehouse->code)

@section('content')
    @livewire('warehouse.warehouse-layout', ['warehouse' => $warehouse])
@endsection
