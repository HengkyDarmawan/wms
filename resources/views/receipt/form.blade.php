@extends('layouts.app')

@section('title', $grn ? $grn->number : __('Penerimaan baru'))

@section('content')
    @livewire('receipt.receipt-form', $grn ? ['goodsReceipt' => $grn] : [])
@endsection
