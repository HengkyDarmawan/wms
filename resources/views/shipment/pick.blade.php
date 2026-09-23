@extends('layouts.app')

@section('title', $task->number)

@section('content')
    @livewire('shipment.pick-detail', ['pickTask' => $task])
@endsection
