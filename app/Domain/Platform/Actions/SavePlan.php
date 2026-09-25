<?php

declare(strict_types=1);

namespace App\Domain\Platform\Actions;

use App\Domain\Platform\Exceptions\PlatformRuleException;
use App\Domain\Platform\Models\Plan;
use App\Domain\Platform\Models\PlatformUser;
use App\Domain\Platform\Support\PlatformAudit;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Super Admin mengatur paket langganan (Blueprint §14: paket bulanan flat per
 * company, durasi trial bawaan A-11). Paket tidak dihapus, hanya dinonaktifkan
 * (P-03); harga baru berlaku untuk tagihan berikutnya (A-177).
 */
class SavePlan
{
    /** @param  array<string, mixed>  $data */
    public function handle(?Plan $plan, array $data, PlatformUser $actor): Plan
    {
        $data['code'] = mb_strtolower(trim((string) ($data['code'] ?? $plan?->code ?? '')));

        $validator = Validator::make($data, [
            'code' => ['required', 'regex:/^[a-z0-9_-]{2,30}$/', Rule::unique('central.plans', 'code')->ignore($plan?->id)],
            'name' => ['required', 'string', 'max:80'],
            'monthly_price' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'trial_days' => ['required', 'integer', 'min:0', 'max:90'],
            'wa_quota' => ['nullable', 'integer', 'min:0'],
            'storage_quota_mb' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ], [], [
            'code' => 'Kode', 'name' => 'Nama', 'monthly_price' => 'Harga bulanan', 'trial_days' => 'Durasi trial',
            'wa_quota' => 'Kuota WA', 'storage_quota_mb' => 'Kuota berkas',
        ]);

        if ($validator->fails()) {
            throw new PlatformRuleException('BR-GEN-11', (string) $validator->errors()->first(), collect($validator->errors()->messages())->map(fn ($m) => $m[0])->all());
        }

        $v = $validator->validated();
        $v['is_active'] = (bool) ($data['is_active'] ?? true);

        $plan ??= new Plan;
        $baru = ! $plan->exists;
        $plan->fill($v)->save();

        PlatformAudit::record($baru ? 'Paket dibuat' : 'Paket diubah', $plan, $actor, $plan->only(['code', 'monthly_price', 'trial_days', 'is_active']));

        return $plan->refresh();
    }
}
