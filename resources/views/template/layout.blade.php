@extends('layouts.app')

@section('title', __('Layout dokumen'))

@section('content')
    @error('logo')
        <div class="alert alert-danger" role="alert">{{ $message }}</div>
    @enderror

    @livewire('template.document-layout-form')
@endsection
