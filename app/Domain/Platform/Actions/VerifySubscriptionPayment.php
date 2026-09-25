<?php

declare(strict_types=1);

namespace App\Domain\Platform\Actions;

use App\Domain\Platform\Enums\InvoiceStatus;
use App\Domain\Platform\Enums\PaymentStatus;
use App\Domain\Platform\Enums\SubscriptionStatus;
use App\Domain\Platform\Exceptions\PlatformRuleException;
use App\Domain\Platform\Models\PlatformUser;
use App\Domain\Platform\Models\Subscription;
use App\Domain\Platform\Models\SubscriptionPayment;
use App\Domain\Platform\Support\PlatformAudit;
use Illuminate\Support\Facades\DB;

/**
 * Super Admin memverifikasi atau menolak bukti bayar (alur 10 langkah 8–14,
 * A-178). Diverifikasi: tagihan lunas, langganan `active` sampai akhir periode
 * tagihan, tenggang & penangguhan dihapus. Ditolak: alasan wajib, tagihan
 * tetap menunggu dan Admin Company boleh mengunggah ulang. Penangguhan manual
 * company oleh Super Admin tidak ikut dicabut (A-179).
 */
class VerifySubscriptionPayment
{
    public function verify(SubscriptionPayment $payment, PlatformUser $actor): SubscriptionPayment
    {
        return DB::connection('central')->transaction(function () use ($payment, $actor) {
            $payment = $this->menunggu($payment);
            $tagihan = $payment->invoice;
            $sub = Subscription::query()->lockForUpdate()->findOrFail($tagihan->subscription_id);

            if ($sub->status === SubscriptionStatus::Terminated) {
                throw PlatformRuleException::rule('BR-SUB-03', 'Langganan sudah diakhiri; pembayaran tidak bisa mengaktifkan kembali.');
            }

            $payment->forceFill(['status' => PaymentStatus::Verified, 'verified_by' => $actor->id, 'verified_at' => now()])->save();
            $tagihan->forceFill(['status' => InvoiceStatus::Paid, 'paid_at' => now()])->save();

            // Periode tidak mundur bila tagihan lama dibayar belakangan. Bila periode
            // tagihan sudah lewat saat diverifikasi (bayar setelah ditangguhkan),
            // periode satu bulan dihitung ulang mulai hari ini (A-195) supaya
            // tagihan berikutnya tidak langsung jatuh tempo.
            $hariIni = now()->startOfDay();
            [$mulai, $akhir] = match (true) {
                $sub->current_period_end !== null && $sub->current_period_end->gte($tagihan->period_end) => [$sub->current_period_start, $sub->current_period_end],
                $tagihan->period_end->lt($hariIni) => [$hariIni->copy(), $hariIni->copy()->addMonthNoOverflow()->subDay()],
                default => [$tagihan->period_start, $tagihan->period_end],
            };

            $sub->forceFill([
                'status' => SubscriptionStatus::Active,
                'current_period_start' => $mulai,
                'current_period_end' => $akhir,
                'grace_ends_at' => null,
                'suspended_at' => null,
            ])->save();

            // Tunggakan lain yang lebih lama tetap menahan status (langkah harian berikutnya).
            PlatformAudit::record('Pembayaran langganan diverifikasi', $payment, $actor, [
                'company' => $sub->company?->code, 'invoice' => $tagihan->number, 'period_end' => $sub->current_period_end?->toDateString(),
            ]);

            return $payment->refresh();
        });
    }

    public function reject(SubscriptionPayment $payment, string $reason, PlatformUser $actor): SubscriptionPayment
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw PlatformRuleException::field('BR-GEN-11', 'reject_reason', 'Alasan penolakan wajib diisi.');
        }

        return DB::connection('central')->transaction(function () use ($payment, $reason, $actor) {
            $payment = $this->menunggu($payment);
            $payment->forceFill([
                'status' => PaymentStatus::Rejected,
                'verified_by' => $actor->id,
                'verified_at' => now(),
                'reject_reason' => mb_substr($reason, 0, 255),
            ])->save();

            PlatformAudit::record('Pembayaran langganan ditolak', $payment, $actor, ['invoice' => $payment->invoice?->number, 'reason' => $reason]);

            return $payment->refresh();
        });
    }

    private function menunggu(SubscriptionPayment $payment): SubscriptionPayment
    {
        $payment = SubscriptionPayment::query()->with('invoice')->lockForUpdate()->findOrFail($payment->id);

        if ($payment->status !== PaymentStatus::Pending) {
            throw PlatformRuleException::rule('BR-GEN-01', 'Bukti bayar ini sudah diputus ('.$payment->status->label().').');
        }

        return $payment;
    }
}
