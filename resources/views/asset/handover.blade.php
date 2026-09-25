@extends('layouts.app')

@section('title', $ast->number)

@section('content')
    @livewire('asset.handover-detail', ['assetHandover' => $ast])
@endsection
