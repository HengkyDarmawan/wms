<?php

declare(strict_types=1);

namespace App\Domain\Approval\Support;

use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Master\Models\ItemCategory;

/**
 * Data satu dokumen yang dicocokkan dengan kondisi aturan dan dipakai untuk
 * menentukan approver (BR-APR-07). Tidak ada nilai uang di sini (D-07);
 * kondisi berbasis nilai hanya milik modul Purchasing (D-28).
 *
 * Disimpan ke `approval_snapshots.context` saat diajukan.
 */
final class ApprovalContext
{
    /**
     * @param  array<int, int>  $warehouseIds  gudang dokumen (gudang sumber/asal)
     * @param  array<int, int>  $categoryIds  kategori item beserta induknya
     * @param  array<int, string>  $ownershipModels  model kepemilikan item
     * @param  array<int, int>  $requesterIds  pemohon/pengaju — tidak boleh memutus (BR-APR-03)
     */
    public function __construct(
        public readonly ApprovalDocumentType $documentType,
        public readonly ?int $documentId = null,
        public readonly ?string $documentNumber = null,
        public readonly array $warehouseIds = [],
        public readonly ?int $projectId = null,
        public readonly array $categoryIds = [],
        public readonly array $ownershipModels = [],
        public readonly int $lineCount = 0,
        public readonly float $maxLineQty = 0.0,
        public readonly bool $fromClient = false,
        public readonly ?string $vendorType = null,
        public readonly ?string $purchaseRequestOrigin = null,
        public readonly ?string $countType = null,
        public readonly ?int $requesterId = null,
        public readonly array $requesterIds = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'document_type' => $this->documentType->value,
            'document_id' => $this->documentId,
            'document_number' => $this->documentNumber,
            'warehouse_ids' => array_values(array_unique(array_map('intval', $this->warehouseIds))),
            'project_id' => $this->projectId,
            'category_ids' => array_values(array_unique(array_map('intval', $this->categoryIds))),
            'ownership_models' => array_values(array_unique($this->ownershipModels)),
            'line_count' => $this->lineCount,
            'max_line_qty' => $this->maxLineQty,
            'from_client' => $this->fromClient,
            'vendor_type' => $this->vendorType,
            'purchase_request_origin' => $this->purchaseRequestOrigin,
            'count_type' => $this->countType,
            'requester_id' => $this->requesterId,
            'requester_ids' => array_values(array_unique(array_map('intval', array_filter($this->requesterIds)))),
        ];
    }

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            documentType: ApprovalDocumentType::from((string) $data['document_type']),
            documentId: isset($data['document_id']) ? (int) $data['document_id'] : null,
            documentNumber: $data['document_number'] ?? null,
            warehouseIds: array_map('intval', $data['warehouse_ids'] ?? []),
            projectId: isset($data['project_id']) && $data['project_id'] !== '' ? (int) $data['project_id'] : null,
            categoryIds: array_map('intval', $data['category_ids'] ?? []),
            ownershipModels: array_values($data['ownership_models'] ?? []),
            lineCount: (int) ($data['line_count'] ?? 0),
            maxLineQty: (float) ($data['max_line_qty'] ?? 0),
            fromClient: (bool) ($data['from_client'] ?? false),
            vendorType: ($data['vendor_type'] ?? null) ?: null,
            purchaseRequestOrigin: ($data['purchase_request_origin'] ?? null) ?: null,
            countType: ($data['count_type'] ?? null) ?: null,
            requesterId: isset($data['requester_id']) && $data['requester_id'] !== '' ? (int) $data['requester_id'] : null,
            requesterIds: array_map('intval', $data['requester_ids'] ?? []),
        );
    }

    /**
     * Kategori item beserta seluruh induknya: kondisi "kategori Material"
     * juga mengena item berkategori Pipa (anak Material).
     *
     * @param  iterable<int|null>  $categoryIds
     * @return array<int, int>
     */
    public static function withAncestors(iterable $categoryIds): array
    {
        $hasil = [];
        $induk = ItemCategory::query()->pluck('parent_id', 'id')->all();

        foreach ($categoryIds as $id) {
            $langkah = 0;

            while ($id !== null && ! in_array((int) $id, $hasil, true) && $langkah < 20) {
                $hasil[] = (int) $id;
                $id = $induk[$id] ?? null;
                $langkah++;
            }
        }

        return $hasil;
    }
}
