@extends('layouts.app')

@section('title', $item ? __('Ubah item') : __('Item baru'))

@section('content')
    @livewire('master.item-form', ['item' => $item])
@endsection
