{{-- A-418: Coba demo — masuk sekali klik per peran; hanya bila WMS_DEMO_LOGIN menyala. --}}
@php($demo = \App\Domain\Access\Support\DemoLogin::pilihan())
@if ($demo !== [])
    <div class="mt-4" data-coba-demo>
        <div class="d-flex align-items-center gap-2 text-muted small mb-2">
            <hr class="flex-grow-1 my-0"><span>{{ __('Coba demo (tanpa password)') }}</span><hr class="flex-grow-1 my-0">
        </div>
        <div class="row g-2">
            @foreach ($demo as $d)
                <div class="col-6">
                    <form method="POST" action="{{ route('demo-login', $d['peran']) }}">
                        @csrf
                        <button class="btn btn-outline-primary w-100 h-100 text-start py-2" type="submit" data-demo-peran="{{ $d['peran'] }}">
                            <span class="fw-semibold d-block">{{ __($d['label']) }}</span>
                            <span class="small text-muted d-block text-truncate">{{ $d['nama'] }}</span>
                        </button>
                    </form>
                </div>
            @endforeach
        </div>
    </div>
@endif
