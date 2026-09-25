@extends('layouts.app')

@section('title', $project->code.' — '.$project->name)

@section('content')
    @livewire('master.project-detail', ['project' => $project])
@endsection
