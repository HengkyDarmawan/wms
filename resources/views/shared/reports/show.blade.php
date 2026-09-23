@extends('layouts.app')

@section('title', $laporan->title())

@section('content')
    @livewire('shared.report-viewer', ['reportKey' => $laporan->key()])
@endsection
