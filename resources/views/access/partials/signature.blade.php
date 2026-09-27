{{--
    Unggah atau gambar tanda tangan (10-access §6.2, A-264).

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

        {{-- A-264: tanda tangan dibuat sendiri di kanvas (jari, pena, atau mouse). --}}
        <form method="POST" action="{{ route('profile.signature') }}" class="mt-3" novalidate>
            @csrf
            <div data-signature>
                <label class="form-label">{{ __('Atau gambar tanda tangan di sini') }}</label>
                <canvas class="border rounded w-100 bg-white" height="160" aria-label="{{ __('Kanvas tanda tangan') }}"></canvas>
                <input type="hidden" name="signature_data" data-signature-target>
                <div class="d-flex gap-2 mt-1">
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-signature-clear>{{ __('Hapus gambar') }}</button>
                    <button class="btn btn-sm btn-primary" type="submit">{{ __('Simpan gambar tanda tangan') }}</button>
                </div>
            </div>
            @error('signature_data')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            <div class="form-text">{{ __('Tanda tangan ini ditempel di kotak tanda tangan dokumen cetak bersama QR segel yang bisa diverifikasi.') }}</div>
        </form>
    </div>
</div>
