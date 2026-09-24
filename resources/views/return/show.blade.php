@extends('layouts.app')

@section('title', $ret->number)

@section('content')
    @livewire('return.return-detail', ['goodsReturn' => $ret])
@endsection
