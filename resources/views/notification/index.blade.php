@extends('layouts.app')

@section('title', __('Notifikasi'))

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Notifikasi') }}</h1>
            <p class="text-muted mb-0">{{ __('Kejadian yang menunggu perhatian Anda. Atur kanal per kejadian di Preferensi.') }}</p>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="{{ route('notifications.preferences') }}">{{ __('Preferensi') }}</a>
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <button class="btn btn-outline-primary" type="submit">{{ __('Tandai semua dibaca') }}</button>
            </form>
        </div>
    </div>


    <div class="card">
        <ul class="list-group list-group-flush">
            @forelse ($notifications as $n)
                <li class="list-group-item d-flex justify-content-between align-items-start gap-2 {{ $n->read_at ? '' : 'bg-body-tertiary' }}">
                    <div>
                        <div class="{{ $n->read_at ? '' : 'fw-semibold' }}">{{ $n->title }}</div>
                        @if ($n->body) <div class="small text-muted">{{ $n->body }}</div> @endif
                        <div class="small text-muted">{{ $n->created_at?->lokal()->format('d/m/Y H:i') }}</div>
                    </div>
                    <form method="POST" action="{{ route('notifications.open', $n->id) }}">
                        @csrf
                        <button class="btn btn-sm btn-outline-secondary" type="submit">{{ $n->url ? __('Buka') : __('Tandai dibaca') }}</button>
                    </form>
                </li>
            @empty
                <li class="list-group-item text-muted">{{ __('Belum ada notifikasi.') }}</li>
            @endforelse
        </ul>
        @if ($notifications->hasPages()) <div class="card-footer">{{ $notifications->links() }}</div> @endif
    </div>
@endsection
