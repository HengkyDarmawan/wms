@extends('layouts.app')

@section('title', __('Riwayat harga beli'))

@section('content')
    @livewire('purchasing.price-history', ['item' => $item])
@endsection
