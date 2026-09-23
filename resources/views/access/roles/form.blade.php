@extends('layouts.app')

@section('title', $roleId ? __('Ubah role') : __('Tambah role'))

@section('content')
    @livewire('access.role-form', ['roleId' => $roleId])
@endsection
