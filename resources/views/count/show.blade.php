@extends('layouts.app')

@section('title', $count->number)

@section('content')
    @livewire('count.count-detail', ['stockCount' => $count])
@endsection
