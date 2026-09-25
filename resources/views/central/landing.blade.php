{{--
    Landing page produk di domain pusat (docs/wms/30-landing-page.md).
    Nama & logo sementara sampai O-07; harga paket dari tabel `plans` (O-08, A-222).
--}}
@extends('layouts.landing')

@php
    $appName = config('app.name');
    $demoHref = 'mailto:'.$salesEmail.'?subject='.rawurlencode(__('Minta demo :app', ['app' => $appName]));
    $navLinks = [
        '#fitur' => __('Fitur'),
        '#cara-kerja' => __('Cara kerja'),
        '#untuk-siapa' => __('Untuk siapa'),
        '#paket' => __('Paket'),
        '#faq' => __('FAQ'),
    ];
@endphp

@section('title', __(':app — WMS khusus material proyek', ['app' => $appName]))
@section('description', __('Warehouse management system untuk penyedia material & alat proyek: stok per proyek dan Gudang Site, konversi potongan, pemakaian material, aset dipinjamkan, dan portal klien.'))

@section('body')
    @include('central.landing.nav')

    <main id="konten">
        @include('central.landing.hero')
        @include('central.landing.goals')
        @include('central.landing.features')
        @include('central.landing.conversion')
        @include('central.landing.steps')
        @include('central.landing.roles')
        @include('central.landing.plans')
        @include('central.landing.enter')
        @include('central.landing.faq')
        @include('central.landing.cta')
    </main>

    @include('central.landing.footer')
@endsection
