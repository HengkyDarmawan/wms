<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Request\Models\MaterialRequestLine;
use App\Domain\Shipment\Enums\DestinationType;
use App\Domain\Shipment\Enums\OwnershipEffect;
use App\Domain\Shipment\Enums\PickTaskStatus;
use App\Domain\Shipment\Enums\ShipmentMethod;
use App\Domain\Shipment\Enums\ShipmentStatus;
use App\Domain\Shipment\Exceptions\ShipmentRuleException;
use App\Domain\Shipment\Models\PickTask;
use App\Domain\Shipment\Models\PickTaskLine;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Shipment\Models\ShipmentLine;
use App\Domain\Stock\Support\DocumentNumber;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `shipment.create` — menyusun surat jalan dari PCK yang selesai
 * (Katalog Status §2.3, BR-SJ-07, BR-SJ-09).
 *
 * Satu SJ boleh memuat beberapa PCK dari beberapa REQ asalkan gudang asal dan
 * tujuannya sama: yang menentukan satu perjalanan adalah kendaraan, bukan
 * dokumen permintaannya.
 */
class CreateShipment
{
    public function __construct(private readonly DocumentNumber $nomor) {}

    /**
     * @param  array<int, int>  $pickTaskIds
     * @param  array<string, mixed>  $data
     */
    public function handle(array $pickTaskIds, array $data, ?User $actor = null): Shipment
    {
        $tugas = $this->kumpulkanTugas($pickTaskIds);
        $gudang = $tugas->first()->warehouse;

        $tujuan = $this->tujuan($data);
        $cara = $this->caraKirim($data);

        $this->pastikanLengkap($tujuan->requiredFields(), $data, 'BR-SJ-04');
        $this->pastikanLengkap($cara->requiredFields(), $data, 'BR-SJ-07');

        return DB::transaction(function () use ($tugas, $gudang, $tujuan, $cara, $data, $actor) {
            $sj = Shipment::create([
                'number' => $this->nomor->next('SJ', (string) $gudang->code),
                'warehouse_id' => $gudang->id,
                'destination_type' => $tujuan,
                'destination_project_id' => $this->id($data, 'destination_project_id'),
                'destination_warehouse_id' => $this->id($data, 'destination_warehouse_id'),
                'destination_vendor_id' => $this->id($data, 'destination_vendor_id'),
                'shipment_method' => $cara,
                'vehicle_id' => $this->id($data, 'vehicle_id'),
                'driver_id' => $this->id($data, 'driver_id'),
                'carrier_id' => $this->id($data, 'carrier_id'),
                'tracking_no' => $this->teks($data, 'tracking_no'),
                'carried_by_name' => $this->teks($data, 'carried_by_name'),
                'status' => ShipmentStatus::Prepared,
                'notes' => $this->teks($data, 'notes'),
            ]);

            $dimuat = 0;

            foreach ($tugas as $pck) {
                foreach ($pck->lines as $baris) {
                    $sisa = $baris->unshippedQty();

                    if ($sisa <= 0) {
                        continue;
                    }

                    ShipmentLine::create([
                        'shipment_id' => $sj->id,
                        'pick_task_line_id' => $baris->id,
                        'qty_shipped' => $sisa,
                        'ownership_effect' => $this->efekKepemilikan($pck, $baris, $tujuan),
                    ]);

                    $dimuat++;
                }
            }

            if ($dimuat === 0) {
                throw ShipmentRuleException::rule(
                    'BR-SJ-09',
                    'Seluruh baris tugas picking yang dipilih sudah termuat di surat jalan lain.',
                );
            }

            activity('shipment')
                ->performedOn($sj)
                ->causedBy($actor)
                ->withProperties(['pck' => $tugas->pluck('number')->all(), 'baris' => $dimuat])
                ->log('Surat jalan disusun');

            return $sj->refresh();
        });
    }

    /**
     * BR-SJ-09: PCK yang digabung harus satu gudang asal dan satu tujuan.
     *
     * @param  array<int, int>  $ids
     * @return \Illuminate\Support\Collection<int, PickTask>
     */
    private function kumpulkanTugas(array $ids): \Illuminate\Support\Collection
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if ($ids === []) {
            throw ShipmentRuleException::rule('BR-SJ-09', 'Pilih minimal satu tugas picking.');
        }

        $tugas = PickTask::query()
            ->with('warehouse', 'lines')
            ->whereIn('id', $ids)
            ->get();

        if ($tugas->count() !== count($ids)) {
            throw ShipmentRuleException::rule('BR-SJ-09', 'Ada tugas picking yang tidak ditemukan.');
        }

        $belumSelesai = $tugas->firstWhere('status', '!=', PickTaskStatus::Completed);

        if ($belumSelesai !== null) {
            throw ShipmentRuleException::rule(
                'BR-SJ-09',
                'Tugas '.$belumSelesai->number.' belum selesai; hanya PCK selesai yang bisa dimuat.',
            );
        }

        if ($tugas->pluck('warehouse_id')->unique()->count() > 1) {
            throw ShipmentRuleException::rule(
                'BR-SJ-09',
                'Seluruh tugas picking harus berasal dari gudang yang sama.',
            );
        }

        return $tugas;
    }

    /**
     * BR-SJ-04: efek kepemilikan diturunkan dari dokumen asal dan tujuan.
     *
     * Baris REQ bertanda pinjam tetap pinjam ke mana pun ia dikirim; sisanya
     * ditentukan tujuan — ke gudang berarti transfer, ke klien berarti jual
     * putus.
     */
    private function efekKepemilikan(PickTask $pck, PickTaskLine $baris, DestinationType $tujuan): OwnershipEffect
    {
        if ($pck->source_type === 'material_request') {
            $sumber = MaterialRequestLine::query()->find($baris->source_line_id);

            if ($sumber?->line_ownership->value === 'loan') {
                return OwnershipEffect::Loan;
            }
        }

        return $tujuan->staysInTransitUntilReceipt()
            ? OwnershipEffect::Transfer
            : OwnershipEffect::Sold;
    }

    /** @param  array<string, mixed>  $data */
    private function tujuan(array $data): DestinationType
    {
        $nilai = (string) ($data['destination_type'] ?? '');
        $tujuan = DestinationType::tryFrom($nilai);

        if ($tujuan === null) {
            throw ShipmentRuleException::field('BR-SJ-04', 'destination_type', 'Jenis tujuan wajib dipilih.');
        }

        return $tujuan;
    }

    /** @param  array<string, mixed>  $data */
    private function caraKirim(array $data): ShipmentMethod
    {
        $nilai = (string) ($data['shipment_method'] ?? '');
        $cara = ShipmentMethod::tryFrom($nilai);

        if ($cara === null) {
            throw ShipmentRuleException::field('BR-SJ-07', 'shipment_method', 'Cara kirim wajib dipilih.');
        }

        return $cara;
    }

    /**
     * @param  array<string, string>  $fields
     * @param  array<string, mixed>  $data
     */
    private function pastikanLengkap(array $fields, array $data, string $rule): void
    {
        foreach ($fields as $kolom => $label) {
            $isi = $data[$kolom] ?? null;

            if ($isi === null || $isi === '' || $isi === 0) {
                throw ShipmentRuleException::field($rule, $kolom, $label.' wajib diisi untuk pilihan ini.');
            }
        }
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
