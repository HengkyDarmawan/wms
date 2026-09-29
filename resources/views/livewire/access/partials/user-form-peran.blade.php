{{-- ② Kartu peran (A-331): satu kalimat penjelasan per peran, pilih satu. --}}
@php($guide = \App\Domain\Access\Support\RoleGuide::class)

<div class="card mb-3">
    <div class="card-header">
        <strong>{{ __('2. Peran') }}</strong> <span class="wajib">*</span>
        <span class="text-muted small ms-2">{{ __('Pilih satu. Peran tambahan ada di Pengaturan lanjutan.') }}</span>
    </div>
    <div class="card-body">
        @error('peranUtama')<div class="alert alert-danger py-2 small">{{ $message }}</div>@enderror

        <div class="row g-2">
            @foreach ($roles as $role)
                @php($terpilih = (string) $role->id === $peranUtama)
                <div class="col-12 col-md-6 col-xl-4">
                    <button class="card h-100 w-100 text-start {{ $terpilih ? 'border-primary border-2' : '' }}"
                            type="button" wire:click="pilihPeran({{ $role->id }})"
                            aria-pressed="{{ $terpilih ? 'true' : 'false' }}">
                        <div class="card-body py-2">
                            <div class="fw-semibold">
                                <i class="bi {{ $terpilih ? 'bi-record-circle' : 'bi-circle' }}"></i>
                                <i class="bi {{ $guide::ikon($role) }}"></i>
                                {{ $role->name }}
                                @if ($role->is_client_role)
                                    <span class="badge text-bg-light border">{{ __('portal') }}</span>
                                @endif
                            </div>
                            <div class="small text-muted">{{ $guide::penjelasan($role) }}</div>
                        </div>
                    </button>
                </div>
            @endforeach
        </div>
    </div>
</div>
