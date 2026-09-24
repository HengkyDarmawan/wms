@extends('layouts.app')

@section('title', $task->number)

@section('content')
    @livewire('receipt.putaway-detail', ['putawayTask' => $task])
@endsection
