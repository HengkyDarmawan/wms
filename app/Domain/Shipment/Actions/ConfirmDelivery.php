<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Asset\Support\AssetCustody;
use App\Domain\Master\Models\CompanySetting;
use App\Domain\Notification\Support\DomainNotifications;
use App\Domain\Request\Support\RequestFulfillment;
use App\Domain\Shipment\Enums\DiscrepancyOrigin;
use App\Domain\Shipment\Enums\DiscrepancyStatus;
use App\Domain\Shipment\Enums\DiscrepancyType;
use App\Domain\Shipment\Enums\OwnershipEffect;
use App\Domain\Shipment\Enums\PodUnitCondition;
use App\Domain\Shipment\Enums\ProofChannel;
use App\Domain\Shipment\Enums\ShipmentStatus;
use App\Domain\Shipment\Exceptions\ShipmentRuleException;
use App\Domain\Shipment\Models\DeliveryDiscrepancy;
use App\Domain\Shipment\Models\DeliveryDiscrepancyLine;
use App\Domain\Shipment\Models\ProofOfDelivery;
use App\Domain\Shipment\Models\ProofOfDeliveryLine;
use App\Domain\Shipment\Models\ProofOfDeliveryUnit;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Shipment\Models\ShipmentLine;
use App\Domain\Shipment\Support\WarehouseBins;
use App\Domain\Stock\Enums\StockEventType;
use App\Domain\Stock\Enums\StockStatus;
use App\Domain\Stock\Exceptions\LedgerException;
use App\Domain\Stock\Support\DocumentNumber;
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `shipment.confirm_delivery` — mencatat apa yang benar-benar
 * sampai (BR-SJ-04, BR-SJ-05, BR-SJ-06, BR-SJ-10).
 *
 * Inti aturannya satu kalimat: **barang tidak menguap karena penerima
 * menolaknya**. Yang baik mengikuti tujuannya; yang rusak dan yang kurang tetap
 * tercatat sebagai stok gudang asal di bin Dalam Perjalanan sampai DSC
 * diselesaikan.
 */
class ConfirmDelivery
{
    /** Batas hari pemohon boleh mengajukan keberatan (BR-REQ-10). */
    public const AMBANG_KONFIRMASI = 'receipt_confirm_days';

    public function __construct(
        private readonly StockLedger $ledger,
        private readonly WarehouseBins $bins,
        private readonly DocumentNumber $nomor,
        private readonly RequestFulfillment $pemenuhan,
    ) {}

    /**
     * @param  array<int, array{shipment_line_id: int|string, qty_good?: float|string, qty_damaged?: float|string, qty_missing?: float|string, damage_photo_path?: ?string, notes?: ?string}>  $lines
     * @param  array<string, mixed>  $proof
     */
    public function handle(Shipment $shipment, array $proof, array $lines, ?User $actor = null): ProofOfDelivery
    {
        if ($shipment->status !== ShipmentStatus::Shipped) {
            throw ShipmentRuleException::rule(
                'BR-SJ-05',
                'Bukti terima hanya bisa diisi untuk surat jalan yang sedang dikirim.',
            );
        }

        if ($shipment->proof()->exists()) {
            throw ShipmentRuleException::rule('BR-SJ-05', 'Surat jalan ini sudah punya bukti terima.');
        }

        $penerima = trim((string) ($proof['received_by_name'] ?? ''));

        if ($penerima === '') {
            throw ShipmentRuleException::field('BR-SJ-05', 'received_by_name', 'Nama penerima wajib diisi.');
        }

        $barisSj = $shipment->lines()->with('pickTaskLine.item')->get()->keyBy('id');
        $isian = $this->periksaIsian($barisSj, $lines);

        $hasil = DB::transaction(function () use ($shipment, $proof, $isian, $barisSj, $penerima, $actor) {
            $bukti = ProofOfDelivery::create([
                'shipment_id' => $shipment->id,
                'received_by_name' => $penerima,
                'received_by_user_id' => $this->id($proof, 'received_by_user_id'),
                'signature_path' => $this->teks($proof, 'signature_path'),
                'photo_path' => $this->teks($proof, 'photo_path'),
                'lat' => $proof['lat'] ?? null,
                'lng' => $proof['lng'] ?? null,
                'confirmed_at' => now(),
                'channel' => ProofChannel::tryFrom((string) ($proof['channel'] ?? '')) ?? ProofChannel::DriverPwa,
                'confirm_deadline_at' => now()->addDays($this->ambangKonfirmasi()),
                'notes' => $this->teks($proof, 'notes'),
            ]);

            $adaSelisih = false;

            foreach ($isian as $baris) {
                /** @var ShipmentLine $sj */
                $sj = $barisSj[$baris['shipment_line_id']];

                $barisBukti = ProofOfDeliveryLine::create([
                    'proof_of_delivery_id' => $bukti->id,
                    'shipment_line_id' => $sj->id,
                    'qty_good' => $baris['qty_good'],
                    'qty_damaged' => $baris['qty_damaged'],
                    'qty_missing' => $baris['qty_missing'],
                    'damage_photo_path' => $baris['damage_photo_path'],
                    'notes' => $baris['notes'],
                ]);

                if ($baris['unit'] !== null) {
                    ProofOfDeliveryUnit::create(['proof_of_delivery_line_id' => $barisBukti->id] + $baris['unit']);
                }

                $this->terapkanEfek($shipment, $sj, $baris, $actor);

                $sj->forceFill(['qty_delivered' => $baris['qty_good']])->save();
                $this->pemenuhan->received($sj, (float) $baris['qty_good'], $actor);

                if ($barisBukti->hasDamage() || (float) $baris['qty_missing'] > 0) {
                    $adaSelisih = true;
                }
            }

            // BR-SJ-06: selisih apa pun membuka DSC dan menurunkan status SJ.
            if ($adaSelisih) {
                $this->bukaSelisih($shipment, $bukti, $isian, $barisSj, $actor);
            }

            $shipment->forceFill([
                'status' => $adaSelisih ? ShipmentStatus::PartiallyDelivered : ShipmentStatus::Delivered,
                'delivered_at' => now(),
            ])->save();

            activity('shipment')
                ->performedOn($shipment)
                ->causedBy($actor)
                ->withProperties(['penerima' => $penerima, 'selisih' => $adaSelisih])
                ->log($adaSelisih ? 'Bukti terima dengan selisih' : 'Bukti terima lengkap');

            return $bukti->refresh();
        });

        // Blueprint §10: pemohon diminta konfirmasi (BR-REQ-10); DSC baru ke penyelesai.
        $notif = app(DomainNotifications::class);
        $notif->deliveryReceived($hasil, $actor);
        $shipment->discrepancies()->where('status', 'open')->get()->each(fn ($dsc) => $notif->discrepancyOpened($dsc, $actor));

        return $hasil;
    }

    public function ambangKonfirmasi(): int
    {
        $nilai = (int) CompanySetting::get(self::AMBANG_KONFIRMASI, 3);

        return $nilai > 0 ? $nilai : 3;
    }

    /**
     * BR-SJ-05: ketiga jumlah harus berjumlah persis sebanyak yang dikirim, dan
     * kerusakan wajib berfoto.
     *
     * @param  Collection<int, ShipmentLine>  $barisSj
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    private function periksaIsian(Collection $barisSj, array $lines): array
    {
        $hasil = [];
        $terisi = [];

        foreach ($lines as $baris) {
            $id = (int) ($baris['shipment_line_id'] ?? 0);

            if (! $barisSj->has($id)) {
                throw ShipmentRuleException::rule('BR-SJ-05', 'Ada baris bukti terima yang bukan milik surat jalan ini.');
            }

            /** @var ShipmentLine $sj */
            $sj = $barisSj[$id];

            $baik = (float) ($baris['qty_good'] ?? 0);
            $rusak = (float) ($baris['qty_damaged'] ?? 0);
            $kurang = (float) ($baris['qty_missing'] ?? 0);

            if ($baik < 0 || $rusak < 0 || $kurang < 0) {
                throw ShipmentRuleException::field('BR-SJ-05', 'qty_good', 'Jumlah tidak boleh negatif.');
            }

            $total = $baik + $rusak + $kurang;
            $dikirim = (float) $sj->qty_shipped;

            if (abs($total - $dikirim) > 0.0001) {
                throw ShipmentRuleException::field(
                    'BR-SJ-05',
                    'qty_good',
                    'Baris '.($sj->pickTaskLine?->item?->code ?? $id).': baik + rusak + kurang = '.$total
                    .', seharusnya '.$dikirim.' sesuai yang dikirim.',
                );
            }

            // BR-SJ-05, A-64, A-244: serial dan potongan dinilai per unit — satu
            // baris SJ adalah satu unit, jadi seluruhnya baik, rusak, atau kurang.
            $pick = $sj->pickTaskLine;
            $unit = null;

            if ($pick?->serial_id !== null || $pick?->piece_id !== null) {
                $penuh = array_filter(['good' => $baik, 'damaged' => $rusak, 'missing' => $kurang], fn (float $n) => $n > 0.0001);

                if (count($penuh) !== 1) {
                    throw ShipmentRuleException::field(
                        'BR-SJ-05',
                        'qty_good',
                        'Baris '.($pick->item?->code ?? $id).' adalah satu unit (serial/potongan): pilih baik, rusak, atau kurang untuk seluruhnya.',
                    );
                }

                $unit = ['serial_id' => $pick->serial_id, 'piece_id' => $pick->piece_id, 'condition' => PodUnitCondition::from((string) array_key_first($penuh))];
            }

            $foto = is_string($baris['damage_photo_path'] ?? null) ? trim($baris['damage_photo_path']) : '';

            // A-64: kerusakan tanpa foto tidak bisa diadu belakangan.
            if ($rusak > 0 && $foto === '') {
                throw ShipmentRuleException::field(
                    'BR-SJ-05',
                    'damage_photo_path',
                    'Foto wajib dilampirkan untuk baris yang rusak.',
                );
            }

            $hasil[] = [
                'shipment_line_id' => $id,
                'qty_good' => $baik,
                'qty_damaged' => $rusak,
                'qty_missing' => $kurang,
                'damage_photo_path' => $foto === '' ? null : $foto,
                'notes' => is_string($baris['notes'] ?? null) ? ($baris['notes'] ?: null) : null,
                'unit' => $unit,
            ];

            $terisi[] = $id;
        }

        $belum = $barisSj->keys()->diff($terisi);

        if ($belum->isNotEmpty()) {
            throw ShipmentRuleException::rule(
                'BR-SJ-05',
                'Bukti terima harus mengisi seluruh baris surat jalan; '.$belum->count().' baris belum diisi.',
            );
        }

        return $hasil;
    }

    /**
     * BR-SJ-04: nasib jumlah **baik** ditentukan tujuan dan kepemilikan.
     *
     * @param  array<string, mixed>  $baris
     */
    private function terapkanEfek(Shipment $shipment, ShipmentLine $sj, array $baris, ?User $actor): void
    {
        $baik = (float) $baris['qty_good'];

        if ($baik <= 0) {
            return;
        }

        $transit = $this->bins->inTransit($shipment->warehouse);

        // Ke gudang lain atau Gudang Site: barang tetap milik gudang asal
        // sampai GRN tujuan mencatatnya, jadi ia menunggu di Dalam Perjalanan.
        if ($shipment->destination_type->staysInTransitUntilReceipt()) {
            // SJ balik RET tidak menerbitkan `stock_transferred`: satu-satunya
            // kejadian retur adalah `goods_returned`/`asset_returned` saat
            // dipilah (matriks §14, A-112).
            if ($sj->pickTaskLine?->pickTask?->source_type !== 'goods_return') {
                $this->catatKejadian($shipment, $sj, $baik, StockEventType::StockTransferred);
            }

            return;
        }

        // Aset dipinjamkan: pindah ke bin On-site proyek, tetap milik company.
        if ($sj->ownership_effect === OwnershipEffect::Loan && $shipment->destinationProject !== null) {
            $tujuan = $this->bins->onSite($shipment->destinationProject);

            // AST lahir di sini supaya kejadian `asset_checked_out` membawa serial,
            // tanggal kembali, dan meter keluar (matriks §14, 25-aset).
            $aset = app(AssetCustody::class)->checkOut($shipment, $sj, $actor);

            $this->pindahkan($shipment, $sj, $baik, (int) $transit->id, (int) $tujuan->id, StockEventType::AssetCheckedOut, $actor, $aset);

            return;
        }

        // Jual putus: barang keluar dari ledger.
        $this->pindahkan($shipment, $sj, $baik, (int) $transit->id, null, StockEventType::GoodsDelivered, $actor);
    }

    /**
     * BR-SJ-10: rusak berkondisi `damaged` seketika; kurang tetap `available`.
     * Keduanya tidak berpindah bin — mereka menunggu di Dalam Perjalanan.
     */
    private function tandaiRusak(Shipment $shipment, ShipmentLine $sj, float $qty, ?User $actor): void
    {
        if ($qty <= 0) {
            return;
        }

        $asal = $sj->pickTaskLine;
        $transit = $this->bins->inTransit($shipment->warehouse);

        try {
            // Barang tidak pindah; yang berubah kondisinya. Keluar sebagai
            // Tersedia, masuk kembali sebagai Rusak, di bin yang sama.
            $this->ledger->post(new MovementRequest(
                item: $asal->item,
                qtyBase: $qty,
                fromBinId: (int) $transit->id,
                toBinId: (int) $transit->id,
                stockStatus: StockStatus::Damaged,
                fromStockStatus: StockStatus::Available,
                lotId: $asal->lot_id,
                serialId: $asal->serial_id,
                pieceId: $asal->piece_id,
                documentType: 'shipment',
                documentId: (int) $shipment->id,
                documentLineId: (int) $sj->id,
                documentNumber: $shipment->number,
                performedBy: $actor,
                notes: 'Rusak saat diterima',
            ));
        } catch (LedgerException $e) {
            throw ShipmentRuleException::rule($e->rule, 'Gagal menandai barang rusak: '.$e->getMessage());
        }
    }

    private function pindahkan(
        Shipment $shipment,
        ShipmentLine $sj,
        float $qty,
        int $fromBinId,
        ?int $toBinId,
        StockEventType $event,
        ?User $actor,
        array $tambahan = [],
    ): void {
        $asal = $sj->pickTaskLine;

        try {
            $this->ledger->post(new MovementRequest(
                item: $asal->item,
                qtyBase: $qty,
                fromBinId: $fromBinId,
                toBinId: $toBinId,
                lotId: $asal->lot_id,
                serialId: $asal->serial_id,
                pieceId: $asal->piece_id,
                projectId: $shipment->destination_project_id,
                documentType: 'shipment',
                documentId: (int) $shipment->id,
                documentLineId: (int) $sj->id,
                documentNumber: $shipment->number,
                performedBy: $actor,
                eventType: $event,
                eventPayload: [
                    'shipment_number' => $shipment->number,
                    'destination_type' => $shipment->destination_type->value,
                    'ownership_effect' => $sj->ownership_effect->value,
                    'item_code' => $asal->item?->code,
                    'qty_base' => $qty,
                ] + $tambahan,
            ));
        } catch (LedgerException $e) {
            throw ShipmentRuleException::rule($e->rule, 'Gagal mencatat penerimaan: '.$e->getMessage());
        }
    }

    /** Kejadian tanpa pergerakan: barang tetap di tempatnya, hanya diumumkan. */
    private function catatKejadian(Shipment $shipment, ShipmentLine $sj, float $qty, StockEventType $event): void
    {
        $this->ledger->emitEvent($event, [
            'shipment_number' => $shipment->number,
            'destination_type' => $shipment->destination_type->value,
            'destination_warehouse_id' => $shipment->destination_warehouse_id,
            'item_code' => $sj->pickTaskLine?->item?->code,
            'qty_base' => $qty,
        ], 'shipment', (int) $shipment->id, $shipment->number, $shipment->destination_project_id);
    }

    /**
     * @param  array<int, array<string, mixed>>  $isian
     * @param  Collection<int, ShipmentLine>  $barisSj
     */
    private function bukaSelisih(
        Shipment $shipment,
        ProofOfDelivery $bukti,
        array $isian,
        Collection $barisSj,
        ?User $actor,
    ): DeliveryDiscrepancy {
        $dsc = DeliveryDiscrepancy::create([
            'number' => $this->nomor->next('DSC', (string) $shipment->warehouse->code),
            'shipment_id' => $shipment->id,
            'origin' => DiscrepancyOrigin::PartialDelivery,
            'status' => DiscrepancyStatus::Open,
        ]);

        foreach ($isian as $baris) {
            /** @var ShipmentLine $sj */
            $sj = $barisSj[$baris['shipment_line_id']];

            foreach ([
                DiscrepancyType::Damaged->value => (float) $baris['qty_damaged'],
                DiscrepancyType::Missing->value => (float) $baris['qty_missing'],
            ] as $jenis => $qty) {
                if ($qty <= 0) {
                    continue;
                }

                DeliveryDiscrepancyLine::create([
                    'delivery_discrepancy_id' => $dsc->id,
                    'shipment_line_id' => $sj->id,
                    'discrepancy_type' => $jenis,
                    'qty_base' => $qty,
                    'photo_path' => $jenis === DiscrepancyType::Damaged->value ? $baris['damage_photo_path'] : null,
                ]);
            }

            $this->tandaiRusak($shipment, $sj, (float) $baris['qty_damaged'], $actor);
        }

        activity('shipment')
            ->performedOn($dsc)
            ->causedBy($actor)
            ->withProperties(['sj' => $shipment->number, 'bukti' => $bukti->id])
            ->log('Selisih pengiriman dibuka');

        return $dsc;
    }

    /** @param  array<string, mixed>  $data */
    private function id(array $data, string $key): ?int
    {
        $nilai = $data[$key] ?? null;

        return $nilai === null || $nilai === '' ? null : (int) $nilai;
    }

    /** @param  array<string, mixed>  $data */
    private function teks(array $data, string $key): ?string
    {
        $nilai = is_string($data[$key] ?? null) ? trim($data[$key]) : '';

        return $nilai === '' ? null : $nilai;
    }
}
