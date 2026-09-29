<?php

declare(strict_types=1);

namespace App\Domain\Approval\Support;

use App\Domain\Access\Models\Position;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\User;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Enums\ApproverType;
use App\Domain\Approval\Enums\ConditionMatch;
use App\Domain\Approval\Models\ApprovalRule;
use App\Domain\Count\Enums\CountType;
use App\Domain\Master\Enums\OwnershipModel;
use App\Domain\Master\Enums\VendorType;
use App\Domain\Master\Models\ItemCategory;
use App\Domain\Master\Models\Project;
use App\Domain\PurchaseRequest\Enums\PurchaseRequestOrigin;
use App\Domain\Warehouse\Models\Warehouse;

/**
 * Aturan approval dalam **kalimat awam** (A-348): "Permintaan material di
 * gudang CKG disetujui Kepala gudang terkait, lalu Manajemen. Lewat 24 jam
 * naik ke atasan si approver." Dipakai daftar aturan, Peta approval, dan
 * ringkasan form aturan, sehingga ketiganya selalu berbunyi sama.
 *
 * Hanya membaca; nilai uang hanya muncul untuk kondisi nilai PO (D-28).
 */
class ApprovalRuleSentence
{
    /**
     * Lapis minimum bawaan kode saat jenis itu tanpa aturan (penangan
     * `fallbackSteps`, A-09/A-96/A-150). Jenis lain tanpa aturan = otomatis.
     */
    public const LAPIS_MINIMUM = [
        'stock_adjustment' => 'Kepala gudang terkait (cadangan Manajemen)',
        'stock_count' => 'Kepala gudang terkait; sesi audit ke Auditor Internal (cadangan Manajemen)',
        'material_issue' => 'pembalikan pemakaian: Kepala gudang Gudang Site (cadangan Manajemen)',
    ];

    /** @var array<string, string|null> */
    private array $nama = [];

    public function aturan(ApprovalRule $rule): string
    {
        return $this->kalimat(
            $rule->document_type,
            $rule->conditions ?? [],
            $rule->steps->map->toPlanInput()->all(),
        );
    }

    /**
     * @param  array<string, mixed>  $kondisi
     * @param  array<int, array<string, mixed>>  $lapis
     */
    public function kalimat(ApprovalDocumentType $jenis, array $kondisi, array $lapis): string
    {
        $lapis = array_values(array_filter($lapis, fn (array $l) => ($l['approver_type'] ?? '') !== ''));
        $subjek = $jenis->label();
        $syarat = $this->kondisi($jenis, $kondisi);

        if ($lapis === []) {
            return trim($subjek.' '.$syarat).': '.__('belum ada approver.');
        }

        $siapa = implode(', lalu ', array_map(fn (array $l) => $this->approver($l), $lapis));
        $jam = (int) ($lapis[0]['timeout_hours'] ?? 24) ?: 24;
        $cadangan = ($lapis[0]['backup_approver_type'] ?? '') !== ''
            ? $this->approver(['approver_type' => $lapis[0]['backup_approver_type'], 'approver_ref_id' => $lapis[0]['backup_ref_id'] ?? null])
            : __('atasan si approver');

        return trim($subjek.' '.$syarat).' '.__('disetujui').' '.$siapa.'. '
            .__('Lewat :jam jam naik ke :siapa.', ['jam' => $jam, 'siapa' => $cadangan]);
    }

    /** Jenis dokumen tanpa aturan aktif. */
    public function tanpaAturan(ApprovalDocumentType $jenis): string
    {
        $minimum = self::LAPIS_MINIMUM[$jenis->value] ?? null;

        return $minimum === null
            ? $jenis->label().' → '.__('tidak ada aturan, disetujui otomatis.')
            : $jenis->label().' → '.__('tidak ada aturan; dijaga lapis minimum sistem:').' '.$minimum.'.';
    }

    /** @param  array<string, mixed>  $lapis */
    public function approver(array $lapis): string
    {
        $jenis = ApproverType::tryFrom((string) ($lapis['approver_type'] ?? ''));
        $ref = isset($lapis['approver_ref_id']) && $lapis['approver_ref_id'] !== '' ? (int) $lapis['approver_ref_id'] : null;

        return match ($jenis) {
            ApproverType::DirectManager => ApproverResolver::tingkat($lapis['manager_levels'] ?? 1) === 2
                ? __('atasan pemohon 2 tingkat di atas')
                : __('atasan langsung pemohon'),
            ApproverType::WarehouseHead => __('Kepala gudang terkait'),
            ApproverType::ProjectPic => __('PIC proyek'),
            ApproverType::Role => $this->namaDari('role', $ref) ?? __('peran tertentu'),
            ApproverType::Position => __('pemegang jabatan').' '.($this->namaDari('position', $ref) ?? '…'),
            ApproverType::User => $this->namaDari('user', $ref) ?? __('orang tertentu'),
            null => '…',
        };
    }

    /** @param  array<string, mixed>  $kondisi */
    public function kondisi(ApprovalDocumentType $jenis, array $kondisi): string
    {
        $bagian = [];
        $kode = fn (string $kelas, array $ids) => $kelas::query()->withoutGlobalScopes()->whereIn('id', array_map('intval', $ids))->orderBy('code')->pluck('code')->implode(', ');

        if (! empty($kondisi['warehouse_ids'])) {
            $bagian[] = __('di gudang').' '.$kode(Warehouse::class, (array) $kondisi['warehouse_ids']);
        }

        if (! empty($kondisi['project_ids'])) {
            $bagian[] = __('untuk proyek').' '.$kode(Project::class, (array) $kondisi['project_ids']);
        }

        if (! empty($kondisi['category_ids'])) {
            $bagian[] = __('berisi kategori').' '.$kode(ItemCategory::class, (array) $kondisi['category_ids']);
        }

        if (! empty($kondisi['ownership_models'])) {
            $bagian[] = __('berisi').' '.collect((array) $kondisi['ownership_models'])
                ->map(fn ($v) => mb_strtolower(OwnershipModel::tryFrom((string) $v)?->label() ?? (string) $v))->implode(' / ');
        }

        if (($kondisi['line_count_min'] ?? '') !== '' && $kondisi['line_count_min'] !== null) {
            $bagian[] = __('dengan :n baris atau lebih', ['n' => (int) $kondisi['line_count_min']]);
        }

        if (($kondisi['line_qty_min'] ?? '') !== '' && $kondisi['line_qty_min'] !== null) {
            $bagian[] = __('dengan jumlah per baris :n atau lebih', ['n' => rtrim(rtrim(number_format((float) $kondisi['line_qty_min'], 4, ',', '.'), '0'), ',')]);
        }

        if (array_key_exists('from_client', $kondisi) && $kondisi['from_client'] !== '' && $kondisi['from_client'] !== null) {
            $bagian[] = filter_var($kondisi['from_client'], FILTER_VALIDATE_BOOLEAN) ? __('dari klien') : __('dari internal');
        }

        if (! empty($kondisi['vendor_types'])) {
            $bagian[] = __('ke vendor jenis').' '.collect((array) $kondisi['vendor_types'])
                ->map(fn ($v) => VendorType::tryFrom((string) $v)?->label() ?? (string) $v)->implode(' / ');
        }

        if (! empty($kondisi['purchase_request_origins'])) {
            $bagian[] = __('berasal dari').' '.collect((array) $kondisi['purchase_request_origins'])
                ->map(fn ($v) => mb_strtolower(PurchaseRequestOrigin::tryFrom((string) $v)?->label() ?? (string) $v))->implode(' / ');
        }

        if (! empty($kondisi['count_types'])) {
            $bagian[] = __('berjenis').' '.collect((array) $kondisi['count_types'])
                ->map(fn ($v) => mb_strtolower(CountType::tryFrom((string) $v)?->label() ?? (string) $v))->implode(' / ');
        }

        if ($jenis === ApprovalDocumentType::PurchaseOrder && ($kondisi['order_value_min'] ?? '') !== '' && $kondisi['order_value_min'] !== null) {
            $bagian[] = __('bernilai Rp :n atau lebih', ['n' => number_format((float) $kondisi['order_value_min'], 0, ',', '.')]); // D-28
        }

        $sambung = ($kondisi['match'] ?? ConditionMatch::All->value) === ConditionMatch::Any->value ? ' '.__('atau').' ' : ' '.__('dan').' ';

        return implode($sambung, $bagian);
    }

    private function namaDari(string $jenis, ?int $id): ?string
    {
        if ($id === null) {
            return null;
        }

        $kunci = $jenis.':'.$id;

        return $this->nama[$kunci] ??= match ($jenis) {
            'role' => Role::query()->whereKey($id)->value('name'),
            'position' => Position::query()->whereKey($id)->value('name'),
            default => User::query()->whereKey($id)->value('name'),
        };
    }
}
