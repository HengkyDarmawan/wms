<?php

declare(strict_types=1);

namespace App\Domain\Label\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Label\Models\RfidTag;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Piece;
use App\Domain\Master\Models\Serial;
use App\Domain\Warehouse\Models\Bin;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Permission: `rfid.manage` — daftarkan / ubah / nonaktifkan tag RFID (A-437).
 * Tag tidak dihapus (P-03); tag yang dicopot dinonaktifkan.
 */
class SaveRfidTag
{
    /** @param  array{epc?: mixed, target_type?: mixed, target_code?: mixed, notes?: mixed, is_active?: mixed}  $data */
    public function handle(?RfidTag $tag, array $data, User $actor): RfidTag
    {
        if (! $actor->hasPermission('rfid.manage')) {
            throw new AuthorizationException('Anda tidak berwenang mengelola tag RFID.');
        }

        $epc = RfidTag::normalize((string) ($data['epc'] ?? ''));
        $jenis = (string) ($data['target_type'] ?? '');
        $kode = mb_strtoupper(trim((string) ($data['target_code'] ?? '')));
        $galat = [];

        if (mb_strlen($epc) < 8 || mb_strlen($epc) > 64 || ! ctype_xdigit($epc)) {
            $galat['epc'] = 'EPC harus 8–64 digit heksadesimal (0–9, A–F).';
        } elseif (RfidTag::query()->where('epc', $epc)->when($tag, fn ($q) => $q->whereKeyNot($tag->id))->exists()) {
            $galat['epc'] = 'Tag '.$epc.' sudah terdaftar.';
        }

        if (! array_key_exists($jenis, RfidTag::TARGET)) {
            $galat['target_type'] = 'Pilih jenis target.';
        }

        $targetId = match ($jenis) {
            'item' => Item::query()->where('code', $kode)->value('id'),
            'serial' => Serial::query()->where('serial_no', $kode)->value('id'),
            'piece' => Piece::query()->where('piece_no', $kode)->value('id'),
            'bin' => Bin::query()->withoutGlobalScopes()->where('code', $kode)->value('id'),
            default => null,
        };

        if (! isset($galat['target_type']) && $targetId === null) {
            $galat['target_code'] = $kode === '' ? 'Isi kode target.' : RfidTag::TARGET[$jenis].' dengan kode '.$kode.' tidak ditemukan.';
        }

        if ($galat !== []) {
            throw ValidationException::withMessages($galat);
        }

        $nilai = [
            'epc' => $epc,
            'target_type' => $jenis,
            'target_id' => $targetId,
            'notes' => ($n = trim((string) ($data['notes'] ?? ''))) !== '' ? mb_substr($n, 0, 255) : null,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : ($tag?->is_active ?? true),
        ];

        $tag === null ? $tag = RfidTag::create($nilai + ['created_by' => $actor->id]) : $tag->update($nilai);

        activity('label')->performedOn($tag)->causedBy($actor)
            ->withProperties(['epc' => $epc, 'target' => $jenis.':'.$kode, 'aktif' => $tag->is_active])
            ->log('Tag RFID '.$epc.' → '.RfidTag::TARGET[$jenis].' '.$kode);

        return $tag->refresh();
    }
}
