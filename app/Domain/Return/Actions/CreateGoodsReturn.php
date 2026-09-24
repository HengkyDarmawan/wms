<?php

declare(strict_types=1);

namespace App\Domain\Return\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Support\ApprovalEngine;
use App\Domain\Master\Models\Project;
use App\Domain\Return\Enums\GoodsReturnStatus;
use App\Domain\Return\Enums\ReturnSource;
use App\Domain\Return\Exceptions\ReturnRuleException;
use App\Domain\Return\Models\GoodsReturn;
use App\Domain\Return\Models\GoodsReturnLine;
use App\Domain\Return\Support\ReturnableStock;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Stock\Support\DocumentNumber;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `return.create` — RET `submitted` (Katalog Status §2.8).
 *
 * Baris dipilih dari barang yang boleh diretur proyek itu (`ReturnableStock`,
 * A-110): stok Gudang Site, aset On-site, barang jual-putus yang sudah diterima
 * klien, atau barang rusak yang ditinggal ekspedisi. Klien hanya tiga yang
 * terakhir (BR-RET-05). Baris jual-putus menandai `ownership = sold` (retur
 * penjualan, BR-RET-03, A-26); lainnya `company`.
 *
 * SJ balik (`self_delivered = false`) hanya untuk stok satu Gudang Site —
 * barang lain tidak berada di bin gudang sehingga tidak bisa dipetik (A-111).
 * RET langsung diteruskan ke mesin approval; tanpa aturan disetujui otomatis.
 */
class CreateGoodsReturn
{
    public function __construct(
        private readonly DocumentNumber $nomor,
        private readonly ApprovalEngine $approval,
        private readonly ReturnableStock $calon,
    ) {}

    /**
     * @param  array<string, mixed>  $header  project_id, to_warehouse_id, self_delivered, origin_shipment_id, notes
     * @param  array<int, array<string, mixed>>  $lines  key, qty_base, notes
     */
    public function handle(array $header, array $lines, ?User $actor = null): GoodsReturn
    {
        $proyek = $this->proyek($header, $actor);
        $tujuan = $this->tujuan($header);
        $klien = $actor?->client_id !== null;
        $sendiri = filter_var($header['self_delivered'] ?? true, FILTER_VALIDATE_BOOLEAN);

        $tersedia = $this->calon->forProject($proyek);
        $baris = [];

        foreach (array_values($lines) as $i => $l) {
            $qty = round((float) ($l['qty_base'] ?? 0), 4);

            if ($qty <= 0) {
                continue;
            }

            $c = $tersedia->get((string) ($l['key'] ?? ''));
            $label = 'Baris '.($i + 1);

            if ($c === null) {
                throw ReturnRuleException::field('BR-RET-03', 'key', $label.': barang ini tidak tercatat bisa diretur dari proyek '.$proyek->code.'.');
            }

            /** @var ReturnSource $asal */
            $asal = $c['source'];

            // BR-RET-05: klien hanya barang Terkirim ke Klien, aset di proyeknya, atau rusak ditinggal ekspedisi.
            if ($klien && ! $asal->allowedForClient()) {
                throw ReturnRuleException::field('BR-RET-05', 'key', $label.' ('.$c['item_code'].'): klien tidak bisa meretur stok Gudang Site.');
            }

            if ($qty - (float) $c['max'] > 0.00005) {
                throw ReturnRuleException::field('BR-RET-03', 'qty_base', $label.' ('.$c['item_code'].'): retur '.$qty.' melebihi yang bisa diretur ('.$c['max'].').');
            }

            if ($c['serial_id'] !== null && abs($qty - 1) > 0.00005) {
                throw ReturnRuleException::field('BR-LED-04', 'qty_base', $label.' ('.$c['item_code'].'): serial diretur per unit.');
            }

            if ($c['piece_id'] !== null && abs($qty - (float) $c['max']) > 0.00005) {
                throw ReturnRuleException::field('BR-STK-09', 'qty_base', $label.' ('.$c['item_code'].'): potongan diretur utuh; pemotongan dicatat saat dipilah (offcut).');
            }

            $baris[] = ['c' => $c, 'qty' => $qty, 'notes' => $this->teks($l['notes'] ?? null)];
        }

        if ($baris === []) {
            throw ReturnRuleException::rule('BR-RET-03', 'Pilih minimal satu barang dengan jumlah retur.');
        }

        $gudangSite = collect($baris)->where('c.source', ReturnSource::SiteStock)->pluck('c.warehouse_id')->unique()->values();

        if ($gudangSite->count() > 1) {
            throw ReturnRuleException::rule('BR-RET-03', 'Satu RET hanya untuk stok satu Gudang Site; pisahkan retur per titik.');
        }

        // A-111: SJ balik hanya untuk stok Gudang Site yang bisa dipetik.
        if (! $sendiri && collect($baris)->contains(fn (array $b) => $b['c']['source'] !== ReturnSource::SiteStock)) {
            throw ReturnRuleException::field(
                'BR-RET-03',
                'self_delivered',
                'SJ balik hanya untuk stok Gudang Site. Barang di tangan klien, aset On-site, dan barang ditinggal ekspedisi diretur tanpa SJ (diantar sendiri).',
            );
        }

        $sjAsal = $this->sjAsal($header, $proyek, $baris);

        return DB::transaction(function () use ($proyek, $tujuan, $sendiri, $gudangSite, $sjAsal, $baris, $header, $actor) {
            $ret = GoodsReturn::create([
                'number' => $this->nomor->next('RET', (string) $tujuan->code),
                'project_id' => $proyek->id,
                'origin_shipment_id' => $sjAsal,
                'requester_id' => $actor?->id,
                'from_warehouse_id' => $gudangSite->first(),
                'to_warehouse_id' => $tujuan->id,
                'self_delivered' => $sendiri,
                'status' => GoodsReturnStatus::Submitted,
                'notes' => $this->teks($header['notes'] ?? null),
            ]);

            foreach ($baris as $b) {
                $c = $b['c'];

                GoodsReturnLine::create([
                    'goods_return_id' => $ret->id,
                    'item_id' => $c['item_id'],
                    'lot_id' => $c['lot_id'],
                    'serial_id' => $c['serial_id'],
                    'piece_id' => $c['piece_id'],
                    'from_bin_id' => $c['from_bin_id'],
                    'origin_shipment_line_id' => $c['origin_shipment_line_id'],
                    'origin_discrepancy_line_id' => $c['origin_discrepancy_line_id'],
                    'ownership' => $c['ownership'],
                    'stock_status' => $c['stock_status'],
                    'qty_base' => $b['qty'],
                    'notes' => $b['notes'],
                ]);
            }

            activity('return')->performedOn($ret)->causedBy($actor)
                ->withProperties(['proyek' => $proyek->code, 'tujuan' => $tujuan->code, 'baris' => count($baris), 'tanpa_sj' => $sendiri])
                ->log('RET diajukan');

            // Katalog §2.8: submitted → pending_approval/approved otomatis sesuai aturan.
            $ret->forceFill(['status' => GoodsReturnStatus::PendingApproval])->save();

            $this->approval->submit(ApprovalDocumentType::GoodsReturn, $ret, $actor);

            return $ret->refresh();
        });
    }

    /** BR-PRJ-01, BR-ACC-05: proyek aktif dalam cakupan pengaju. */
    private function proyek(array $header, ?User $actor): Project
    {
        $id = (int) ($header['project_id'] ?? 0);
        $proyek = $id > 0 ? Project::query()->withoutGlobalScopes()->find($id) : null;
        $cakupan = $actor?->accessibleProjectIds();

        if ($proyek === null || ($cakupan !== null && ! in_array((int) $proyek->id, $cakupan, true))) {
            throw ReturnRuleException::field('BR-ACC-05', 'project_id', 'Proyek wajib dipilih dari proyek dalam cakupan Anda.');
        }

        if (! $proyek->acceptsDocuments()) {
            throw ReturnRuleException::field('BR-PRJ-01', 'project_id', 'Proyek '.$proyek->code.' berstatus '.$proyek->status->label().' dan tidak menerima dokumen baru.');
        }

        return $proyek;
    }

    /** Gudang penerima retur: gudang company aktif, bukan Gudang Site. */
    private function tujuan(array $header): Warehouse
    {
        $id = (int) ($header['to_warehouse_id'] ?? 0);
        $gudang = $id > 0 ? Warehouse::query()->withoutGlobalScopes()->with('type')->find($id) : null;

        if ($gudang === null) {
            throw ReturnRuleException::field('BR-RET-01', 'to_warehouse_id', 'Gudang tujuan retur wajib dipilih.');
        }

        if (! $gudang->is_active) {
            throw ReturnRuleException::field('BR-WH-07', 'to_warehouse_id', 'Gudang '.$gudang->code.' nonaktif.');
        }

        if ($gudang->isSite()) {
            throw ReturnRuleException::field('BR-RET-02', 'to_warehouse_id', 'Retur kembali ke gudang, bukan ke Gudang Site; pemindahan antar titik memakai transfer.');
        }

        return $gudang;
    }

    /**
     * BR-RET-03: retur merujuk SJ asal bila ada. Dipakai SJ yang dipilih, atau
     * satu-satunya SJ asal baris jual-putus/klaim.
     *
     * @param  array<int, array<string, mixed>>  $baris
     */
    private function sjAsal(array $header, Project $proyek, array $baris): ?int
    {
        $dariBaris = collect($baris)->pluck('c.shipment_id')->filter()->unique()->values();
        $pilihan = (int) ($header['origin_shipment_id'] ?? 0);

        if ($pilihan === 0) {
            return $dariBaris->count() === 1 ? (int) $dariBaris->first() : null;
        }

        $sj = Shipment::query()->withoutGlobalScopes()->find($pilihan);

        if ($sj === null || (int) $sj->destination_project_id !== (int) $proyek->id) {
            throw ReturnRuleException::field('BR-RET-03', 'origin_shipment_id', 'SJ asal harus pengiriman ke proyek '.$proyek->code.'.');
        }

        if ($dariBaris->contains(fn ($id) => (int) $id !== $pilihan)) {
            throw ReturnRuleException::field('BR-RET-03', 'origin_shipment_id', 'Semua barang terkirim yang diretur harus berasal dari SJ '.$sj->number.'.');
        }

        return (int) $sj->id;
    }

    private function teks(mixed $nilai): ?string
    {
        $isi = is_scalar($nilai) ? trim((string) $nilai) : '';

        return $isi === '' ? null : mb_substr($isi, 0, 255);
    }
}
