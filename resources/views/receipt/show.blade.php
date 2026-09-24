@extends('layouts.app')

@section('title', $grn->number)

@section('content')
    @livewire('receipt.receipt-detail', ['goodsReceipt' => $grn])
@endsection
