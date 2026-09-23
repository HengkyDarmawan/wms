{{--
    Foto item (11-master §3.4). Disimpan di disk privat per company dan
    disajikan lewat route berotorisasi, bukan URL publik.
--}}
<div class="card h-100">
    <div class="card-header"><strong>{{ __('Foto item') }}</strong></div>
    <div class="card-body">
        @if ($item->photo_path)
            <div class="border rounded p-2 mb-3 bg-white text-center">
                <img src="{{ route('files.item-photo', $item) }}" alt="{{ $item->name }}"
                     class="img-fluid" style="max-height: 12rem">
            </div>

            @can('update', $item)
                <form method="POST" action="{{ route('items.photo.destroy', $item) }}" class="mb-3">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-outline-danger btn-sm" type="submit">{{ __('Hapus foto') }}</button>
                </form>
            @endcan
        @else
            <p class="small text-muted">{{ __('Belum ada foto.') }}</p>
        @endif

        @can('update', $item)
            <form method="POST" action="{{ route('items.photo.store', $item) }}"
                  enctype="multipart/form-data" novalidate>
                @csrf

                <label class="form-label" for="item-photo">
                    {{ $item->photo_path ? __('Ganti foto') : __('Unggah foto') }} <span class="wajib">*</span>
                </label>
                <div class="d-flex gap-2">
                    <input class="form-control @error('photo') is-invalid @enderror"
                           type="file" id="item-photo" name="photo"
                           accept="image/png,image/jpeg,image/webp" required>
                    <button class="btn btn-primary" type="submit">{{ __('Simpan') }}</button>
                </div>
                @error('photo')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                <div class="form-text">{{ __('PNG, JPG, atau WEBP. Maksimum 5 MB.') }}</div>
            </form>
        @endcan
    </div>
</div>
