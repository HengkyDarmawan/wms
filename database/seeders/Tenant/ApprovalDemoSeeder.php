<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Domain\Access\Models\Role;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Enums\ApproverType;
use App\Domain\Approval\Enums\DecisionMode;
use App\Domain\Approval\Models\ApprovalRule;
use App\Domain\Approval\Models\ApprovalStep;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Aturan approval demo company DEMO — sumber kebenaran: docs/00-akun-uji.md §5.
 * HANYA untuk dev, demo, dan staging.
 *
 * Jenis dokumen yang sudah tersambung ke mesin approval (REQ, RTV, ADJ, OPN,
 * PRQ, PO) diseed.
 * Aman dijalankan berulang (dicari lewat jenis + nama).
 */
class ApprovalDemoSeeder extends Seeder
{
    public const REQ_BESAR = 'REQ besar atau aset';

    public const REQ_LAINNYA = 'REQ lainnya';

    public const RTV = 'RTV — kepala gudang';

    public const ADJ_BESAR = 'ADJ di atas 100 unit';

    public const ADJ_LAINNYA = 'ADJ lainnya';

    public const OPN_AUDIT = 'OPN tahunan & pemeriksaan mendadak';

    public const PRQ_ONLINE = 'PRQ toko online';

    public const PO_BESAR = 'PO bernilai ≥ Rp 50 juta';

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('ApprovalDemoSeeder tidak boleh dijalankan di produksi.');
        }

        $manajemen = Role::findByCode('management');

        if ($manajemen === null) {
            throw new RuntimeException('Role Manajemen belum ada. Jalankan ReferenceSeeder lebih dulu.');
        }

        // §5 baris 1: > 20 baris atau barang aset (A-87) → kepala gudang sumber → Manajemen.
        $this->aturan(ApprovalDocumentType::MaterialRequest, self::REQ_BESAR, 10,
            ['match' => 'any', 'line_count_min' => 21, 'ownership_models' => ['asset']],
            [
                [ApproverType::WarehouseHead, null, DecisionMode::All],
                [ApproverType::Role, $manajemen->id, DecisionMode::Any],
            ],
        );

        // §5 baris 2: REQ lainnya → kepala gudang sumber.
        $this->aturan(ApprovalDocumentType::MaterialRequest, self::REQ_LAINNYA, 100, ['match' => 'all'], [
            [ApproverType::WarehouseHead, null, DecisionMode::All],
        ]);

        // §5 baris RTV: kepala gudang asal, sama dengan perilaku sebelum mesin approval (A-93).
        $this->aturan(ApprovalDocumentType::VendorReturn, self::RTV, 100, ['match' => 'all'], [
            [ApproverType::WarehouseHead, null, DecisionMode::Any],
        ]);

        // §5 baris ADJ manual: kepala gudang → Manajemen bila > 100 unit (A-09, A-105).
        // Tanpa aturan pun ADJ manual tetap satu lapis Kepala Gudang (lapis minimum).
        $this->aturan(ApprovalDocumentType::StockAdjustment, self::ADJ_BESAR, 10,
            ['match' => 'all', 'line_qty_min' => 100.0001],
            [
                [ApproverType::WarehouseHead, null, DecisionMode::Any],
                [ApproverType::Role, $manajemen->id, DecisionMode::Any],
            ],
        );

        $this->aturan(ApprovalDocumentType::StockAdjustment, self::ADJ_LAINNYA, 100, ['match' => 'all'], [
            [ApproverType::WarehouseHead, null, DecisionMode::Any],
        ]);

        // §5 baris OPN: sesi tahunan & pemeriksaan mendadak → Auditor Internal (BR-OPN-09).
        // Sesi bulanan/ad-hoc tanpa aturan memakai lapis minimum Kepala Gudang (A-96).
        $auditor = Role::findByCode('internal_auditor');

        if ($auditor === null) {
            throw new RuntimeException('Role Auditor Internal belum ada. Jalankan ReferenceSeeder lebih dulu.');
        }

        $this->aturan(ApprovalDocumentType::StockCount, self::OPN_AUDIT, 10,
            ['match' => 'all', 'count_types' => ['annual', 'spot_check']],
            [
                [ApproverType::Role, $auditor->id, DecisionMode::Any],
            ],
        );

        // §5 baris PRQ: jenis vendor toko online → kepala gudang tujuan → Manajemen (A-52).
        // PRQ lain tanpa aturan = disetujui otomatis (A-08).
        $this->aturan(ApprovalDocumentType::PurchaseRequest, self::PRQ_ONLINE, 10,
            ['match' => 'all', 'vendor_types' => ['online_marketplace']],
            [
                [ApproverType::WarehouseHead, null, DecisionMode::Any],
                [ApproverType::Role, $manajemen->id, DecisionMode::Any],
            ],
        );

        // §5 baris PO: nilai PO ≥ Rp 50.000.000 → Manajemen (D-28, A-212, A-218).
        // PO lain tanpa aturan = disetujui otomatis (A-08).
        $this->aturan(ApprovalDocumentType::PurchaseOrder, self::PO_BESAR, 10,
            ['match' => 'all', 'order_value_min' => 50000000],
            [
                [ApproverType::Role, $manajemen->id, DecisionMode::Any],
            ],
        );

        $this->command?->info('Aturan approval demo siap: '.ApprovalRule::count().' aturan.');
    }

    /**
     * @param  array<string, mixed>  $conditions
     * @param  array<int, array{0: ApproverType, 1: int|null, 2: DecisionMode}>  $steps
     */
    private function aturan(ApprovalDocumentType $type, string $name, int $priority, array $conditions, array $steps): void
    {
        $rule = ApprovalRule::updateOrCreate(
            ['document_type' => $type->value, 'name' => $name],
            ['priority' => $priority, 'conditions' => $conditions, 'is_active' => true],
        );

        foreach ($steps as $i => [$approver, $ref, $mode]) {
            ApprovalStep::updateOrCreate(
                ['approval_rule_id' => $rule->id, 'step_no' => $i + 1],
                [
                    'approver_type' => $approver->value,
                    'approver_ref_id' => $ref,
                    'decision_mode' => $mode->value,
                    'timeout_hours' => 24,
                    'channel' => 'web',
                    'require_pin' => false,
                ],
            );
        }
    }
}
