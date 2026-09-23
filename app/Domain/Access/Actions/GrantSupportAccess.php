<?php

declare(strict_types=1);

namespace App\Domain\Access\Actions;

use App\Domain\Access\Exceptions\AccessRuleException;
use App\Domain\Access\Models\User;
use App\Domain\Platform\Models\Company;
use App\Domain\Platform\Models\SupportAccess;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Permission: `support_access.grant`.
 *
 * A-27 / BR-SUB-04: Admin Company memberi Super Admin izin berperiode untuk
 * membuka data operasional. Dicatat di database pusat dan di audit log tenant.
 */
class GrantSupportAccess
{
    public function handle(
        Company $company,
        int $platformUserId,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        string $reason,
        User $grantedBy,
    ): SupportAccess {
        $reason = trim($reason);

        if ($reason === '') {
            throw AccessRuleException::rule('BR-GEN-11', 'Alasan wajib diisi.');
        }

        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            throw new AccessRuleException('Tanggal selesai harus setelah tanggal mulai.');
        }

        $maxDays = (int) config('access.support_access.max_days', 7);

        if ($startsAt->diffInDays($endsAt, absolute: true) > $maxDays) {
            throw AccessRuleException::rule(
                'BR-SUB-04',
                'Akses dukungan paling lama '.$maxDays.' hari.',
            );
        }

        $access = SupportAccess::create([
            'company_id' => $company->getTenantKey(),
            'platform_user_id' => $platformUserId,
            'granted_by_tenant_user_id' => $grantedBy->id,
            // BR-GEN-07: disimpan UTC apa pun zona waktu yang dipakai layar.
            'starts_at' => Carbon::parse($startsAt)->utc(),
            'ends_at' => Carbon::parse($endsAt)->utc(),
            'reason' => $reason,
        ]);

        activity('access')
            ->causedBy($grantedBy)
            ->withProperties([
                'support_access_id' => $access->id,
                'starts_at' => $access->starts_at->toIso8601String(),
                'ends_at' => $access->ends_at->toIso8601String(),
                'reason' => $reason,
            ])
            ->log('Akses dukungan diberikan');

        return $access;
    }

    public function revoke(SupportAccess $access, User $actor): SupportAccess
    {
        $access->forceFill(['revoked_at' => now()])->save();

        activity('access')
            ->causedBy($actor)
            ->withProperties(['support_access_id' => $access->id])
            ->log('Akses dukungan dicabut');

        return $access->refresh();
    }
}
