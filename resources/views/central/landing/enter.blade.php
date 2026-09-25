{{--
    "Masuk ke company Anda" (A-221). Formulir GET ke /masuk yang hanya
    mengalihkan ke halaman masuk di subdomain company; tanpa JavaScript tetap
    berjalan. Alpine menambah pratinjau alamat dan validasi di sisi klien.
    Login Super Admin sengaja tidak ditautkan dari halaman publik.
--}}
@php
    $oldCompany = (string) old('company', '');
    $oldAs = old('as') === 'portal' ? 'portal' : 'team';
@endphp
<section class="lp-section pt-0 lp-section-alt" id="masuk" aria-labelledby="enterTitle">
    <div class="container">
        <div class="lp-enter mx-auto" style="max-width: 48rem"
             x-data="companyEnter(@js($centralDomain), @js($companyPort), @js($oldCompany), @js($oldAs), @js($errors->has('company')))">
            <div class="row g-4 align-items-center">
                <div class="col-md-5">
                    <span class="lp-eyebrow">{{ __('Sudah berlangganan?') }}</span>
                    <h2 class="h3 fw-bold mb-2" id="enterTitle">{{ __('Masuk ke company Anda') }}</h2>
                    <p class="lp-muted mb-0">{{ __('Setiap company punya alamat sendiri. Ketik alamat company Anda, lalu lanjutkan ke halaman masuk.') }}</p>
                </div>
                <div class="col-md-7">
                    <form method="get" action="{{ route('central.enter') }}" novalidate
                          @submit="if (!valid) { $event.preventDefault(); $refs.company.focus(); }">
                        <label class="form-label fw-semibold" for="enterCompany">{{ __('Alamat company') }} <span class="text-danger" aria-hidden="true">*</span></label>
                        <div class="input-group has-validation">
                            <input type="text" class="form-control @error('company') is-invalid @enderror" id="enterCompany" name="company"
                                   x-ref="company" x-model="company" value="{{ $oldCompany }}"
                                   placeholder="{{ __('nama-company') }}" required maxlength="63"
                                   pattern="[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?"
                                   autocomplete="organization" autocapitalize="none" spellcheck="false" inputmode="url"
                                   aria-describedby="enterPreview @error('company') enterError @enderror"
                                   @input="touched = true" :class="{ 'is-invalid': invalid }">
                            <span class="input-group-text" title=".{{ $centralDomain }}">.{{ $centralDomain }}</span>
                            <div class="invalid-feedback" id="enterError" role="alert">
                                @error('company')
                                    {{ $message }}
                                @else
                                    {{ __('Alamat company hanya huruf kecil, angka, dan tanda hubung.') }}
                                @enderror
                            </div>
                        </div>

                        <fieldset class="mt-3">
                            <legend class="form-label fw-semibold fs-6 mb-1">{{ __('Masuk sebagai') }}</legend>
                            <div class="d-flex flex-wrap gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="as" id="enterTeam" value="team" x-model="as" @checked($oldAs === 'team')>
                                    <label class="form-check-label" for="enterTeam">{{ __('Tim company') }}</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="as" id="enterPortal" value="portal" x-model="as" @checked($oldAs === 'portal')>
                                    <label class="form-check-label" for="enterPortal">{{ __('Klien (portal)') }}</label>
                                </div>
                            </div>
                        </fieldset>

                        <p class="lp-preview mt-3 mb-3" id="enterPreview" aria-live="polite">
                            <span class="visually-hidden">{{ __('Tujuan:') }}</span>
                            <span x-text="preview">{{ __('nama-company') }}.{{ $centralDomain }}{{ $companyPort !== '' ? ':'.$companyPort : '' }}/login</span>
                        </p>

                        <button type="submit" class="lp-btn lp-btn-primary w-100">
                            {{ __('Lanjut ke halaman masuk') }} <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
