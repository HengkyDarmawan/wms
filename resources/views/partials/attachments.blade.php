{{--
    Kartu lampiran foto (A-238). Parameter:
    $judul, $lampiran (koleksi Attachment), $aksi (URL POST atau null bila tidak boleh unggah),
    $field (nama input berkas), $kosong (teks bila belum ada).
--}}
<div class="card mb-3">
    <div class="card-header"><strong>{{ $judul }}</strong> <span class="small text-muted">{{ __('jpg/png/webp, maks. 5 MB') }}</span></div>
    <div class="card-body">
        @if ($lampiran->isEmpty())
            <p class="text-muted small mb-0">{{ $kosong }}</p>
        @else
            <div class="d-flex flex-wrap gap-2">
                @foreach ($lampiran as $a)
                    <a href="{{ route('attachments.show', $a) }}" target="_blank" rel="noopener" title="{{ $a->original_name }} · {{ $a->uploader?->name }} · {{ $a->created_at?->lokal()->format('d/m/Y H:i') }}">
                        <img src="{{ route('attachments.show', $a) }}" alt="{{ $a->original_name ?? __('Foto') }}" class="rounded border" style="width: 96px; height: 96px; object-fit: cover;" loading="lazy">
                    </a>
                @endforeach
            </div>
        @endif
    </div>
    @if ($aksi)
        <form class="card-footer d-flex flex-wrap align-items-start gap-2" method="POST" action="{{ $aksi }}" enctype="multipart/form-data">
            @csrf
            <div class="flex-grow-1">
                <input class="form-control @error($field) is-invalid @enderror" name="{{ $field }}" type="file" accept="image/jpeg,image/png,image/webp" capture="environment" required aria-label="{{ $judul }}">
                @error($field) <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <button class="btn btn-outline-primary" type="submit">{{ __('Unggah foto') }}</button>
        </form>
    @endif
</div>
