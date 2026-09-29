<?php

declare(strict_types=1);

namespace App\Domain\Access\Actions;

use App\Domain\Access\Exceptions\AccessRuleException;
use App\Domain\Access\Models\ProjectTeamMember;
use App\Domain\Access\Models\User;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ReasonCode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `role.assign`.
 *
 * Mengakhiri penempatan di site lebih awal (A-337). BR-GEN-11: alasan wajib.
 * P-03: barisnya tidak dihapus — tanggal selesainya dimajukan dan `ended_at`
 * diisi, sehingga akses ke site berhenti sendiri lewat `RoleAssignment::valid()`
 * sementara akun dan akses lain orang itu tetap jalan (A-341).
 */
class EndProjectTeamMember
{
    public function handle(
        ProjectTeamMember $member,
        string $reasonCode,
        ?string $notes = null,
        ?string $endsOn = null,
        ?User $actor = null,
    ): ProjectTeamMember {
        if ($member->ended_at !== null) {
            throw AccessRuleException::rule('BR-GEN-11', 'Penempatan ini sudah diakhiri.');
        }

        $reasonCode = trim($reasonCode);

        if ($reasonCode === '') {
            throw AccessRuleException::rule('BR-GEN-11', 'Alasan wajib dipilih.');
        }

        $alasan = ReasonCode::query()->forContext(ReasonContext::Cancel)->where('code', $reasonCode)->first();

        try {
            $selesai = $endsOn === null || trim($endsOn) === ''
                ? now()->startOfDay()
                : Carbon::parse(trim($endsOn))->startOfDay();
        } catch (\Throwable) {
            throw AccessRuleException::rule('BR-GEN-11', 'Tanggal berlaku sampai tidak sah.');
        }

        if ($selesai->greaterThan($member->ends_on->startOfDay())) {
            $selesai = $member->ends_on->startOfDay();
        }

        DB::transaction(function () use ($member, $selesai, $alasan, $notes): void {
            $member->forceFill([
                'ends_on' => $selesai->toDateString(),
                'ended_at' => now(),
                'end_reason_code_id' => $alasan?->id,
                'end_notes' => $notes,
            ])->save();

            $member->assignments()->update(['valid_until' => $selesai->toDateString()]);
        });

        $member->user?->forgetPermissionCache();

        activity('access')
            ->performedOn($member->user)
            ->causedBy($actor)
            ->withProperties([
                'project' => $member->project?->code,
                'reason_code' => $reasonCode,
                'notes' => $notes,
                'sampai' => $selesai->toDateString(),
            ])
            ->log('Penempatan di site diakhiri');

        return $member->refresh();
    }
}
