<?php

declare(strict_types=1);

namespace App\Domain\Transfer\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Master\Models\Project;
use App\Domain\Transfer\Exceptions\TransferRuleException;
use App\Domain\Transfer\Models\Transfer;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `transfer.create` — "Pindahkan ke proyek lain" dari hub proyek
 * (A-250): saat proyek asal selesai, sisa barangnya dipindah ke proyek lain
 * dalam satu langkah:
 *
 * - aset yang sedang dipinjam proyek asal → satu TRF aset On-site (A-249);
 * - stok Tersedia di Gudang Site proyek asal → satu TRF biasa per Gudang Site
 *   asal ke Gudang Site proyek tujuan yang dipilih (BR-RET-02, alur PCK → SJ →
 *   GRN yang sudah ada).
 *
 * Semua dibuat dalam satu transaksi: gagal satu, gagal semua. Proyek asal tidak
 * ditutup otomatis — setelah semuanya tiba, checklist penutupan (BR-PRJ-02)
 * kosong dan proyek ditutup dari hub seperti biasa.
 */
class MoveProjectRemainder
{
    public function __construct(
        private readonly CreateAssetTransfer $aset,
        private readonly CreateTransfer $stok,
    ) {}

    /**
     * @param  array<string, mixed>  $data  to_project_id, to_warehouse_id, serial_ids[], stock[] (warehouse_id, item_id, qty_base), notes
     * @return array{asset: ?Transfer, stock: array<int, Transfer>}
     */
    public function handle(Project $asal, array $data, ?User $actor = null): array
    {
        $tujuanId = (int) ($data['to_project_id'] ?? 0);
        $serial = array_values(array_filter(array_map('intval', (array) ($data['serial_ids'] ?? []))));
        $stok = collect((array) ($data['stock'] ?? []))
            ->map(fn ($s) => ['warehouse_id' => (int) ($s['warehouse_id'] ?? 0), 'item_id' => (int) ($s['item_id'] ?? 0), 'qty_base' => round((float) ($s['qty_base'] ?? 0), 4)])
            ->filter(fn (array $s) => $s['warehouse_id'] > 0 && $s['item_id'] > 0 && $s['qty_base'] > 0);

        if ($tujuanId === 0) {
            throw TransferRuleException::field('BR-RET-02', 'to_project_id', 'Proyek tujuan wajib dipilih.');
        }

        if ($serial === [] && $stok->isEmpty()) {
            throw TransferRuleException::rule('BR-RET-01', 'Pilih minimal satu aset atau stok yang dipindah.');
        }

        $catatan = trim((string) ($data['notes'] ?? '')) ?: 'Pindahan sisa proyek '.$asal->code;

        return DB::transaction(function () use ($asal, $tujuanId, $serial, $stok, $data, $catatan, $actor) {
            $trfAset = $serial === [] ? null : $this->aset->handle(
                ['from_project_id' => $asal->id, 'to_project_id' => $tujuanId, 'notes' => $catatan],
                $serial,
                $actor,
            );

            $trfStok = [];

            if ($stok->isNotEmpty()) {
                $gudangTujuan = Warehouse::query()->withoutGlobalScopes()->find((int) ($data['to_warehouse_id'] ?? 0));

                if ($gudangTujuan === null || (int) $gudangTujuan->project_id !== $tujuanId) {
                    throw TransferRuleException::field('BR-RET-02', 'to_warehouse_id', 'Pilih Gudang Site proyek tujuan untuk stok yang dipindah.');
                }

                foreach ($stok->groupBy('warehouse_id') as $gudangAsalId => $baris) {
                    $gudangAsal = Warehouse::query()->withoutGlobalScopes()->find((int) $gudangAsalId);

                    if ($gudangAsal === null || (int) $gudangAsal->project_id !== (int) $asal->id) {
                        throw TransferRuleException::field('BR-RET-02', 'stock', 'Stok yang dipindah harus dari Gudang Site proyek '.$asal->code.'.');
                    }

                    $trfStok[] = $this->stok->handle(
                        ['from_warehouse_id' => $gudangAsal->id, 'to_warehouse_id' => $gudangTujuan->id, 'notes' => $catatan],
                        $baris->map(fn (array $b) => ['item_id' => $b['item_id'], 'qty_base' => $b['qty_base']])->values()->all(),
                        $actor,
                    );
                }
            }

            activity('project')->performedOn($asal)->causedBy($actor)
                ->withProperties([
                    'ke' => Project::query()->withoutGlobalScopes()->whereKey($tujuanId)->value('code'),
                    'trf' => array_values(array_filter(array_merge([$trfAset?->number], array_map(fn (Transfer $t) => $t->number, $trfStok)))),
                ])
                ->log('Sisa proyek dipindah ke proyek lain');

            return ['asset' => $trfAset, 'stock' => $trfStok];
        });
    }
}
