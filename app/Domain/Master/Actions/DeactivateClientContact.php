<?php

declare(strict_types=1);

namespace App\Domain\Master\Actions;

use App\Domain\Access\Actions\DeactivateUser;
use App\Domain\Access\Models\User;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Exceptions\MasterRuleException;
use App\Domain\Master\Models\ClientContact;
use App\Domain\Master\Models\ReasonCode;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `client.deactivate`.
 *
 * P-03: PIC tidak pernah dihapus, hanya dinonaktifkan. BR-GEN-11: alasan wajib,
 * keterangan opsional. PIC yang punya akun portal boleh sekaligus dinonaktifkan
 * akunnya lewat `DeactivateUser` yang sudah ada (A-326).
 */
class DeactivateClientContact
{
    public function __construct(private readonly DeactivateUser $deactivateUser) {}

    public function handle(
        ClientContact $contact,
        string $reasonCode,
        ?string $notes = null,
        bool $nonaktifkanAkun = false,
        ?User $actor = null,
    ): ClientContact {
        $reasonCode = trim($reasonCode);

        if ($reasonCode === '') {
            throw MasterRuleException::rule('BR-GEN-11', 'Alasan wajib dipilih.');
        }

        $akun = $contact->portalUser;
        $ikutAkun = $nonaktifkanAkun && $akun !== null && $akun->is_active;
        $alasan = ReasonCode::query()->forContext(ReasonContext::Cancel)->where('code', $reasonCode)->value('label') ?? $reasonCode;

        DB::transaction(function () use ($contact, $actor, $ikutAkun, $akun, $alasan, $notes): void {
            $contact->forceFill(['is_active' => false, 'updated_by' => $actor?->id])->save();

            if ($ikutAkun) {
                $this->deactivateUser->handle($akun, $alasan, $notes, $actor);
            }
        });

        activity('master')
            ->performedOn($contact)
            ->causedBy($actor)
            ->withProperties(['reason_code' => $reasonCode, 'notes' => $notes, 'akun_dinonaktifkan' => $ikutAkun])
            ->log('PIC klien dinonaktifkan');

        return $contact->refresh();
    }

    public function reactivate(ClientContact $contact, ?User $actor = null): ClientContact
    {
        $contact->forceFill(['is_active' => true, 'updated_by' => $actor?->id])->save();

        activity('master')
            ->performedOn($contact)
            ->causedBy($actor)
            ->log('PIC klien diaktifkan kembali');

        return $contact->refresh();
    }
}
