{{-- A-324 / P-03: nonaktif, bukan hapus; alasan wajib. Variabel: $label, $catatan, $alasan. --}}
<hr>
<div class="fw-semibold mb-1">{{ $label }}</div>
<div class="d-flex flex-wrap gap-2 align-items-start">
    <select class="form-select form-select-sm w-auto" x-model="f.alasan" aria-label="{{ __('Alasan nonaktif') }}">
        <option value="">{{ __('Alasan…') }}</option>
        @foreach ($alasan as $kode => $teks) <option value="{{ $kode }}">{{ $teks }}</option> @endforeach
    </select>
    <button class="btn btn-sm btn-outline-danger" type="button" x-on:click="nonaktifkan()"><i class="bi bi-slash-circle"></i> {{ __('Nonaktifkan') }}</button>
</div>
<div class="text-muted mt-1">{{ $catatan }}</div>
