<?php

declare(strict_types=1);

namespace App\Domain\Shared\Support;

use App\Domain\Access\Models\User;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Receipt\Enums\GoodsReceiptStatus;
use App\Domain\Receipt\Enums\PutawayTaskStatus;
use App\Domain\Receipt\Models\GoodsReceipt;
use App\Domain\Receipt\Models\PutawayTask;
use App\Domain\Shipment\Enums\ProofChannel;
use App\Domain\Shipment\Models\PickTask;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Shipment\Support\DeliveryRecipients;
use Illuminate\Support\Collection;

/**
 * Paket data tugas gudang untuk perangkat (32-pwa-offline §3, A-439). Isinya
 * mengikuti izin & cakupan pengguna; klien hanya merekam niat, server memvalidasi.
 */
class OfflineTaskPack
{
    private const BATAS = 100;

    private const RELASI = ['item:id,code,name,base_uom_id', 'item.baseUom:id,code', 'lot:id,lot_no', 'serial:id,serial_no', 'piece:id,piece_no'];

    /** @return array<string, mixed> */
    public function untuk(User $user): array
    {
        return [
            'diunduh' => now()->toIso8601String(),
            'csrf' => csrf_token(),
            'alasan_kurang' => ReasonCode::options(ReasonContext::ShortPick),
            'putaway' => $user->hasPermission('putaway.complete') ? $this->putaway() : [],
            'picking' => $user->hasPermission('pick.complete') ? $this->picking() : [],
            'penerimaan' => $user->hasPermission('receipt.receive') ? $this->penerimaan() : [],
            'muat' => $user->hasPermission('shipment.ship') ? $this->muat($user) : [],
            'bukti' => $this->bukti($user),
        ];
    }

    private function putaway(): Collection
    {
        return PutawayTask::query()->where('status', PutawayTaskStatus::Pending->value)->orderBy('id')->limit(self::BATAS)
            ->with(['lines' => fn ($q) => $q->whereNull('scanned_at')->orderBy('id')->with([...self::RELASI, 'suggestedBin:id,code'])])
            ->get()->map(fn (PutawayTask $t) => [
                'id' => $t->id,
                'nomor' => $t->number,
                'baris' => $t->lines->map(fn ($l) => [...$this->barang($l), 'id' => $l->id, 'qty' => (float) $l->qty_base, 'bin' => $l->suggestedBin?->code])->values(),
            ])->values();
    }

    private function picking(): Collection
    {
        return PickTask::query()->whereIn('status', ['pending', 'in_progress'])->orderBy('id')->limit(self::BATAS)
            ->with(['lines' => fn ($q) => $q->orderBy('id')->with([...self::RELASI, 'bin:id,code'])])
            ->get()->map(fn (PickTask $t) => [
                'id' => $t->id,
                'nomor' => $t->number,
                'baris' => $t->lines->map(fn ($l) => [...$this->barang($l), 'id' => $l->id, 'qty' => (float) $l->qty_allocated, 'bin' => $l->bin?->code])->values(),
            ])->values();
    }

    private function penerimaan(): Collection
    {
        return GoodsReceipt::query()->where('status', GoodsReceiptStatus::Draft->value)->orderBy('id')->limit(self::BATAS)
            ->with(['lines' => fn ($q) => $q->orderBy('id')->with(self::RELASI)])
            ->get()->map(fn (GoodsReceipt $g) => [
                'id' => $g->id,
                'nomor' => $g->number,
                'baris' => $g->lines->map(fn ($l) => [...$this->barang($l), 'id' => $l->id, 'qty' => (float) $l->qty_received])->values(),
            ])->values();
    }

    private function muat(User $user): Collection
    {
        return Shipment::query()->where('status', 'prepared')->orderBy('id')->limit(self::BATAS)
            ->with(['lines' => fn ($q) => $q->orderBy('id')->with(self::RELASI)])->get()
            ->filter(fn (Shipment $s) => $user->can('ship', $s))
            ->map(fn (Shipment $s) => [
                'id' => $s->id,
                'nomor' => $s->number,
                'baris' => $s->lines->map(fn ($l) => [...$this->barang($l), 'id' => $l->id, 'qty' => (float) $l->qty_shipped])->values(),
            ])->values();
    }

    /** Penerima bisa di luar cakupan gudang asal: saringannya `channelFor`, bukan global scope. */
    private function bukti(User $user): Collection
    {
        $penerima = app(DeliveryRecipients::class);

        return Shipment::query()->withoutGlobalScopes()->where('status', 'shipped')->doesntHave('proof')->orderBy('id')->limit(self::BATAS)
            ->with(['lines' => fn ($q) => $q->withoutGlobalScopes()->orderBy('id')->with(self::RELASI)])->get()
            ->map(fn (Shipment $s) => ['sj' => $s, 'kanal' => $penerima->channelFor($user, $s)])
            ->filter(fn (array $x) => $x['kanal'] !== null)
            ->map(fn (array $x) => [
                'id' => $x['sj']->id,
                'nomor' => $x['sj']->number,
                'wajib_sj' => $x['kanal']->requiresSignedDocument(),
                'nama_bawaan' => $x['kanal'] === ProofChannel::SignedDocument ? '' : $user->name,
                'baris' => $x['sj']->lines->map(fn ($l) => [...$this->barang($l), 'id' => $l->id, 'qty' => (float) $l->qty_shipped])->values(),
            ])->values();
    }

    /** @return array{kode: ?string, nama: ?string, satuan: ?string, lacak: ?string} */
    private function barang(object $l): array
    {
        return [
            'kode' => $l->item?->code,
            'nama' => $l->item?->name,
            'satuan' => $l->item?->baseUom?->code,
            'lacak' => $l->lot?->lot_no ?? $l->serial?->serial_no ?? $l->piece?->piece_no,
        ];
    }
}
