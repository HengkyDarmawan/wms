<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Domain\Platform\Actions\VerifySubscriptionPayment;
use App\Domain\Platform\Enums\PaymentStatus;
use App\Domain\Platform\Exceptions\PlatformRuleException;
use App\Domain\Platform\Models\PlatformUser;
use App\Domain\Platform\Models\SubscriptionPayment;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifikasi bukti bayar langganan oleh Super Admin (alur 10 langkah 8,
 * A-178). Bukti dibaca dari disk company pengunggah.
 */
class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $status = (string) $request->query('status', PaymentStatus::Pending->value);

        return view('platform.payments.index', [
            'payments' => SubscriptionPayment::query()
                ->with('invoice.subscription.company', 'verifier:id,name')
                ->when($status !== '', fn ($q) => $q->where('status', $status))
                ->latest('id')->paginate(25)->withQueryString(),
            'statuses' => collect(PaymentStatus::cases())->mapWithKeys(fn (PaymentStatus $s) => [$s->value => $s->label()])->all(),
            'status' => $status,
        ]);
    }

    public function verify(Request $request, int $payment, VerifySubscriptionPayment $action): RedirectResponse
    {
        try {
            $action->verify(SubscriptionPayment::query()->findOrFail($payment), $this->admin($request));
        } catch (PlatformRuleException $e) {
            return back()->withErrors(['platform' => $e->getMessage()])->with('ruleCode', $e->rule);
        }

        return back()->with('status', __('Pembayaran diverifikasi; langganan aktif.'));
    }

    public function reject(Request $request, int $payment, VerifySubscriptionPayment $action): RedirectResponse
    {
        try {
            $action->reject(SubscriptionPayment::query()->findOrFail($payment), (string) $request->input('reject_reason'), $this->admin($request));
        } catch (PlatformRuleException $e) {
            return back()->withErrors($e->fieldErrors ?: ['platform' => $e->getMessage()])->with('ruleCode', $e->rule);
        }

        return back()->with('status', __('Bukti bayar ditolak.'));
    }

    public function proof(int $payment): Response
    {
        $bukti = SubscriptionPayment::query()->with('invoice.subscription.company')->findOrFail($payment);
        $company = $bukti->invoice?->subscription?->company;

        abort_if($company === null || $bukti->proof_path === null, 404);

        [$isi, $mime] = $company->run(function () use ($bukti) {
            $disk = Storage::disk('local');

            return $disk->exists($bukti->proof_path) ? [$disk->get($bukti->proof_path), $disk->mimeType($bukti->proof_path)] : [null, null];
        });

        abort_if($isi === null, 404);

        return response($isi, 200, ['Content-Type' => $mime ?: 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function admin(Request $request): PlatformUser
    {
        /** @var PlatformUser $admin */
        $admin = $request->user('platform');

        return $admin;
    }
}
