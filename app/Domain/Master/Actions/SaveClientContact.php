<?php

declare(strict_types=1);

namespace App\Domain\Master\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Master\Exceptions\MasterRuleException;
use App\Domain\Master\Models\Client;
use App\Domain\Master\Models\ClientContact;
use App\Domain\Master\Models\Project;
use App\Domain\Shared\Messaging\PhoneNumber;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `client.create` / `client.update`.
 *
 * PIC Klien (A-326). Nomor WA dibakukan `62…` seperti isian driver (A-315),
 * dan proyek yang diurus hanya boleh proyek milik klien itu sendiri.
 * BR-GEN-11 nama wajib; BR-GEN-05 audit log.
 */
class SaveClientContact
{
    /** @param  array<string, mixed>  $attributes */
    public function handle(Client $client, ?ClientContact $contact, array $attributes, ?User $actor = null): ClientContact
    {
        $baru = $contact === null || ! $contact->exists;

        if (! $baru && (int) $contact->client_id !== (int) $client->id) {
            throw MasterRuleException::rule('BR-GEN-09', 'PIC itu milik klien lain.');
        }

        $nama = trim((string) ($attributes['name'] ?? ''));

        if ($nama === '') {
            throw MasterRuleException::fields(['name' => 'Nama PIC wajib diisi.'], 'BR-GEN-11');
        }

        $email = $this->email($attributes['email'] ?? null);

        // PIC yang sudah punya akun portal selalu punya email: akun itu dibuat darinya.
        if (! $baru && $contact->hasPortalAccount() && $email === null) {
            throw MasterRuleException::fields(
                ['email' => 'PIC ini punya akun portal, jadi emailnya tidak boleh dikosongkan.'],
                'BR-GEN-11',
            );
        }

        $data = [
            'client_id' => $client->id,
            'name' => $nama,
            'position' => $this->kosongJadiNull($attributes['position'] ?? null),
            'phone' => $this->nomor($attributes['phone'] ?? null),
            'email' => $email,
            'notes' => $this->kosongJadiNull($attributes['notes'] ?? null),
            'updated_by' => $actor?->id,
        ];

        $proyek = $this->proyek($client, $attributes['projects'] ?? []);

        $contact = DB::transaction(function () use ($contact, $baru, $data, $proyek, $actor): ClientContact {
            if ($baru) {
                $contact = ClientContact::create($data + ['is_active' => true, 'created_by' => $actor?->id]);
            } else {
                $contact->fill($data)->save();
            }

            $contact->projects()->sync($proyek);

            return $contact;
        });

        activity('master')
            ->performedOn($contact)
            ->causedBy($actor)
            ->log($baru ? 'PIC klien dibuat' : 'PIC klien diubah');

        return $contact->refresh();
    }

    /**
     * Hanya proyek milik klien itu; id lain ditolak, bukan didiamkan.
     *
     * @return array<int, int>
     */
    private function proyek(Client $client, mixed $pilihan): array
    {
        $ids = collect(is_array($pilihan) ? $pilihan : [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $sah = Project::query()->whereIn('id', $ids->all())->where('client_id', $client->id)->pluck('id')
            ->map(fn ($id) => (int) $id);

        if ($sah->count() !== $ids->count()) {
            throw MasterRuleException::fields(
                ['projects' => 'Hanya proyek milik klien ini yang bisa dipilih.'],
                'BR-GEN-09',
            );
        }

        return $sah->all();
    }

    /** Nomor WA dibakukan `62…`; yang jelas bukan nomor HP ditolak per kolom (A-315). */
    private function nomor(mixed $value): ?string
    {
        $teks = trim((string) ($value ?? ''));

        if ($teks === '') {
            return null;
        }

        return PhoneNumber::normalize($teks)
            ?? throw MasterRuleException::fields(
                ['phone' => 'Nomor WA tidak dikenali. Contoh: 0812-3456-7890.'],
                'BR-GEN-11',
            );
    }

    private function email(mixed $value): ?string
    {
        $teks = mb_strtolower(trim((string) ($value ?? '')));

        if ($teks === '') {
            return null;
        }

        if (! filter_var($teks, FILTER_VALIDATE_EMAIL)) {
            throw MasterRuleException::fields(['email' => 'Email tidak sah.'], 'BR-GEN-11');
        }

        return $teks;
    }

    private function kosongJadiNull(mixed $value): ?string
    {
        $teks = trim((string) ($value ?? ''));

        return $teks === '' ? null : $teks;
    }
}
