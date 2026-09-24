@extends('layouts.app')

@section('title', __('Hitung bin'))

@section('content')
    @livewire('count.count-entry', ['countAssignment' => $assignment])
@endsection
