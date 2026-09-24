@extends('layouts.app')

@section('title', $isu->number)

@section('content')
    @livewire('issue.issue-detail', ['materialIssue' => $isu])
@endsection
