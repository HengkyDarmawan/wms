<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Request\Enums\RequestLineStatus;
use App\Domain\Request\Models\MaterialRequestLine;
use App\Domain\Request\Support\RequestFulfillment;
use App\Domain\Shipment\Enums\ClientDecision;
use App\Domain\Shipment\Enums\DiscrepancyDisposition;
use App\Domain\Shipment\Enums\DiscrepancyStatus;
use App\Domain\Shipment\Exceptions\ShipmentRuleException;
use App\Domain\Shipment\Models\DeliveryDiscrepancy;
use App\Domain\Shipment\Models\DeliveryDiscrepancyLine;
use App\Domain\Shipment\Support\WarehouseBins;
use App\Domain\Stock\Enums\StockEventType;
use App\Domain\Stock\Enums\StockStatus;
use App\Domain\Stock\Exceptions\LedgerException;
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `discrepancy.resolve` — memutuskan nasib barang yang kurang atau
 * rusak (BR-SJ-10).
 *
 * Setiap baris punya dua keputusan yang sengaja dipisah:
 * - **disposisi** — ke mana barangnya pergi;
 * - **keputusan klien** — apakah permintaannya masih perlu dipenuhi.
 *
 * Keduanya tidak selalu searah: barang rusak bisa dibawa balik ke gudang
 * sementara klien tetap menunggu penggantinya.
 */
class ResolveDiscrepancy
{
    public function __construct(
        private readonly StockLedger $ledger,
        private readonly WarehouseBins $bins,
        private readonly RequestFulfillment $pemenuhan,
    ) {}

    /**
     * @param  array<int, array{line_id: int|string, disposition: string, client_decision?: string, reason_code_id?: int|string|null, claim_ref?: ?string}>  $decisions
     */
    public function handle(DeliveryDiscrepancy $dsc, array $decisions, ?string $notes = null, ?User $actor = null): DeliveryDiscrepancy
    {
        if ($dsc->status !== DiscrepancyStatus::Open) {
            throw ShipmentRuleException::rule('BR-SJ-10', 'Selisih ini sudah diselesaikan.');
        }

        $baris = $dsc->lines()->with('shipmentLine.pickTaskLine.item')->get()->keyBy('id');
        $keputusan = $this->periksaKeputusan($baris, $decisions);

        return DB::transaction(function () use ($dsc, $baris, $keputusan, $notes, $actor) {
            foreach ($keputusan as $k) {
                /** @var DeliveryDiscrepancyLine $l */
                $l = $baris[$k['line_id']];

                $l->forceFill([
                    'disposition' => $k['disposition'],
                    'client_decision' => $k['client_decision'],
                    'reason_code_id' => $k['reason_code_id'],
                    'claim_ref' => $k['claim_ref'],
                ])->save();

                $this->terapkanDisposisi($dsc, $l->refresh(), $actor);
                $this->terapkanKeputusanKlien($l, $actor);
            }

            $dsc->forceFill([
                'status' => DiscrepancyStatus::Resolved,
                'resolved_by' => $actor?->id,
                'resolved_at' => now(),
                'notes' => $notes ?? $dsc->notes,
            ])->save();

            $this->ledger->emitEvent(
                StockEventType::DeliveryDiscrepancy,
                [
                    'discrepancy_number' => $dsc->number,
                    'shipment_number' => $dsc->shipment?->number,
                    'baris' => $baris->count(),
                ],
                'delivery_discrepancy',
                (int) $dsc->id,
                $dsc->number,
                $dsc->shipment?->destination_project_id,
            );

            activity('shipment')
                ->performedOn($dsc)
                ->causedBy($actor)
                ->withProperties(['baris' => $baris->count(), 'catatan' => $notes])
                ->log('Selisih pengiriman diselesaikan');

            return $dsc->refresh();
        });
    }

    /**
     * @param  \Illuminate\Support\Collection<int, DeliveryDiscrepancyLine>  $baris
     * @param  array<int, array<string, mixed>>  $decisions
     * @return array<int, array<string, mixed>>
     */
    private function periksaKeputusan(\Illuminate\Support\Collection $baris, array $decisions): array
    {
        $hasil = [];
        $terisi = [];

        foreach ($decisions as $d) {
            $id = (int) ($d['line_id'] ?? 0);

            if (! $baris->has($id)) {
                throw ShipmentRuleException::rule('BR-SJ-10', 'Ada keputusan untuk baris yang bukan milik selisih ini.');
            }

            $disposisi = DiscrepancyDisposition::tryFrom((string) ($d['disposition'] ?? ''));

            if ($disposisi === null) {
                throw ShipmentRuleException::field('BR-SJ-10', 'disposition', 'Disposisi wajib dipilih untuk setiap baris.');
            }

            $alasanId = ($d['reason_code_id'] ?? null) === null || $d['reason_code_id'] === ''
                ? null
                : (int) $d['reason_code_id'];

            // BR-GEN-11: barang yang dikeluarkan dari pembukuan harus punya sebab.
            if ($disposisi->requiresReason() && $alasanId === null) {
                throw ShipmentRuleException::field(
                    'BR-GEN-11',
                    'reason_code_id',
                    'Disposisi '.$disposisi->label().' menuntut alasan.',
                );
            }

            $klaim = is_string($d['claim_ref'] ?? null) ? trim($d['claim_ref']) : '';

            if ($disposisi->requiresClaimRef() && $klaim === '') {
                throw ShipmentRuleException::field(
                    'BR-SJ-10',
                    'claim_ref',
                    'Klaim ke ekspedisi menuntut nomor klaim.',
                );
            }

            $hasil[] = [
                'line_id' => $id,
                'disposition' => $disposisi,
                'client_decision' => ClientDecision::tryFrom((string) ($d['client_decision'] ?? ''))
                    ?? ClientDecision::StillNeeded,
                'reason_code_id' => $alasanId,
                'claim_ref' => $klaim === '' ? null : $klaim,
            ];

            $terisi[] = $id;
        }

        $belum = $baris->keys()->diff($terisi);

        if ($belum->isNotEmpty()) {
            throw ShipmentRuleException::rule(
                'BR-SJ-10',
                'Setiap baris selisih harus punya disposisi; '.$belum->count().' baris belum diputuskan.',
            );
        }

        return $hasil;
    }

    /**
     * Memindahkan barang sesuai disposisi.
     *
     * Semua disposisi kecuali `reship` mengosongkan bin Dalam Perjalanan: entah
     * barangnya pulang ke gudang, atau ia keluar dari pembukuan karena hilang
     * maupun diklaim. `reship` tidak menyentuh stok — yang dikirim ulang adalah
     * barang baru dari gudang.
     */
    private function terapkanDisposisi(DeliveryDiscrepancy $dsc, DeliveryDiscrepancyLine $line, ?User $actor): void
    {
        $qty = (float) $line->qty_base;

        if ($qty <= 0 || $line->disposition === DiscrepancyDisposition::Reship) {
            return;
        }

        $sj = $dsc->shipment;
        $asal = $line->shipmentLine?->pickTaskLine;

        if ($asal?->item === null) {
            return;
        }

        $transit = $this->bins->inTransit($sj->warehouse);

        // Rusak sudah berkondisi `damaged` sejak bukti terima (BR-SJ-10).
        $kondisi = $line->isDamaged() ? StockStatus::Damaged : StockStatus::Available;

        $tujuan = $line->disposition->returnsStock()
            ? (int) $this->bins->returnBin($sj->warehouse)->id
            : null;

        try {
            $this->ledger->post(new MovementRequest(
                item: $asal->item,
                qtyBase: $qty,
                fromBinId: (int) $transit->id,
                toBinId: $tujuan,
                stockStatus: $kondisi,
                lotId: $asal->lot_id,
                serialId: $asal->serial_id,
                pieceId: $asal->piece_id,
                projectId: $sj->destination_project_id,
                documentType: 'delivery_discrepancy',
                documentId: (int) $dsc->id,
                documentLineId: (int) $line->id,
                documentNumber: $dsc->number,
                reasonCodeId: $line->reason_code_id,
                performedBy: $actor,
                notes: $line->disposition->label()
                    .($line->claim_ref !== null ? ' · klaim '.$line->claim_ref : ''),
            ));
        } catch (LedgerException $e) {
            throw ShipmentRuleException::rule(
                $e->rule,
                'Baris '.$asal->item->code.' gagal diselesaikan: '.$e->getMessage(),
            );
        }
    }

    /**
     * BR-SJ-10: `still_needed` mengembalikan jumlahnya ke backorder baris REQ;
     * `not_needed` menutup sisa barisnya.
     */
    private function terapkanKeputusanKlien(DeliveryDiscrepancyLine $line, ?User $actor): void
    {
        $sumber = $line->shipmentLine?->pickTaskLine;

        if ($sumber === null || $sumber->pickTask?->source_type !== 'material_request') {
            return;
        }

        $baris = MaterialRequestLine::query()->find($sumber->source_line_id);

        if ($baris === null) {
            return;
        }

        if ($line->client_decision->closesRequestLine()) {
            $baris->forceFill([
                'status' => RequestLineStatus::Closed,
                'qty_backorder' => 0,
            ])->save();

            // Sisa baris ditutup: REQ bisa jadi selesai karenanya.
            $this->pemenuhan->refresh($baris->request, $actor);

            return;
        }

        $baris->forceFill([
            'qty_backorder' => (float) $baris->qty_backorder + (float) $line->qty_base,
        ])->save();
    }
}
