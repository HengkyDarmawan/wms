@extends('layouts.app')

@section('title', __('Ubah').' '.$isu->number)

@section('content')
    @livewire('issue.issue-form', ['materialIssue' => $isu])
@endsection
