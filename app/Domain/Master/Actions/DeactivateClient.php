<?php

declare(strict_types=1);

namespace App\Domain\Master\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Master\Exceptions\MasterRuleException;
use App\Domain\Master\Models\Client;

/**
 * Permission: `client.deactivate`.
 *
 * P-03: klien tidak pernah dihapus. BR-MST-05: klien yang masih punya proyek
 * aktif tidak bisa dinonaktifkan. BR-GEN-11: alasan wajib, keterangan opsional.
 */
class DeactivateClient
{
    public function handle(Client $client, string $reasonCode, ?string $notes = null, ?User $actor = null): Client
    {
        if (trim($reasonCode) === '') {
            throw MasterRuleException::rule('BR-GEN-11', 'Alasan wajib dipilih.');
        }

        $aktif = $client->activeProjectCount();

        if ($aktif > 0) {
            throw MasterRuleException::rule(
                'BR-MST-05',
                'Klien masih punya '.$aktif.' proyek aktif. Tutup proyeknya dulu sebelum menonaktifkan klien.',
            );
        }

        $client->forceFill(['is_active' => false])->save();

        activity('master')
            ->performedOn($client)
            ->causedBy($actor)
            ->withProperties(['reason_code' => $reasonCode, 'notes' => $notes])
            ->log('Klien dinonaktifkan');

        return $client->refresh();
    }

    public function reactivate(Client $client, ?User $actor = null): Client
    {
        $client->forceFill(['is_active' => true])->save();

        activity('master')
            ->performedOn($client)
            ->causedBy($actor)
            ->log('Klien diaktifkan kembali');

        return $client->refresh();
    }
}
