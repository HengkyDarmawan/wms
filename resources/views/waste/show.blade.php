@extends('layouts.app')

@section('title', $wst->number)

@section('content')
    @livewire('waste.waste-disposal-detail', ['wasteDisposal' => $wst])
@endsection
