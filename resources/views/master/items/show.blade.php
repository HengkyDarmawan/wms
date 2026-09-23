@extends('layouts.app')

@section('title', $item->name)

@section('content')
    @livewire('master.item-detail', ['item' => $item])
@endsection
