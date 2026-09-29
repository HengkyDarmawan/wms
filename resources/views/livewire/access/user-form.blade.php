<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ $user ? __('Ubah pengguna') : __('Tambah pengguna') }}</h1>
            <p class="text-muted mb-0">
                {{ __('Field bertanda') }} <span class="wajib">*</span> {{ __('wajib diisi.') }}
            </p>
        </div>
        <a class="btn btn-outline-secondary" href="{{ route('users.index') }}">{{ __('Kembali ke daftar') }}</a>
    </div>

    <form wire:submit="save">
        {{-- ① Data diri --}}
        <div class="card mb-3">
            <div class="card-header"><strong>{{ __('1. Data diri') }}</strong></div>
            <div class="card-body row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="name">{{ __('Nama') }} <span class="wajib">*</span></label>
                    <input class="form-control @error('name') is-invalid @enderror" id="name" type="text"
                           wire:model="name" maxlength="100" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="email">{{ __('Email') }} <span class="wajib">*</span></label>
                    <input class="form-control @error('email') is-invalid @enderror" id="email" type="email"
                           wire:model="email" maxlength="150" required @disabled($emailTerkunci)>
                    @if ($emailTerkunci)
                        <div class="form-text">{{ __('Email tidak bisa diubah setelah undangan diterima.') }}</div>
                    @endif
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="phone">{{ __('No. WhatsApp') }}</label>
                    <input class="form-control @error('phone') is-invalid @enderror" id="phone" type="text"
                           wire:model="phone" maxlength="20" placeholder="0812-3456-7890">
                    <div class="form-text">{{ __('Dipakai untuk mengirim tautan undangan lewat WhatsApp.') }}</div>
                    @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        @if ($penugasanSiteSaja)
            <div class="alert alert-info">
                {{ __('Seluruh cakupan pengguna ini berasal dari Tim site di halaman proyek, jadi tidak diubah dari sini. Buka detail pengguna › tab Penugasan site.') }}
            </div>
        @else
            @include('livewire.access.partials.user-form-peran')
            @include('livewire.access.partials.user-form-cakupan')
        @endif

        @include('livewire.access.partials.user-form-lanjutan')

        @unless ($user)
            @include('livewire.access.partials.user-form-akses')
        @endunless

        <div class="d-flex gap-2 mt-3">
            <button class="btn btn-primary" type="submit">
                <span wire:loading.remove wire:target="save">{{ __('Simpan') }}</span>
                <span wire:loading wire:target="save">{{ __('Menyimpan…') }}</span>
            </button>
            <a class="btn btn-outline-secondary" href="{{ route('users.index') }}">{{ __('Batal') }}</a>
        </div>
    </form>
</div>
