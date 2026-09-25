{{-- BR-SUB-01/02/03: spanduk status langganan; BR-SUB-04: sesi akses dukungan. --}}
@php($subscriptionStatus = request()->attributes->get('subscription_status'))
@php($supportAccess = request()->attributes->get('support_access'))

@if ($supportAccess)
    <div class="nx-subscription-banner alert alert-info" role="status">
        <i class="bi bi-life-preserver"></i>
        {{ __('Mode akses dukungan (hanya-baca) oleh :admin, berlaku sampai :waktu.', [
            'admin' => session(\App\Domain\Access\Actions\StartSupportSession::SESSION_ADMIN, 'Super Admin'),
            'waktu' => $supportAccess->ends_at->timezone(tenant()?->timezone ?? 'Asia/Jakarta')->format('d/m/Y H:i'),
        ]) }}
    </div>
@endif

@if ($subscriptionStatus instanceof \App\Domain\Platform\Enums\SubscriptionStatus)
    @php($tautanTagihan = auth()->user()?->can('billing.view') ? route('billing.index') : null)
    @if ($subscriptionStatus === \App\Domain\Platform\Enums\SubscriptionStatus::PastDue)
        <div class="nx-subscription-banner alert alert-warning" role="status">
            <i class="bi bi-exclamation-triangle"></i>
            {{ __('Langganan jatuh tempo. Unggah bukti pembayaran sebelum masa tenggang habis agar akses tidak ditangguhkan.') }}
            @if ($tautanTagihan) <a class="alert-link" href="{{ $tautanTagihan }}">{{ __('Buka tagihan') }}</a> @endif
        </div>
    @elseif ($subscriptionStatus === \App\Domain\Platform\Enums\SubscriptionStatus::Suspended)
        <div class="nx-subscription-banner alert alert-danger" role="status">
            <i class="bi bi-lock"></i>
            {{ __('Langganan ditangguhkan: data hanya bisa dibaca sampai pembayaran diverifikasi.') }}
            @if ($tautanTagihan) <a class="alert-link" href="{{ $tautanTagihan }}">{{ __('Buka tagihan') }}</a> @endif
        </div>
    @elseif ($subscriptionStatus === \App\Domain\Platform\Enums\SubscriptionStatus::Terminated)
        <div class="nx-subscription-banner alert alert-dark" role="status">
            <i class="bi bi-archive"></i>
            {{ __('Langganan sudah diakhiri. Hanya ekspor data (Laporan) yang tersedia.') }}
        </div>
    @endif
@endif
