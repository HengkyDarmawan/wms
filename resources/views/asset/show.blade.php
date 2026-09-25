@extends('layouts.app')

@section('title', $aset->serial_no)

@section('content')
    @livewire('asset.asset-detail', ['serial' => $aset])
@endsection
