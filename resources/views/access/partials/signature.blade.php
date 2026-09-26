{{--
    Unggah tanda tangan (10-access §6.2).

    Berkas disimpan di disk privat per company dan hanya bisa dibuka lewat
    route berotorisasi, tidak lewat URL publik. Legalitas tanda tangan digital
    masih menunggu keputusan pemilik produk (O-13); sampai itu diputuskan, ini
    hanya gambar yang ditempel pada dokumen cetak.
--}}
<div class="card mb-3">
    <div class="card-body">
        <h2 class="h6 text-uppercase text-muted mb-3">{{ __('Tanda tangan') }}</h2>

        @if ($adaTandaTangan)
            <div class="border rounded p-2 mb-3 bg-white" style="max-width: 18rem">
                <img src="{{ route('files.signature', $user) }}" alt="{{ __('Tanda tangan Anda') }}"
                     class="img-fluid" style="max-height: 6rem">
            </div>

            <form method="POST" action="{{ route('profile.signature.destroy') }}" class="mb-3">
                @csrf
                @method('DELETE')
                <button class="btn btn-outline-danger btn-sm" type="submit">{{ __('Hapus tanda tangan') }}</button>
            </form>
        @else
            <p class="small text-muted">
                {{ __('Belum ada tanda tangan. Unggah gambar tanda tangan Anda untuk ditempel pada dokumen cetak.') }}
            </p>
        @endif

        <form method="POST" action="{{ route('profile.signature') }}" enctype="multipart/form-data" novalidate>
            @csrf

            <label class="form-label" for="signature">
                {{ $adaTandaTangan ? __('Ganti tanda tangan') : __('Unggah tanda tangan') }} <span class="wajib">*</span>
            </label>
            <div class="d-flex gap-2">
                <input class="form-control @error('signature') is-invalid @enderror"
                       type="file" id="signature" name="signature" accept="image/png,image/jpeg,image/webp" required>
                <button class="btn btn-primary" type="submit">{{ __('Simpan') }}</button>
            </div>
            @error('signature')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            <div class="form-text">{{ __('PNG, JPG, atau WEBP. Maksimum 20 MB; dikecilkan otomatis, PNG tetap PNG.') }}</div>
        </form>
    </div>
</div>
