@extends('layouts.app')

@section('title', __('Pindahkan ke proyek lain').' — '.$project->code)

@section('content')
    @livewire('transfer.project-move', ['project' => $project])
@endsection
