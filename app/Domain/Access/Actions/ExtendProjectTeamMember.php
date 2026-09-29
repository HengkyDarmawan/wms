<?php

declare(strict_types=1);

namespace App\Domain\Access\Actions;

use App\Domain\Access\Exceptions\AccessRuleException;
use App\Domain\Access\Models\ProjectTeamMember;
use App\Domain\Access\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `role.assign` — atau Kepala Gudang Gudang Site proyek itu (A-342).
 *
 * Memperpanjang penempatan di site (A-337). Hanya menyentuh penugasan role yang
 * dibuat keanggotaan ini; penugasan tetap milik orang itu tidak ikut berubah.
 * Pengingat H-7 direset supaya periode baru diingatkan lagi pada waktunya.
 */
class ExtendProjectTeamMember
{
    public function handle(ProjectTeamMember $member, string $endsOn, ?string $notes = null, ?User $actor = null): ProjectTeamMember
    {
        if ($member->ended_at !== null) {
            throw AccessRuleException::rule('BR-GEN-11', 'Penempatan ini sudah diakhiri, jadi tidak bisa diperpanjang.');
        }

        try {
            $selesai = Carbon::parse(trim($endsOn))->startOfDay();
        } catch (\Throwable) {
            throw AccessRuleException::rule('BR-GEN-11', 'Tanggal selesai tidak sah.');
        }

        if (! $selesai->greaterThan($member->ends_on->startOfDay())) {
            throw AccessRuleException::rule(
                'BR-GEN-11',
                'Tanggal selesai baru harus lebih jauh dari '.$member->ends_on->format('d/m/Y').'.',
            );
        }

        $lama = $member->ends_on->toDateString();

        DB::transaction(function () use ($member, $selesai): void {
            $member->forceFill([
                'ends_on' => $selesai->toDateString(),
                'reminded_at' => null,
            ])->save();

            $member->assignments()->update(['valid_until' => $selesai->toDateString()]);
        });

        $member->user?->forgetPermissionCache();

        activity('access')
            ->performedOn($member->user)
            ->causedBy($actor)
            ->withProperties([
                'project' => $member->project?->code,
                'dari' => $lama,
                'sampai' => $selesai->toDateString(),
                'notes' => $notes,
            ])
            ->log('Penempatan di site diperpanjang');

        return $member->refresh();
    }
}
