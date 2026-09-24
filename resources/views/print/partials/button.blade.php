{{-- Tombol cetak PDF di tab baru (18 §6). Parameter: $jenis (DocumentTemplateType), $id, $teks opsional. --}}
<a class="btn btn-outline-secondary" target="_blank" rel="noopener"
   href="{{ route('print.document', ['type' => $jenis->routeSegment(), 'id' => $id]) }}">
    <i class="bi bi-printer"></i> {{ $teks ?? __('Cetak') }}
</a>
