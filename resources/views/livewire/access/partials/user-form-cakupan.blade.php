{{-- ③ Pertanyaan sesuai peran (A-331). Tanpa tanggal: penempatan berbatas waktu = Tim site. --}}
@php($guide = \App\Domain\Access\Support\RoleGuide::class)

@if ($pertanyaan !== null)
    <div class="card mb-3">
        <div class="card-header"><strong>{{ __('3. Pertanyaan untuk peran ini') }}</strong></div>
        <div class="card-body">
            @if ($pertanyaan === $guide::GUDANG)
                <label class="form-label">
                    {{ __('Gudang tetap yang ia pegang') }} <span class="wajib">*</span>
                    <span class="text-muted small">{{ __('(boleh lebih dari satu)') }}</span>
                </label>
                <div class="row g-2 @error('gudangDipilih') border border-danger rounded py-2 @enderror">
                    @forelse ($gudangTetap as $gudang)
                        <div class="col-12 col-md-6 col-xl-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="gudang-{{ $gudang->id }}"
                                       value="{{ $gudang->id }}" wire:model="gudangDipilih">
                                <label class="form-check-label" for="gudang-{{ $gudang->id }}">
                                    {{ $gudang->code }} — {{ $gudang->name }}
                                </label>
                            </div>
                        </div>
                    @empty
                        <div class="col-12 small text-muted">{{ __('Belum ada gudang tetap. Buat gudang dulu.') }}</div>
                    @endforelse
                </div>
                @error('gudangDipilih')<div class="small text-danger mt-1">{{ $message }}</div>@enderror
                <div class="form-text">
                    {{ __('Hanya gudang tetap (pusat/cabang), tanpa tanggal. Penempatan di Gudang Site proyek diatur lewat Proyek › tab Tim site.') }}
                </div>

            @elseif ($pertanyaan === $guide::PROYEK)
                <label class="form-label">
                    {{ __('Proyek yang ia tangani') }} <span class="wajib">*</span>
                    <span class="text-muted small">{{ __('(boleh lebih dari satu)') }}</span>
                </label>
                <div class="row g-2 @error('proyekDipilih') border border-danger rounded py-2 @enderror">
                    @forelse ($projects as $proyek)
                        <div class="col-12 col-md-6 col-xl-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="proyek-{{ $proyek->id }}"
                                       value="{{ $proyek->id }}" wire:model="proyekDipilih">
                                <label class="form-check-label" for="proyek-{{ $proyek->id }}">
                                    {{ $proyek->code }} — {{ $proyek->name }}
                                </label>
                            </div>
                        </div>
                    @empty
                        <div class="col-12 small text-muted">{{ __('Belum ada proyek aktif.') }}</div>
                    @endforelse
                </div>
                @error('proyekDipilih')<div class="small text-danger mt-1">{{ $message }}</div>@enderror
                <div class="form-text">{{ __('Berlaku sampai dicabut, tanpa tanggal.') }}</div>

            @elseif ($pertanyaan === $guide::KLIEN)
                <div class="row g-3">
                    <div class="col-md-5">
                        <x-pilih model="clientId" id="clientId" live wajib :label="__('Klien')" :kosong="__('— pilih klien —')"
                                 :options="$clients->map(fn ($k) => ['value' => $k->id, 'text' => $k->code.' — '.$k->name])->all()" />
                    </div>

                    <div class="col-md-7">
                        <label class="form-label">
                            {{ __('Proyek awal yang boleh ia lihat') }} <span class="wajib">*</span>
                        </label>
                        <div class="row g-2 @error('proyekDipilih') border border-danger rounded py-2 @enderror">
                            @forelse ($proyekKlien as $proyek)
                                <div class="col-12 col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="kproyek-{{ $proyek->id }}"
                                               value="{{ $proyek->id }}" wire:model="proyekDipilih">
                                        <label class="form-check-label" for="kproyek-{{ $proyek->id }}">
                                            {{ $proyek->code }} — {{ $proyek->name }}
                                        </label>
                                    </div>
                                </div>
                            @empty
                                <div class="col-12 small text-muted">
                                    {{ $clientId === null ? __('Pilih kliennya dulu.') : __('Klien ini belum punya proyek aktif.') }}
                                </div>
                            @endforelse
                        </div>
                        @error('proyekDipilih')<div class="small text-danger mt-1">{{ $message }}</div>@enderror
                        <div class="form-text">
                            {{ __('Periodenya diatur di Proyek › tab Tim site; di sini hanya penempatan awalnya. Lebih cepat: Master data › Klien › tab PIC › Buat akun portal.') }}
                            <a href="{{ route('clients.index') }}">{{ __('Buka daftar klien') }}</a>
                        </div>
                    </div>
                </div>

            @else
                <p class="mb-0 text-muted">
                    <i class="bi bi-shield-check"></i>
                    {{ __('Cakupan otomatis: semua gudang & proyek. Tidak ada yang perlu dipilih.') }}
                </p>
            @endif
        </div>
    </div>
@endif
