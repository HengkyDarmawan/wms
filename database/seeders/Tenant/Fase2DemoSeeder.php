<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Domain\Access\Models\User;
use App\Domain\Asset\Actions\MaintainAsset;
use App\Domain\Asset\Models\MaintenanceSchedule;
use App\Domain\Conversion\Actions\SaveConversionRecipe;
use App\Domain\Conversion\Models\ConversionRecipe;
use App\Domain\Label\Actions\SaveRfidTag;
use App\Domain\Label\Models\RfidTag;
use App\Domain\Master\Models\CompanySetting;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\ProjectMaterialPlan;
use App\Domain\Master\Models\Serial;
use App\Domain\Platform\Models\FeatureFlag;
use App\Domain\Template\Enums\DocumentTemplateType;
use App\Domain\Template\Models\DocumentTemplate;
use Illuminate\Database\Seeder;

/**
 * Data contoh Fase 2 untuk company DEMO (A-430): semua fitur Fase 2 bisa
 * langsung dicoba tanpa pihak luar — WhatsApp simulasi, RFID, servis aset
 * (termasuk pemicu meter), resep konversi, rencana material, teks template.
 * Aman dijalankan berulang: yang sudah ada tidak dibuat dua kali.
 */
class Fase2DemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@demo.wms.test')->first();

        if ($admin === null || tenant() === null) {
            return;
        }

        // Fitur platform: WhatsApp (driver log = Kotak pesan simulasi) & RFID.
        foreach (['whatsapp', 'rfid'] as $kunci) {
            FeatureFlag::query()->updateOrCreate(['company_id' => tenant()->getTenantKey(), 'key' => $kunci], ['enabled' => true]);
        }

        // Approval WhatsApp untuk Kepala Gudang CKG (nomor dummy terverifikasi).
        User::query()->where('email', 'kagudang.ckg@demo.wms.test')->whereNull('phone_verified_at')
            ->update(['phone' => '6281300000101', 'phone_verified_at' => now()]);
        CompanySetting::put('wa_events', ['approval.task_assigned' => 'instant']);

        // Servis aset: genset bermeter jam, servis tiap 90 hari atau 250 jam.
        $gns = Serial::query()->where('serial_no', 'GNS-5K-0001')->first();

        if ($gns !== null) {
            if ($gns->meter_unit?->value === 'none') {
                $gns->forceFill(['meter_unit' => 'hour', 'meter_total' => 120])->save();
            }

            if (! MaintenanceSchedule::query()->where('serial_id', $gns->id)->exists()) {
                app(MaintainAsset::class)->plan($gns->refresh(), ['scheduled_at' => now()->toDateString(), 'interval_days' => '90', 'interval_meter' => '250', 'notes' => 'Ganti oli & filter (contoh)'], $admin);
            }
        }

        // Resep konversi.
        if (! ConversionRecipe::query()->where('code', 'PIPA6-4X150')->exists() && ($pipa = Item::query()->where('code', 'PIPA-PVC-4')->first()) !== null) {
            app(SaveConversionRecipe::class)->handle(null, ['code' => 'PIPA6-4X150', 'name' => 'Pipa 6 m jadi 4 × 1,5 m', 'conversion_type' => 'cut',
                'input_item_id' => $pipa->id, 'potong' => [['item_id' => '', 'length' => '1.5', 'count' => '4']]], $admin);
        }

        // Rencana material PRJ-001.
        $proyek = Project::query()->where('code', 'PRJ-001')->first();

        if ($proyek !== null && ! ProjectMaterialPlan::query()->where('project_id', $proyek->id)->exists()) {
            foreach (['BAUT-M12' => 500, 'PIPA-PVC-4' => 120, 'SEMEN-PCC-50' => 2000] as $kode => $qty) {
                if (($item = Item::query()->where('code', $kode)->first()) !== null) {
                    ProjectMaterialPlan::create(['project_id' => $proyek->id, 'item_id' => $item->id, 'version' => 1, 'planned_qty_base' => $qty,
                        'is_current' => true, 'created_by' => $admin->id, 'source_file' => 'contoh-boq.xlsx']);
                }
            }
        }

        // Tag RFID contoh (EPC dummy).
        foreach ([['E2003412000000000001', 'item', 'BAUT-M12'], ['E2003412000000000002', 'bin', 'CKG-A-R01-L1-B01'], ['E2003412000000000003', 'serial', 'GNS-5K-0001']] as [$epc, $jenis, $kode]) {
            if (! RfidTag::query()->where('epc', $epc)->exists()) {
                try {
                    app(SaveRfidTag::class)->handle(null, ['epc' => $epc, 'target_type' => $jenis, 'target_code' => $kode, 'notes' => 'Tag contoh'], $admin);
                } catch (\Throwable) {
                    // target contoh tidak ada di data ini: lewati
                }
            }
        }

        // Teks template Surat Jalan contoh.
        $sj = DocumentTemplate::forType(DocumentTemplateType::Shipment);

        if ($sj->body_html === null) {
            $sj->update(['body_html' => '<p>Barang untuk proyek <b>{proyek}</b> dikirim dari {gudang}.</p>{isi_bawaan}<p><small>Mohon periksa jumlah dan kondisi barang sebelum menandatangani.</small></p>']);
        }
    }
}
