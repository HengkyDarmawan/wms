<?php

declare(strict_types=1);

namespace App\Domain\Master\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Master\Enums\ProjectStatus;
use App\Domain\Master\Exceptions\MasterRuleException;
use App\Domain\Master\Models\Client;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Support\MasterCode;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `project.create` / `project.update`.
 *
 * BR-MST-04: Proyek Internal tidak boleh punya klien; proyek biasa wajib klien.
 * A-06: satu Proyek Internal dipakai konversi dan peminjaman non-klien.
 */
class SaveProject
{
    /** @param  array<string, mixed>  $attributes */
    public function handle(?Project $project, array $attributes, ?User $actor = null): Project
    {
        $baru = $project === null || ! $project->exists;

        $nama = trim((string) ($attributes['name'] ?? ''));

        if ($nama === '') {
            throw MasterRuleException::rule('BR-GEN-11', 'Nama proyek wajib diisi.');
        }

        $kode = MasterCode::resolve($project, (string) ($attributes['code'] ?? ''), 'proyek');

        $bentrok = Project::query()->where('code', $kode)
            ->when(! $baru, fn ($q) => $q->whereKeyNot($project->getKey()))
            ->exists();

        if ($bentrok) {
            throw MasterRuleException::rule('BR-MST-01', 'Kode proyek "'.$kode.'" sudah dipakai.');
        }

        $internal = (bool) ($attributes['is_internal'] ?? $project?->is_internal ?? false);
        $clientId = $attributes['client_id'] ?? null;
        $clientId = ($clientId === '' || $clientId === null) ? null : (int) $clientId;

        if ($internal && $clientId !== null) {
            throw MasterRuleException::fields(
                ['client_id' => 'Proyek Internal tidak boleh punya klien (BR-MST-04).'],
                'BR-MST-04',
            );
        }

        if (! $internal && $clientId === null) {
            throw MasterRuleException::fields(
                ['client_id' => 'Klien wajib dipilih, kecuali proyek ini ditandai Proyek Internal.'],
                'BR-MST-04',
            );
        }

        if ($clientId !== null && ! Client::query()->whereKey($clientId)->active()->exists()) {
            throw MasterRuleException::fields(
                ['client_id' => 'Klien yang dipilih tidak aktif.'],
                'BR-MST-05',
            );
        }

        $data = [
            'code' => $kode,
            'name' => $nama,
            'client_id' => $clientId,
            'is_internal' => $internal,
            'address' => $this->kosongJadiNull($attributes['address'] ?? null),
            'lat' => $this->angkaAtauNull($attributes['lat'] ?? null),
            'lng' => $this->angkaAtauNull($attributes['lng'] ?? null),
            'start_date' => $this->kosongJadiNull($attributes['start_date'] ?? null),
            'target_end_date' => $this->kosongJadiNull($attributes['target_end_date'] ?? null),
            'pic_user_id' => $this->idAtauNull($attributes['pic_user_id'] ?? null),
        ];

        $project = DB::transaction(function () use ($project, $baru, $data): Project {
            if ($baru) {
                return Project::create($data + ['status' => ProjectStatus::Active]);
            }

            $project->fill($data)->save();

            return $project;
        });

        activity('master')
            ->performedOn($project)
            ->causedBy($actor)
            ->log($baru ? 'Proyek dibuat' : 'Proyek diubah');

        return $project->refresh();
    }

    private function kosongJadiNull(mixed $value): ?string
    {
        $teks = trim((string) ($value ?? ''));

        return $teks === '' ? null : $teks;
    }

    private function angkaAtauNull(mixed $value): ?float
    {
        $teks = trim((string) ($value ?? ''));

        return $teks === '' ? null : (float) $teks;
    }

    private function idAtauNull(mixed $value): ?int
    {
        return ($value === '' || $value === null) ? null : (int) $value;
    }
}
