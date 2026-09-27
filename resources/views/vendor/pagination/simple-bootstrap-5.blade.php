{{-- Paginasi sederhana halaman controller (simplePaginate), D-05; teks Indonesia. --}}
@if ($paginator->hasPages())
    <nav aria-label="{{ __('Navigasi halaman') }}">
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
    </nav>
@endif
