{{-- BR-SUB-01/02: spanduk status langganan. --}}
@php($subscriptionStatus = request()->attributes->get('subscription_status'))

@if ($subscriptionStatus instanceof \App\Domain\Platform\Enums\SubscriptionStatus)
    @if ($subscriptionStatus === \App\Domain\Platform\Enums\SubscriptionStatus::PastDue)
        <div class="nx-subscription-banner alert alert-warning" role="status">
            <i class="bi bi-exclamation-triangle"></i>
            {{ __('Langganan jatuh tempo. Unggah bukti pembayaran sebelum masa tenggang habis agar akses tidak ditangguhkan.') }}
        </div>
    @elseif ($subscriptionStatus === \App\Domain\Platform\Enums\SubscriptionStatus::Suspended)
        <div class="nx-subscription-banner alert alert-danger" role="status">
            <i class="bi bi-lock"></i>
            {{ __('Langganan ditangguhkan: data hanya bisa dibaca sampai pembayaran diverifikasi.') }}
        </div>
    @elseif ($subscriptionStatus === \App\Domain\Platform\Enums\SubscriptionStatus::Terminated)
        <div class="nx-subscription-banner alert alert-dark" role="status">
            <i class="bi bi-archive"></i>
            {{ __('Langganan sudah diakhiri. Hanya ekspor data yang tersedia.') }}
        </div>
    @endif
@endif
