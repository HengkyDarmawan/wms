@extends('layouts.app')

@section('title', __('Rencana material').' · '.$project->code)

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Rencana material') }}</h1>
            <p class="text-muted mb-0">{{ $project->code }} · {{ $project->name }}</p>
        </div>
        <a wire:navigate class="btn btn-outline-secondary" href="{{ route('portal.dashboard') }}">{{ __('Kembali') }}</a>
    </div>
    @livewire('master.project-material-plan', ['project' => $project], key('rencana-portal-'.$project->id))
@endsection
