<?php

declare(strict_types=1);

namespace App\Domain\Platform\Support;

use App\Domain\Platform\Actions\VerifySubscriptionPayment;
use App\Domain\Platform\Enums\CompanyStatus;
use App\Domain\Platform\Enums\InvoiceStatus;
use App\Domain\Platform\Enums\SubscriptionStatus;
use App\Domain\Platform\Models\Company;
use App\Domain\Platform\Models\Subscription;
use App\Domain\Platform\Models\SubscriptionInvoice;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Siklus langganan harian (BR-SUB-01, A-12, alur 10, A-177):
 *
 * 1. H-7 sebelum akhir masa berjalan (akhir trial / periode) terbit tagihan
 *    periode berikutnya; jatuh tempo = akhir masa berjalan.
 * 2. Lewat jatuh tempo tanpa pembayaran terverifikasi → `past_due`, tenggang 7 hari.
 * 3. Tenggang habis → `suspended` (hanya-baca 30 hari).
 * 4. 30 hari ditangguhkan → `terminated`; data disimpan 90 hari (`purge_after`),
 *    company `terminated`. Database tidak pernah dihapus otomatis (P-03, A-179).
 *
 * Tagihan terbit, jatuh tempo, dan penangguhan diberitahukan ke company lewat
 * {@see BillingNotifier} (A-202).
 *
 * Pembayaran terverifikasi mengembalikan ke `active` lewat
 * {@see VerifySubscriptionPayment}.
 */
class SubscriptionLifecycle
{
    public const INVOICE_LEAD_DAYS = 7;

    public const GRACE_DAYS = 7;

    public const SUSPEND_DAYS = 30;

    public const RETENTION_DAYS = 90;

    public function __construct(
        private readonly InvoiceNumber $nomor,
        private readonly BillingNotifier $kabar,
    ) {}

    /** @return array<string, int> ringkasan per langkah */
    public function run(?CarbonInterface $sekarang = null): array
    {
        $sekarang ??= now();
        $hasil = ['invoiced' => 0, 'past_due' => 0, 'suspended' => 0, 'terminated' => 0];

        $langganan = Subscription::query()->with('company', 'plan')
            ->whereIn('id', Subscription::query()->selectRaw('max(id)')->groupBy('company_id'))
            ->where('status', '!=', SubscriptionStatus::Terminated->value)
            ->get();

        foreach ($langganan as $sub) {
            if ($sub->company === null || $sub->company->status === CompanyStatus::Provisioning) {
                continue;
            }

            [$tagihan, $langkah, $sub] = DB::connection('central')->transaction(function () use ($sub, $sekarang, &$hasil) {
                $sub = Subscription::query()->lockForUpdate()->findOrFail($sub->id);
                $tagihan = $this->terbitkan($sub, $sekarang);

                if ($tagihan !== null) {
                    $hasil['invoiced']++;
                }

                $langkah = $this->pindahStatus($sub, $sekarang);

                if ($langkah !== null) {
                    $hasil[$langkah]++;
                }

                return [$tagihan, $langkah, $sub];
            });

            // Pengingat ke company setelah commit (A-202).
            if ($tagihan !== null) {
                $this->kabar->invoiceIssued($sub->company, $tagihan);
            }

            if ($langkah !== null) {
                $this->kabar->statusChanged($sub->company, $sub, $langkah);
            }
        }

        return $hasil;
    }

    /** Tagihan periode berikutnya bila masa berjalan berakhir ≤ 7 hari lagi dan belum ada. */
    public function terbitkan(Subscription $sub, CarbonInterface $sekarang): ?SubscriptionInvoice
    {
        $akhir = $sub->periodEnd();

        if ($akhir === null || ! in_array($sub->status, [SubscriptionStatus::Trial, SubscriptionStatus::Active], true)) {
            return null;
        }

        if ($sekarang->copy()->startOfDay()->addDays(self::INVOICE_LEAD_DAYS)->lt($akhir->copy()->startOfDay())) {
            return null;
        }

        $mulai = $akhir->copy()->addDay()->startOfDay();

        $sudah = $sub->invoices()->where('period_start', $mulai->toDateString())
            ->where('status', '!=', InvoiceStatus::Void->value)->exists();

        if ($sudah) {
            return null;
        }

        $tagihan = SubscriptionInvoice::create([
            'subscription_id' => $sub->id,
            'number' => $this->nomor->next(),
            'period_start' => $mulai->toDateString(),
            'period_end' => $mulai->copy()->addMonthNoOverflow()->subDay()->toDateString(),
            'amount' => (float) ($sub->plan?->monthly_price ?? $sub->company?->plan?->monthly_price ?? 0),
            // Jaring pengaman: bila akhir periode sudah lewat saat terbit, jatuh
            // tempo diberi jeda yang sama dengan jeda terbit (A-195).
            'due_date' => ($akhir->copy()->startOfDay()->lt($sekarang->copy()->startOfDay())
                ? $sekarang->copy()->startOfDay()->addDays(self::INVOICE_LEAD_DAYS)
                : $akhir)->toDateString(),
            'status' => InvoiceStatus::Open,
        ]);

        PlatformAudit::record('Tagihan langganan terbit', $tagihan, null, ['company' => $sub->company?->code, 'due_date' => $tagihan->due_date->toDateString()]);

        return $tagihan;
    }

    private function pindahStatus(Subscription $sub, CarbonInterface $sekarang): ?string
    {
        $hari = $sekarang->copy()->startOfDay();

        if (in_array($sub->status, [SubscriptionStatus::Trial, SubscriptionStatus::Active], true)) {
            $tunggakan = $sub->invoices()->where('status', InvoiceStatus::Open->value)
                ->where('due_date', '<', $hari->toDateString())->orderBy('due_date')->first();

            if ($tunggakan === null) {
                return null;
            }

            $tunggakan->forceFill(['status' => InvoiceStatus::Overdue])->save();
            $sub->forceFill([
                'status' => SubscriptionStatus::PastDue,
                'grace_ends_at' => $tunggakan->due_date->copy()->addDays(self::GRACE_DAYS)->endOfDay(),
            ])->save();
            $this->catat($sub, 'Langganan jatuh tempo (tenggang '.self::GRACE_DAYS.' hari)');

            return 'past_due';
        }

        if ($sub->status === SubscriptionStatus::PastDue && $sub->grace_ends_at !== null && $sub->grace_ends_at->lt($sekarang)) {
            $sub->forceFill(['status' => SubscriptionStatus::Suspended, 'suspended_at' => $sekarang])->save();
            $this->catat($sub, 'Langganan ditangguhkan (hanya-baca)');

            return 'suspended';
        }

        if ($sub->status === SubscriptionStatus::Suspended && $sub->suspended_at !== null
            && $sub->suspended_at->copy()->addDays(self::SUSPEND_DAYS)->lt($sekarang)) {
            $sub->forceFill([
                'status' => SubscriptionStatus::Terminated,
                'terminated_at' => $sekarang,
                'purge_after' => $sekarang->copy()->addDays(self::RETENTION_DAYS)->toDateString(),
            ])->save();
            $sub->company?->forceFill(['status' => CompanyStatus::Terminated])->save();
            $this->catat($sub, 'Langganan diakhiri; data disimpan '.self::RETENTION_DAYS.' hari');

            return 'terminated';
        }

        return null;
    }

    private function catat(Subscription $sub, string $pesan): void
    {
        PlatformAudit::record($pesan, $sub, null, ['company' => $sub->company?->code, 'status' => $sub->status->value]);
    }
}
