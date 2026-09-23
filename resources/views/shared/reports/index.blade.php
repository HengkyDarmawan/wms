@extends('layouts.app')

@section('title', __('Laporan'))

@section('content')
    <div class="mb-3">
        <h1 class="h3 mb-1">{{ __('Laporan') }}</h1>
        <p class="text-muted mb-0">{{ __('Laporan yang boleh Anda buka. Setiap laporan bisa diekspor ke Excel.') }}</p>
    </div>

    <div class="row g-3">
        @forelse ($laporan as $item)
            <div class="col-md-6 col-xl-4">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <h2 class="h6 mb-2">{{ $item->title() }}</h2>
                        <p class="text-muted small flex-grow-1">{{ $item->description() }}</p>
                        <a class="btn btn-outline-primary btn-sm align-self-start"
                           href="{{ route('reports.show', $item->key()) }}">{{ __('Buka') }}</a>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="card"><div class="card-body text-muted">{{ __('Belum ada laporan yang boleh Anda buka.') }}</div></div>
            </div>
        @endforelse
    </div>
@endsection
