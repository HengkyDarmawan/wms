{{--
    Paket dari tabel pusat `plans` (aktif saja). Harga di sini adalah harga
    langganan platform (D-02), bukan nilai barang (D-07). Harga 0 = belum
    ditetapkan → "Hubungi kami" (O-08, A-222).
--}}
<section class="lp-section lp-section-alt" id="paket" aria-labelledby="plansTitle">
    <div class="container">
        <div class="lp-section-head">
            <span class="lp-eyebrow">{{ __('Paket') }}</span>
            <h2 class="lp-title" id="plansTitle">{{ __('Langganan bulanan flat per company') }}</h2>
            <p class="lp-lead">{{ __('Tidak dihitung per user atau per gudang. Tambah tim, gudang, dan proyek sesuai kebutuhan — biayanya tetap.') }}</p>
        </div>

        @if ($plans->isEmpty())
            <div class="lp-plan text-center mx-auto" style="max-width: 36rem">
                <h3>{{ __('Paket sedang disiapkan') }}</h3>
                <p class="lp-muted">{{ __('Hubungi kami untuk penawaran yang sesuai dengan jumlah gudang dan proyek Anda.') }}</p>
                <a class="lp-btn lp-btn-primary align-self-center" href="{{ $demoHref }}">{{ __('Hubungi kami') }}</a>
            </div>
        @else
            <div class="row g-4 justify-content-center">
                @foreach ($plans as $plan)
                    @php
                        $harga = (float) $plan->monthly_price;
                        $gb = $plan->storage_quota_mb ? $plan->storage_quota_mb / 1024 : null;
                        $planHref = 'mailto:'.$salesEmail.'?subject='.rawurlencode(__('Paket :plan — :app', ['plan' => $plan->name, 'app' => $appName]));
                    @endphp
                    <div class="col-md-6 col-lg-4">
                        <article class="lp-plan {{ $plans->count() === 1 ? 'is-featured' : '' }}" data-plan="{{ $plan->code }}">
                            <h3>{{ $plan->name }}</h3>
                            <p class="lp-price">
                                @if ($harga > 0)
                                    Rp {{ number_format($harga, 0, ',', '.') }} <small>/ {{ __('bulan per company') }}</small>
                                @else
                                    {{ __('Hubungi kami') }}
                                @endif
                            </p>
                            <ul class="lp-check-list">
                                <li><i class="bi bi-check2-circle" aria-hidden="true"></i><span>{{ __('Semua fitur WMS di setiap paket') }}</span></li>
                                <li><i class="bi bi-check2-circle" aria-hidden="true"></i><span>{{ __('User, gudang, dan proyek tanpa batas') }}</span></li>
                                <li><i class="bi bi-check2-circle" aria-hidden="true"></i><span>{{ __('Portal klien & PWA untuk lapangan') }}</span></li>
                                @if ($gb)
                                    <li><i class="bi bi-check2-circle" aria-hidden="true"></i><span>{{ __('Penyimpanan berkas :gb GB', ['gb' => rtrim(rtrim(number_format($gb, 1, ',', '.'), '0'), ',')]) }}</span></li>
                                @endif
                                @if ((int) $plan->trial_days > 0)
                                    <li><i class="bi bi-check2-circle" aria-hidden="true"></i><span>{{ __('Trial :n hari', ['n' => (int) $plan->trial_days]) }}</span></li>
                                @endif
                            </ul>
                            <a class="lp-btn {{ $harga > 0 ? 'lp-btn-primary' : 'lp-btn-ghost' }}" href="{{ $planHref }}">
                                {{ $harga > 0 ? __('Minta demo') : __('Hubungi kami') }}
                            </a>
                        </article>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
