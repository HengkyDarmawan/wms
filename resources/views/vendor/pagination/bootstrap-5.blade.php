{{--
    Paginasi halaman controller (Paginator::useBootstrapFive, D-05). Tampilan sama dengan
    resources/views/vendor/livewire/bootstrap.blade.php, tetapi memakai tautan biasa.
--}}
@if ($paginator->hasPages())
    <nav aria-label="{{ __('Navigasi halaman') }}">
        {{-- Layar sempit: sebelumnya / berikutnya saja --}}
        <div class="d-flex d-sm-none align-items-center justify-content-between gap-2">
            <span class="small text-muted">{{ __('Hal.') }} {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
            <ul class="pagination mb-0">
                @if ($paginator->onFirstPage())
                    <li class="page-item disabled" aria-disabled="true"><span class="page-link">{{ __('Sebelumnya') }}</span></li>
                @else
                    <li class="page-item"><a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">{{ __('Sebelumnya') }}</a></li>
                @endif

                @if ($paginator->hasMorePages())
                    <li class="page-item"><a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next">{{ __('Berikutnya') }}</a></li>
                @else
                    <li class="page-item disabled" aria-disabled="true"><span class="page-link">{{ __('Berikutnya') }}</span></li>
                @endif
            </ul>
        </div>

        <div class="d-none d-sm-flex flex-wrap align-items-center justify-content-between gap-2">
            <p class="small text-muted mb-0">
                {{ __('Menampilkan') }}
                <span class="fw-semibold">{{ $paginator->firstItem() }}</span>–<span class="fw-semibold">{{ $paginator->lastItem() }}</span>
                {{ __('dari') }}
                <span class="fw-semibold">{{ $paginator->total() }}</span>
                {{ __('data') }}
            </p>

            <ul class="pagination mb-0">
                @if ($paginator->onFirstPage())
                    <li class="page-item disabled" aria-disabled="true" aria-label="{{ __('Sebelumnya') }}">
                        <span class="page-link" aria-hidden="true">&lsaquo;</span>
                    </li>
                @else
                    <li class="page-item">
                        <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('Sebelumnya') }}">&lsaquo;</a>
                    </li>
                @endif

                @foreach ($elements as $element)
                    @if (is_string($element))
                        <li class="page-item disabled" aria-disabled="true"><span class="page-link">{{ $element }}</span></li>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <li class="page-item active" aria-current="page"><span class="page-link">{{ $page }}</span></li>
                            @else
                                <li class="page-item"><a class="page-link" href="{{ $url }}">{{ $page }}</a></li>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                @if ($paginator->hasMorePages())
                    <li class="page-item">
                        <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('Berikutnya') }}">&rsaquo;</a>
                    </li>
                @else
                    <li class="page-item disabled" aria-disabled="true" aria-label="{{ __('Berikutnya') }}">
                        <span class="page-link" aria-hidden="true">&rsaquo;</span>
                    </li>
                @endif
            </ul>
        </div>
    </nav>
@endif
