@extends('layouts.auth')

@section('title', __('Bukti terima'))

@section('content')
    {{-- A-231: langkah 2 halaman penerima bertoken — isi per baris, foto wajib bila rusak, tanda tangan. --}}
    <h1 class="h4 mb-1">{{ __('Bukti terima') }} {{ $sj->number }}</h1>
    <p class="text-muted mb-3">
        {{ __('Dari') }} {{ $sj->warehouse?->name }}
        @if ($sj->destinationProject) · {{ __('untuk') }} {{ $sj->destinationProject->name }} @endif
    </p>

    @if ($errors->has('terima'))
        <div class="alert alert-danger">{{ $errors->first('terima') }}</div>
    @endif

    <form method="post" action="{{ route('terima.store', $token) }}" enctype="multipart/form-data">
        @csrf
        <div class="mb-3">
            <label class="form-label" for="received_by_name">{{ __('Nama penerima') }} <span class="wajib">*</span></label>
            <input class="form-control @error('received_by_name') is-invalid @enderror" id="received_by_name" name="received_by_name"
                   type="text" maxlength="100" value="{{ old('received_by_name') }}" required>
            @error('received_by_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <p class="small text-muted mb-2">{{ __('Jumlah baik + rusak + kurang harus sama dengan yang dikirim. Foto wajib bila ada yang rusak.') }}</p>
        @foreach (['qty_good', 'damage_photo_path'] as $f)
            @error($f) <div class="text-danger small mb-2">{{ $message }}</div> @enderror
        @endforeach

        @foreach ($sj->lines as $l)
            @php($id = $l->id)
            <div class="border rounded p-2 mb-2">
                <div class="fw-semibold">{{ $l->pickTaskLine?->item?->code }} <span class="fw-normal text-muted small">{{ $l->pickTaskLine?->item?->name }}</span></div>
                <div class="small text-muted mb-2">
                    {{ __('Dikirim') }} {{ rtrim(rtrim(number_format((float) $l->qty_shipped, 4, ',', '.'), '0'), ',') }} {{ $l->pickTaskLine?->item?->baseUom?->code }}
                    @if ($l->pickTaskLine?->serial) · {{ __('Serial') }} {{ $l->pickTaskLine->serial->serial_no }} @endif
                    @if ($l->pickTaskLine?->piece) · {{ __('Potongan') }} {{ $l->pickTaskLine->piece->piece_no }} @endif
                </div>
                <div class="row g-2">
                    @if ($l->pickTaskLine?->serial_id !== null || $l->pickTaskLine?->piece_id !== null)
                        {{-- A-244: satu unit — baik, rusak, atau kurang seluruhnya. --}}
                        <div class="col-12">
                            <label class="form-label small mb-0" for="kondisi-{{ $id }}">{{ __('Kondisi unit') }}</label>
                            <select class="form-select form-select-sm" id="kondisi-{{ $id }}" name="lines[{{ $id }}][condition]">
                                @foreach (\App\Domain\Shipment\Enums\PodUnitCondition::cases() as $k)
                                    <option value="{{ $k->value }}" @selected(old('lines.'.$id.'.condition', 'good') === $k->value)>{{ $k->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                    @else
                    <div class="col-4">
                        <label class="form-label small mb-0" for="baik-{{ $id }}">{{ __('Baik') }}</label>
                        <input class="form-control form-control-sm" id="baik-{{ $id }}" type="number" step="any" min="0"
                               name="lines[{{ $id }}][qty_good]" value="{{ old('lines.'.$id.'.qty_good', (string) (float) $l->qty_shipped) }}">
                    </div>
                    <div class="col-4">
                        <label class="form-label small mb-0" for="rusak-{{ $id }}">{{ __('Rusak') }}</label>
                        <input class="form-control form-control-sm" id="rusak-{{ $id }}" type="number" step="any" min="0"
                               name="lines[{{ $id }}][qty_damaged]" value="{{ old('lines.'.$id.'.qty_damaged', '0') }}">
                    </div>
                    <div class="col-4">
                        <label class="form-label small mb-0" for="kurang-{{ $id }}">{{ __('Kurang') }}</label>
                        <input class="form-control form-control-sm" id="kurang-{{ $id }}" type="number" step="any" min="0"
                               name="lines[{{ $id }}][qty_missing]" value="{{ old('lines.'.$id.'.qty_missing', '0') }}">
                    </div>
                    @endif
                    <div class="col-12">
                        <label class="form-label small mb-0" for="foto-{{ $id }}">{{ __('Foto kerusakan') }} <span class="text-muted">({{ __('wajib bila rusak') }})</span></label>
                        <input class="form-control form-control-sm" id="foto-{{ $id }}" type="file" accept="image/jpeg,image/png,image/webp" capture="environment" name="photos[{{ $id }}]">
                    </div>
                </div>
            </div>
        @endforeach

        <div class="mb-3">
            <label class="form-label" for="photo">{{ __('Foto serah terima') }} <span class="text-muted small">({{ __('opsional') }})</span></label>
            <input class="form-control @error('photo') is-invalid @enderror" id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" capture="environment">
            @error('photo') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3" data-signature>
            <label class="form-label">{{ __('Tanda tangan penerima') }} <span class="text-muted small">({{ __('gambar dengan jari') }})</span></label>
            <canvas class="border rounded w-100 bg-white" height="160" aria-label="{{ __('Kanvas tanda tangan') }}"></canvas>
            <input type="hidden" name="signature" data-signature-target>
            <button class="btn btn-sm btn-outline-secondary mt-1" type="button" data-signature-clear>{{ __('Hapus tanda tangan') }}</button>
        </div>

        <div class="mb-3">
            <label class="form-label" for="notes">{{ __('Catatan') }}</label>
            <input class="form-control" id="notes" name="notes" type="text" maxlength="255" value="{{ old('notes') }}" placeholder="{{ __('Opsional') }}">
        </div>

        <button class="btn btn-success w-100" type="submit">{{ __('Simpan bukti terima') }}</button>
    </form>
@endsection
