<?php

declare(strict_types=1);

namespace App\Domain\Platform\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Platform\Enums\PaymentStatus;
use App\Domain\Platform\Enums\SubscriptionStatus;
use App\Domain\Platform\Exceptions\PlatformRuleException;
use App\Domain\Platform\Models\Company;
use App\Domain\Platform\Models\SubscriptionInvoice;
use App\Domain\Platform\Models\SubscriptionPayment;
use App\Domain\Platform\Support\PlatformAudit;
use App\Domain\Shared\Files\StoreUpload;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Permission: `billing.pay` — Admin Company mengunggah bukti transfer untuk
 * satu tagihan (alur 10 langkah 7, A-178). Boleh saat `suspended` (route
 * `billing.payment.store` dikecualikan dari gerbang hanya-baca, BR-SUB-02);
 * ditolak saat `terminated` (BR-SUB-03). Satu bukti menunggu per tagihan.
 * Berkas bukti (foto/tangkapan layar) disimpan di disk company.
 */
class SubmitSubscriptionPayment
{
    public function __construct(private readonly StoreUpload $upload) {}

    /** @param  array{amount?: mixed, paid_at?: mixed, notes?: mixed}  $data */
    public function handle(Company $company, SubscriptionInvoice $invoice, array $data, ?UploadedFile $proof, User $actor): SubscriptionPayment
    {
        $sub = $invoice->subscription;

        if ($sub === null || (int) $sub->company_id !== (int) $company->getTenantKey()) {
            abort(404);
        }

        if ($sub->status === SubscriptionStatus::Terminated) {
            throw PlatformRuleException::rule('BR-SUB-03', 'Langganan sudah diakhiri; pembayaran tidak diterima lagi.');
        }

        if (! $invoice->status->isPayable()) {
            throw PlatformRuleException::rule('BR-GEN-01', 'Tagihan '.$invoice->number.' tidak menunggu pembayaran ('.$invoice->status->label().').');
        }

        if ($invoice->pendingPayment() !== null) {
            throw PlatformRuleException::rule('BR-GEN-01', 'Bukti bayar tagihan ini masih menunggu verifikasi.');
        }

        $jumlah = is_numeric($data['amount'] ?? null) ? round((float) $data['amount'], 2) : 0.0;

        if ($jumlah <= 0) {
            throw PlatformRuleException::field('BR-GEN-11', 'amount', 'Jumlah transfer wajib diisi.');
        }

        $isiTanggal = trim((string) ($data['paid_at'] ?? ''));

        try {
            $tanggal = $isiTanggal === '' ? null : Carbon::parse($isiTanggal)->toDateString();
        } catch (\Throwable) {
            $tanggal = null;
        }

        if ($tanggal === null) {
            throw PlatformRuleException::field('BR-GEN-11', 'paid_at', 'Tanggal transfer wajib diisi.');
        }

        if ($tanggal > now()->toDateString()) {
            throw PlatformRuleException::field('BR-GEN-11', 'paid_at', 'Tanggal transfer tidak boleh di masa depan.');
        }

        if ($proof === null) {
            throw PlatformRuleException::field('BR-GEN-11', 'proof', 'Bukti transfer wajib diunggah.');
        }

        try {
            $path = $this->upload->handle($proof, 'billing', 'bukti-'.$invoice->id.'-'.Str::lower(Str::random(8)));
        } catch (\RuntimeException $e) {
            throw PlatformRuleException::field('NFR-14', 'proof', $e->getMessage());
        }

        $payment = SubscriptionPayment::create([
            'invoice_id' => $invoice->id,
            'uploaded_by' => $actor->id,
            'uploaded_by_name' => mb_substr($actor->name, 0, 100),
            'proof_path' => $path,
            'amount' => $jumlah,
            'paid_at' => $tanggal,
            'status' => PaymentStatus::Pending,
            'notes' => ($n = trim((string) ($data['notes'] ?? ''))) === '' ? null : mb_substr($n, 0, 255),
        ]);

        activity('billing')->causedBy($actor)
            ->withProperties(['invoice' => $invoice->number, 'amount' => $jumlah])
            ->log('Bukti bayar langganan diunggah');
        PlatformAudit::record('Bukti bayar langganan diunggah', $payment, null, ['company' => $company->code, 'invoice' => $invoice->number, 'by' => $actor->name]);

        return $payment;
    }
}
