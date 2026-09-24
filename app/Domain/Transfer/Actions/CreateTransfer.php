<?php

declare(strict_types=1);

namespace App\Domain\Transfer\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Support\ApprovalEngine;
use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Project;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Stock\Support\DocumentNumber;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Transfer\Enums\TransferOrigin;
use App\Domain\Transfer\Enums\TransferStatus;
use App\Domain\Transfer\Exceptions\TransferRuleException;
use App\Domain\Transfer\Models\Transfer;
use App\Domain\Transfer\Models\TransferLine;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `transfer.create` — TRF `submitted` (Katalog Status §2.7).
 *
 * Dibuat Staf/Kepala Gudang (manual) atau sistem dari backorder REQ
 * (BR-REQ-05, A-106). Guard: gudang asal ≠ tujuan — termasuk dua Gudang Site
 * proyek yang sama, yaitu transfer dalam proyek (A-50). Proyek asal/tujuan
 * diturunkan dari Gudang Site-nya (BR-WH-04), bukan diketik.
 *
 * TRF langsung diteruskan ke mesin approval (`submitted → pending_approval`);
 * tanpa aturan ia disetujui otomatis (A-08) — jalur ringan transfer dalam
 * proyek (BR-RET-02).
 */
class CreateTransfer
{
    public function __construct(
        private readonly DocumentNumber $nomor,
        private readonly ApprovalEngine $approval,
        private readonly StockLedger $ledger,
    ) {}

    /**
     * @param  array<string, mixed>  $header  from_warehouse_id, to_warehouse_id, notes
     * @param  array<int, array<string, mixed>>  $lines  item_id, qty_base, notes, material_request_line_id
     */
    public function handle(
        array $header,
        array $lines,
        ?User $actor = null,
        TransferOrigin $origin = TransferOrigin::Manual,
        ?MaterialRequest $source = null,
    ): Transfer {
        [$asal, $tujuan] = $this->gudang($header, $actor, $origin);

        $proyekAsal = $this->proyek($asal);
        $proyekTujuan = $this->proyek($tujuan);

        $baris = $this->baris($lines, $asal, $origin);

        return DB::transaction(function () use ($asal, $tujuan, $proyekAsal, $proyekTujuan, $baris, $header, $actor, $origin, $source) {
            $trf = Transfer::create([
                'number' => $this->nomor->next('TRF', (string) $asal->code),
                'from_warehouse_id' => $asal->id,
                'to_warehouse_id' => $tujuan->id,
                'from_project_id' => $proyekAsal?->id,
                'to_project_id' => $proyekTujuan?->id,
                'origin' => $origin,
                'status' => TransferStatus::Submitted,
                'source_type' => $source === null ? null : 'material_request',
                'source_id' => $source?->id,
                'submitted_by' => $actor?->id,
                'notes' => $this->teks($header['notes'] ?? null),
            ]);

            foreach ($baris as $b) {
                TransferLine::create($b + ['transfer_id' => $trf->id]);
            }

            activity('transfer')->performedOn($trf)->causedBy($actor)
                ->withProperties([
                    'asal' => $asal->code,
                    'tujuan' => $tujuan->code,
                    'jenis' => $trf->kind()->label(),
                    'baris' => count($baris),
                    'req' => $source?->number,
                ])
                ->log($origin === TransferOrigin::Backorder ? 'TRF dibuat dari backorder REQ' : 'TRF diajukan');

            // Katalog §2.7: submitted → pending_approval otomatis; mesin approval
            // membuat snapshot aturan, tanpa aturan langsung approved (A-08).
            $trf->forceFill(['status' => TransferStatus::PendingApproval])->save();

            $this->approval->submit(ApprovalDocumentType::Transfer, $trf, $actor);

            return $trf->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $header
     * @return array{0: Warehouse, 1: Warehouse}
     */
    private function gudang(array $header, ?User $actor, TransferOrigin $origin): array
    {
        $asalId = (int) ($header['from_warehouse_id'] ?? 0);
        $tujuanId = (int) ($header['to_warehouse_id'] ?? 0);

        $asal = $asalId > 0 ? Warehouse::query()->withoutGlobalScopes()->with('type')->find($asalId) : null;
        $tujuan = $tujuanId > 0 ? Warehouse::query()->withoutGlobalScopes()->with('type')->find($tujuanId) : null;

        if ($asal === null) {
            throw TransferRuleException::field('BR-RET-01', 'from_warehouse_id', 'Gudang asal wajib dipilih.');
        }

        if ($tujuan === null) {
            throw TransferRuleException::field('BR-RET-01', 'to_warehouse_id', 'Gudang tujuan wajib dipilih.');
        }

        // Katalog §2.7: gudang asal ≠ tujuan (termasuk dua Gudang Site satu proyek, A-50).
        if ((int) $asal->id === (int) $tujuan->id) {
            throw TransferRuleException::field('BR-RET-02', 'to_warehouse_id', 'Gudang tujuan harus berbeda dari gudang asal.');
        }

        foreach ([['from_warehouse_id', $asal], ['to_warehouse_id', $tujuan]] as [$kolom, $g]) {
            if (! $g->is_active) {
                throw TransferRuleException::field('BR-WH-07', $kolom, 'Gudang '.$g->code.' nonaktif.');
            }
        }

        // BR-ACC-05: pengaju manual harus bercakupan gudang asal atau tujuan.
        if ($origin === TransferOrigin::Manual && $actor !== null) {
            $cakupan = $actor->accessibleWarehouseIds();
            $boleh = $cakupan === null
                || in_array((int) $asal->id, $cakupan, true)
                || in_array((int) $tujuan->id, $cakupan, true);

            if (! $boleh) {
                throw TransferRuleException::field('BR-ACC-05', 'from_warehouse_id', 'Gudang asal atau tujuan harus dalam cakupan Anda.');
            }
        }

        return [$asal, $tujuan];
    }

    /** BR-WH-04, BR-PRJ-01: proyek diturunkan dari Gudang Site dan harus aktif. */
    private function proyek(Warehouse $gudang): ?Project
    {
        if ($gudang->project_id === null) {
            return null;
        }

        $proyek = Project::query()->withoutGlobalScopes()->find($gudang->project_id);

        if ($proyek !== null && ! $proyek->acceptsDocuments()) {
            throw TransferRuleException::rule(
                'BR-PRJ-01',
                'Proyek '.$proyek->code.' (Gudang Site '.$gudang->code.') berstatus '.$proyek->status->label().' dan tidak menerima dokumen baru.',
            );
        }

        return $proyek;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    private function baris(array $lines, Warehouse $asal, TransferOrigin $origin): array
    {
        $hasil = [];
        $perItem = [];

        foreach (array_values($lines) as $i => $l) {
            $itemId = (int) ($l['item_id'] ?? 0);
            $qty = round((float) ($l['qty_base'] ?? 0), 4);

            if ($itemId === 0 && $qty <= 0) {
                continue;
            }

            $item = $itemId > 0 ? Item::query()->find($itemId) : null;
            $label = 'Baris '.($i + 1);

            if ($item === null) {
                throw TransferRuleException::field('BR-RET-01', 'item_id', $label.': item wajib dipilih.');
            }

            // BR-REQ-03: item sementara/nonaktif belum boleh bergerak.
            if ($item->status !== ItemStatus::Active) {
                throw TransferRuleException::field('BR-REQ-03', 'item_id', $label.' ('.$item->code.'): item berstatus '.$item->status->label().'.');
            }

            if ($qty <= 0) {
                throw TransferRuleException::field('BR-LED-02', 'qty_base', $label.' ('.$item->code.'): jumlah harus lebih dari nol.');
            }

            if ($item->tracksSerial() && abs($qty - round($qty)) > 0.00005) {
                throw TransferRuleException::field('BR-LED-04', 'qty_base', $label.' ('.$item->code.'): item berserial dipindah per unit utuh.');
            }

            $perItem[$item->id] = ($perItem[$item->id] ?? 0) + $qty;

            $hasil[] = [
                'item_id' => $item->id,
                'qty_base' => $qty,
                'material_request_line_id' => isset($l['material_request_line_id']) ? (int) $l['material_request_line_id'] : null,
                'notes' => $this->teks($l['notes'] ?? null),
            ];
        }

        if ($hasil === []) {
            throw TransferRuleException::rule('BR-RET-01', 'TRF wajib punya minimal satu baris.');
        }

        // BR-STK-03: yang diminta dari gudang asal tidak boleh melebihi stok
        // tersedianya. Diperiksa lagi saat reservasi lunak dibuat (disetujui).
        if ($origin === TransferOrigin::Manual) {
            foreach ($perItem as $itemId => $qty) {
                $tersedia = $this->ledger->availableQty((int) $itemId, (int) $asal->id);

                if ($qty - $tersedia > 0.00005) {
                    $kode = Item::query()->whereKey($itemId)->value('code');

                    throw TransferRuleException::field(
                        'BR-STK-03',
                        'qty_base',
                        'Stok tersedia '.$kode.' di gudang '.$asal->code.' hanya '.round($tersedia, 4).', diminta '.round($qty, 4).'.',
                    );
                }
            }
        }

        return $hasil;
    }

    private function teks(mixed $nilai): ?string
    {
        $isi = is_scalar($nilai) ? trim((string) $nilai) : '';

        return $isi === '' ? null : mb_substr($isi, 0, 255);
    }
}
