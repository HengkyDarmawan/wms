@extends('layouts.app')

@section('title', __('Kartu stok').' — '.$item->code)

@section('content')
    @livewire('stock.stock-card', ['item' => $item])
@endsection
