@extends('layouts.app')

@section('title', $rule ? __('Ubah aturan approval') : __('Aturan approval baru'))

@section('content')
    @livewire('approval.rule-form', ['rule' => $rule])
@endsection
