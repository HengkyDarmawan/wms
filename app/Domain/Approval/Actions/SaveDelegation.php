<?php

declare(strict_types=1);

namespace App\Domain\Approval\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Exceptions\ApprovalRuleException;
use App\Domain\Approval\Models\ApprovalDelegation;
use App\Domain\Approval\Support\ApprovalEngine;
use App\Domain\Approval\Support\ApprovalRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `approval.delegate` — membuat dan mengakhiri delegasi hak
 * approve (BR-APR-05, A-89).
 *
 * Delegasi berperiode dan tidak berantai: delegat tidak bisa mendelegasikan
 * lagi, dan orang yang sedang mendelegasikan tidak bisa menerima delegasi.
 * Pemegang `approval_rule.manage` (Admin Company) boleh membuat delegasi atas
 * nama approver lain — mis. approver yang mendadak cuti. Diakhiri, tidak
 * dihapus (P-03).
 */
class SaveDelegation
{
    public function __construct(
        private readonly ApprovalRegistry $registry,
        private readonly ApprovalEngine $engine,
    ) {}

    /**
     * @param  array<string, mixed>  $data  from_user_id?, to_user_id, starts_at, ends_at, document_types[], notes
     */
    public function create(array $data, User $actor): ApprovalDelegation
    {
        $dari = (int) ($data['from_user_id'] ?? 0) ?: (int) $actor->id;

        if ($dari !== (int) $actor->id && ! $actor->hasPermission('approval_rule.manage')) {
            throw ApprovalRuleException::field('BR-GEN-09', 'from_user_id', 'Anda hanya bisa mendelegasikan hak approve Anda sendiri.');
        }

        $ke = (int) ($data['to_user_id'] ?? 0);

        if ($ke === 0 || $ke === $dari) {
            throw ApprovalRuleException::field('BR-APR-05', 'to_user_id', 'Pilih delegat selain pemberi delegasi.');
        }

        $delegat = User::query()->whereKey($ke)->first();

        if ($delegat === null || ! $delegat->is_active || $delegat->client_id !== null) {
            throw ApprovalRuleException::field('BR-APR-05', 'to_user_id', 'Delegat harus user internal yang aktif.');
        }

        try {
            $awal = CarbonImmutable::parse((string) ($data['starts_at'] ?? ''));
            $akhir = CarbonImmutable::parse((string) ($data['ends_at'] ?? ''));
        } catch (\Throwable) {
            throw ApprovalRuleException::field('BR-APR-05', 'ends_at', 'Periode delegasi wajib diisi.');
        }

        if (($data['starts_at'] ?? '') === '' || ($data['ends_at'] ?? '') === '' || $akhir->lessThanOrEqualTo($awal)) {
            throw ApprovalRuleException::field('BR-APR-05', 'ends_at', 'Akhir periode harus sesudah awal periode.');
        }

        if ($akhir->isPast()) {
            throw ApprovalRuleException::field('BR-APR-05', 'ends_at', 'Periode delegasi sudah lewat.');
        }

        $jenis = $this->jenis($data['document_types'] ?? []);
        $this->pastikanDelegatBerizin($delegat, $jenis);
        $this->pastikanTidakBerantai($dari, $ke, $awal, $akhir, $jenis);

        return DB::transaction(function () use ($dari, $ke, $awal, $akhir, $jenis, $data, $actor) {
            $d = ApprovalDelegation::create([
                'from_user_id' => $dari,
                'to_user_id' => $ke,
                'starts_at' => $awal,
                'ends_at' => $akhir,
                'document_types' => $jenis,
                'is_active' => true,
                'notes' => ($n = trim((string) ($data['notes'] ?? ''))) !== '' ? mb_substr($n, 0, 255) : null,
            ]);

            activity('approval')->performedOn($d)->causedBy($actor)->log('Delegasi approval dibuat');

            // Tugas terbuka pemberi delegasi langsung pindah bila periode sudah mulai.
            if ($d->isEffective()) {
                $this->engine->applyDelegations($dari);
            }

            return $d->refresh();
        });
    }

    public function end(ApprovalDelegation $delegation, User $actor): ApprovalDelegation
    {
        if ((int) $delegation->from_user_id !== (int) $actor->id && ! $actor->hasPermission('approval_rule.manage')) {
            throw ApprovalRuleException::rule('BR-GEN-09', 'Anda hanya bisa mengakhiri delegasi Anda sendiri.');
        }

        if (! $delegation->is_active) {
            throw ApprovalRuleException::rule('BR-APR-05', 'Delegasi ini sudah berakhir.');
        }

        $delegation->forceFill([
            'is_active' => false,
            'ends_at' => $delegation->ends_at->isFuture() ? now() : $delegation->ends_at,
        ])->save();

        activity('approval')->performedOn($delegation)->causedBy($actor)->log('Delegasi approval diakhiri');

        return $delegation->refresh();
    }

    /**
     * @param  mixed  $raw
     * @return array<int, string>|null null = semua jenis dokumen
     */
    private function jenis(mixed $raw): ?array
    {
        $nilai = array_values(array_unique(array_filter(array_map('strval', (array) $raw))));

        if ($nilai === []) {
            return null;
        }

        foreach ($nilai as $v) {
            if (ApprovalDocumentType::tryFrom($v) === null || ! $this->registry->has($v)) {
                throw ApprovalRuleException::field('BR-GEN-10', 'document_types', 'Jenis dokumen '.$v.' belum tersambung ke mesin approval.');
            }
        }

        return $nilai;
    }

    /** A-86: delegat harus memegang izin approve jenis dokumen yang dilimpahkan. @param  array<int, string>|null  $jenis */
    private function pastikanDelegatBerizin(User $delegat, ?array $jenis): void
    {
        $daftar = $jenis ?? array_map(fn (ApprovalDocumentType $t) => $t->value, $this->registry->types());
        $berizin = array_filter($daftar, fn (string $t) => $delegat->hasPermission($this->registry->handler($t)->approvePermission()));

        if ($berizin === [] || ($jenis !== null && count($berizin) !== count($jenis))) {
            throw ApprovalRuleException::field('BR-APR-05', 'to_user_id', 'Delegat tidak memegang izin approve untuk jenis dokumen yang dilimpahkan.');
        }
    }

    /** BR-APR-05: tidak berantai dan tidak tumpang tindih. @param  array<int, string>|null  $jenis */
    private function pastikanTidakBerantai(int $dari, int $ke, CarbonImmutable $awal, CarbonImmutable $akhir, ?array $jenis): void
    {
        $beririsan = fn (int $kolomUser, string $kolom) => ApprovalDelegation::query()
            ->overlapping($awal, $akhir)->where($kolom, $kolomUser)->get()
            ->contains(fn (ApprovalDelegation $d) => $d->sharesTypesWith($jenis));

        if ($beririsan($dari, 'from_user_id')) {
            throw ApprovalRuleException::field('BR-APR-05', 'ends_at', 'Sudah ada delegasi aktif pada periode yang beririsan.');
        }

        if ($beririsan($ke, 'from_user_id')) {
            throw ApprovalRuleException::field('BR-APR-05', 'to_user_id', 'Delegat sedang mendelegasikan hak approve-nya pada periode itu; delegasi tidak boleh berantai.');
        }

        if ($beririsan($dari, 'to_user_id')) {
            throw ApprovalRuleException::field('BR-APR-05', 'from_user_id', 'Anda sedang menerima delegasi pada periode itu; delegat tidak bisa mendelegasikan ulang.');
        }
    }
}
