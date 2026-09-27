{{--
    Paginasi komponen Livewire (config livewire.pagination_theme = bootstrap, D-05).
    Menimpa view bawaan Livewire: teks Indonesia dan tanpa margin ganda di card-footer.
    Pasangan untuk halaman controller: resources/views/vendor/pagination/bootstrap-5.blade.php.
--}}
@php
if (! isset($scrollTo)) {
    $scrollTo = 'body';
}

$scrollIntoViewJsSnippet = ($scrollTo !== false)
    ? <<<JS
       (\$el.closest('{$scrollTo}') || document.querySelector('{$scrollTo}')).scrollIntoView()
    JS
    : '';

$pageName = $paginator->getPageName();
$dusk = $pageName === 'page' ? '' : '.'.$pageName;
@endphp

<div>
    @if ($paginator->hasPages())
        <nav aria-label="{{ __('Navigasi halaman') }}">
            {{-- Layar sempit: sebelumnya / berikutnya saja --}}
            <div class="d-flex d-sm-none align-items-center justify-content-between gap-2">
                <span class="small text-muted">{{ __('Hal.') }} {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
                <ul class="pagination mb-0">
                    @if ($paginator->onFirstPage())
                        <li class="page-item disabled" aria-disabled="true"><span class="page-link">{{ __('Sebelumnya') }}</span></li>
                    @else
                        <li class="page-item">
                            <button type="button" dusk="previousPage{{ $dusk }}" class="page-link" wire:click="previousPage('{{ $pageName }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled">{{ __('Sebelumnya') }}</button>
                        </li>
                    @endif

                    @if ($paginator->hasMorePages())
                        <li class="page-item">
                            <button type="button" dusk="nextPage{{ $dusk }}" class="page-link" wire:click="nextPage('{{ $pageName }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled">{{ __('Berikutnya') }}</button>
                        </li>
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
                            <button type="button" dusk="previousPage{{ $dusk }}" class="page-link" wire:click="previousPage('{{ $pageName }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled" aria-label="{{ __('Sebelumnya') }}">&lsaquo;</button>
                        </li>
                    @endif

                    @foreach ($elements as $element)
                        @if (is_string($element))
                            <li class="page-item disabled" aria-disabled="true"><span class="page-link">{{ $element }}</span></li>
                        @endif

                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                @if ($page == $paginator->currentPage())
                                    <li class="page-item active" wire:key="paginator-{{ $pageName }}-page-{{ $page }}" aria-current="page"><span class="page-link">{{ $page }}</span></li>
                                @else
                                    <li class="page-item" wire:key="paginator-{{ $pageName }}-page-{{ $page }}"><button type="button" class="page-link" wire:click="gotoPage({{ $page }}, '{{ $pageName }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}">{{ $page }}</button></li>
                                @endif
                            @endforeach
                        @endif
                    @endforeach

                    @if ($paginator->hasMorePages())
                        <li class="page-item">
                            <button type="button" dusk="nextPage{{ $dusk }}" class="page-link" wire:click="nextPage('{{ $pageName }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled" aria-label="{{ __('Berikutnya') }}">&rsaquo;</button>
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
</div>
