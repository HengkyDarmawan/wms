<?php

declare(strict_types=1);

namespace App\Domain\Master\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Master\Exceptions\MasterRuleException;
use App\Domain\Master\Models\Client;
use App\Domain\Master\Support\MasterCode;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `client.create` / `client.update`.
 * BR-MST-01 kode huruf besar & tidak bisa diubah; BR-GEN-05 audit log.
 */
class SaveClient
{
    /** @param  array<string, mixed>  $attributes */
    public function handle(?Client $client, array $attributes, ?User $actor = null): Client
    {
        $baru = $client === null || ! $client->exists;

        $nama = trim((string) ($attributes['name'] ?? ''));

        if ($nama === '') {
            throw MasterRuleException::rule('BR-GEN-11', 'Nama klien wajib diisi.');
        }

        $kode = MasterCode::resolve($client, (string) ($attributes['code'] ?? ''), 'klien');

        $bentrok = Client::query()->where('code', $kode)
            ->when(! $baru, fn ($q) => $q->whereKeyNot($client->getKey()))
            ->exists();

        if ($bentrok) {
            throw MasterRuleException::rule('BR-MST-01', 'Kode klien "'.$kode.'" sudah dipakai.');
        }

        $data = [
            'code' => $kode,
            'name' => $nama,
            'tax_id' => $this->kosongJadiNull($attributes['tax_id'] ?? null),
            'address' => $this->kosongJadiNull($attributes['address'] ?? null),
            'contact_name' => $this->kosongJadiNull($attributes['contact_name'] ?? null),
            'phone' => $this->kosongJadiNull($attributes['phone'] ?? null),
            'email' => $this->kosongJadiNull($attributes['email'] ?? null),
        ];

        $client = DB::transaction(function () use ($client, $baru, $data): Client {
            if ($baru) {
                return Client::create($data + ['is_active' => true]);
            }

            $client->fill($data)->save();

            return $client;
        });

        activity('master')
            ->performedOn($client)
            ->causedBy($actor)
            ->log($baru ? 'Klien dibuat' : 'Klien diubah');

        return $client->refresh();
    }

    private function kosongJadiNull(mixed $value): ?string
    {
        $teks = trim((string) ($value ?? ''));

        return $teks === '' ? null : $teks;
    }
}
