{{--
    Paginasi sederhana komponen Livewire (simplePaginate / cursorPaginate), D-05.
    Menimpa view bawaan Livewire: teks Indonesia, tanpa margin ganda di card-footer.
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
@endphp

<div>
    @if ($paginator->hasPages())
        <nav aria-label="{{ __('Navigasi halaman') }}">
            <ul class="pagination mb-0">
                @if ($paginator->onFirstPage())
                    <li class="page-item disabled" aria-disabled="true"><span class="page-link">{{ __('Sebelumnya') }}</span></li>
                @elseif (method_exists($paginator, 'getCursorName'))
                    {{-- Di halaman kosong kursor sebelumnya null: muat ulang kursor sekarang, seperti bawaan Livewire. --}}
                    @php($previousCursor = $paginator->previousCursor() ?? $paginator->cursor())
                    <li class="page-item">
                        <button dusk="previousPage" type="button" class="page-link" wire:key="cursor-{{ $paginator->getCursorName() }}-{{ $previousCursor?->encode() }}" wire:click="setPage('{{ $previousCursor?->encode() }}','{{ $paginator->getCursorName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled">{{ __('Sebelumnya') }}</button>
                    </li>
                @else
                    <li class="page-item">
                        <button type="button" dusk="previousPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}" class="page-link" wire:click="previousPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled">{{ __('Sebelumnya') }}</button>
                    </li>
                @endif

                @if (! $paginator->hasMorePages())
                    <li class="page-item disabled" aria-disabled="true"><span class="page-link">{{ __('Berikutnya') }}</span></li>
                @elseif (method_exists($paginator, 'getCursorName'))
                    @php($nextCursor = $paginator->nextCursor() ?? $paginator->cursor())
                    <li class="page-item">
                        <button dusk="nextPage" type="button" class="page-link" wire:key="cursor-{{ $paginator->getCursorName() }}-{{ $nextCursor?->encode() }}" wire:click="setPage('{{ $nextCursor?->encode() }}','{{ $paginator->getCursorName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled">{{ __('Berikutnya') }}</button>
                    </li>
                @else
                    <li class="page-item">
                        <button type="button" dusk="nextPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}" class="page-link" wire:click="nextPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled">{{ __('Berikutnya') }}</button>
                    </li>
                @endif
            </ul>
        </nav>
    @endif
</div>
