<?php

declare(strict_types=1);

namespace App\Domain\Platform\Support;

use App\Domain\Notification\Support\Notifier;
use App\Domain\Platform\Models\Company;
use App\Domain\Platform\Models\Subscription;
use App\Domain\Platform\Models\SubscriptionInvoice;
use Illuminate\Support\Facades\Log;

/**
 * Pengingat tagihan langganan (Blueprint §10 "email untuk tagihan", A-202):
 * kejadian `subscription.billing` ke pemegang `billing.view` di database
 * company — lonceng + email bawaan. Dijalankan dari siklus harian pusat,
 * jadi berpindah ke konteks company dulu. Galat pengiriman hanya dicatat;
 * siklus langganan tidak pernah gagal karenanya.
 */
class BillingNotifier
{
    public function invoiceIssued(Company $company, SubscriptionInvoice $tagihan): void
    {
        $this->kirim($company, 'Tagihan langganan '.$tagihan->number.' terbit',
            'Periode '.$tagihan->period_start->format('d/m/Y').'–'.$tagihan->period_end->format('d/m/Y')
            .', jatuh tempo '.$tagihan->due_date->format('d/m/Y').'. Unggah bukti transfer di menu Tagihan langganan.',
            'subscription_invoice', (int) $tagihan->id);
    }

    public function statusChanged(Company $company, Subscription $sub, string $langkah): void
    {
        [$judul, $isi] = match ($langkah) {
            'past_due' => ['Langganan jatuh tempo', 'Tagihan belum dibayar. Masa tenggang sampai '.$sub->grace_ends_at?->format('d/m/Y').'; setelah itu data hanya bisa dibaca.'],
            'suspended' => ['Langganan ditangguhkan', 'Data hanya bisa dibaca sampai tagihan dibayar dan diverifikasi.'],
            default => [null, null],
        };

        if ($judul !== null) {
            $this->kirim($company, $judul, $isi, 'subscription_'.$langkah, (int) $sub->id);
        }
    }

    private function kirim(Company $company, string $judul, string $isi, string $jenisDokumen, int $idDokumen): void
    {
        $kirim = function () use ($judul, $isi, $jenisDokumen, $idDokumen): void {
            $notifier = app(Notifier::class);
            $notifier->send($notifier->recipients('billing.view'), 'subscription.billing', $judul, $isi,
                route('billing.index', absolute: false), $jenisDokumen, $idDokumen);
        };

        try {
            // Sudah di konteks company ini (mis. uji) → langsung; selain itu pindah konteks sementara.
            tenant()?->getTenantKey() === $company->getTenantKey() ? $kirim() : $company->run($kirim);
        } catch (\Throwable $e) {
            Log::warning('Pengingat tagihan gagal dikirim: '.$e->getMessage(), ['company' => $company->code]);
        }
    }
}
