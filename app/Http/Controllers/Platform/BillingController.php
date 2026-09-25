<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Domain\Platform\Actions\SubmitSubscriptionPayment;
use App\Domain\Platform\Exceptions\PlatformRuleException;
use App\Domain\Platform\Models\Company;
use App\Domain\Platform\Models\Subscription;
use App\Domain\Platform\Models\SubscriptionInvoice;
use App\Domain\Platform\Models\SubscriptionPayment;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Layar *Tagihan langganan* Admin Company (Blueprint §14, alur 10 langkah
 * 6–7, 17-platform-login §6.2): status langganan, tagihan, unggah bukti
 * transfer (POST), dan lihat bukti yang sudah diunggah.
 */
class BillingController extends Controller
{
    public function index(): View
    {
        $this->authorize('billing.view');

        $company = $this->company();
        $sub = Subscription::query()->with('plan')->where('company_id', $company->getTenantKey())->latest('id')->first();

        return view('platform.billing.index', [
            'company' => $company,
            'subscription' => $sub,
            'invoices' => $sub === null ? collect() : SubscriptionInvoice::query()
                ->whereIn('subscription_id', Subscription::query()->where('company_id', $company->getTenantKey())->select('id'))
                ->with(['payments' => fn ($q) => $q->latest('id')])
                ->orderByDesc('period_start')->get(),
        ]);
    }

    public function store(Request $request, int $invoice, SubmitSubscriptionPayment $action): RedirectResponse
    {
        $this->authorize('billing.pay');

        try {
            $payment = $action->handle(
                $this->company(),
                $this->invoice($invoice),
                $request->only(['amount', 'paid_at', 'notes']),
                $request->file('proof'),
                $request->user(),
            );
        } catch (PlatformRuleException $e) {
            return back()->withInput()->withErrors($e->fieldErrors ?: ['proof' => $e->getMessage()])->with('ruleCode', $e->rule);
        }

        return redirect()->route('billing.index')
            ->with('status', __('Bukti bayar :nomor terkirim; menunggu verifikasi Super Admin.', ['nomor' => $payment->invoice?->number]));
    }

    public function proof(int $payment): Response
    {
        $this->authorize('billing.view');

        $bukti = SubscriptionPayment::query()->with('invoice.subscription')->findOrFail($payment);

        abort_unless((int) $bukti->invoice?->subscription?->company_id === (int) $this->company()->getTenantKey(), 404);
        abort_if($bukti->proof_path === null || ! Storage::disk('local')->exists($bukti->proof_path), 404);

        return Storage::disk('local')->response($bukti->proof_path);
    }

    private function invoice(int $id): SubscriptionInvoice
    {
        $tagihan = SubscriptionInvoice::query()->with('subscription')->findOrFail($id);

        abort_unless((int) $tagihan->subscription?->company_id === (int) $this->company()->getTenantKey(), 404);

        return $tagihan;
    }

    private function company(): Company
    {
        /** @var Company $company */
        $company = tenant();

        return $company;
    }
}
