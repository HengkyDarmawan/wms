<?php

declare(strict_types=1);

namespace App\Domain\Approval\Support;

use App\Domain\Approval\Enums\ConditionMatch;

/**
 * Mencocokkan kondisi aturan dengan data dokumen (BR-APR-07, Blueprint §8.1).
 *
 * Kondisi yang kosong diabaikan; aturan tanpa kondisi berlaku untuk semua
 * dokumen jenisnya ("lainnya"). Tidak ada kondisi nilai uang (D-07). Jumlah
 * dibandingkan dalam satuan dasar per baris, atau jumlah baris.
 */
class ConditionMatcher
{
    /** @var array<string, string> kunci kondisi => label UI */
    public const KEYS = [
        'warehouse_ids' => 'Gudang',
        'project_ids' => 'Proyek',
        'category_ids' => 'Kategori barang',
        'ownership_models' => 'Model kepemilikan',
        'line_count_min' => 'Jumlah baris ≥',
        'line_qty_min' => 'Jumlah satuan dasar per baris ≥',
        'from_client' => 'Permintaan dari klien',
        'vendor_types' => 'Jenis vendor',
        'purchase_request_origins' => 'Asal PRQ',
        'count_types' => 'Jenis opname',
    ];

    /**
     * Membersihkan isian form menjadi bentuk yang disimpan: kunci tak dikenal
     * dibuang, daftar kosong dibuang, angka dijadikan angka.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    public static function normalize(array $raw): array
    {
        $hasil = ['match' => ConditionMatch::tryFrom((string) ($raw['match'] ?? ''))?->value ?? ConditionMatch::All->value];

        foreach (['warehouse_ids', 'project_ids', 'category_ids'] as $k) {
            $ids = array_values(array_unique(array_map('intval', array_filter((array) ($raw[$k] ?? []), fn ($v) => $v !== '' && $v !== null))));
            if ($ids !== []) {
                $hasil[$k] = $ids;
            }
        }

        foreach (['ownership_models', 'vendor_types', 'purchase_request_origins', 'count_types'] as $k) {
            $nilai = array_values(array_unique(array_map('strval', array_filter((array) ($raw[$k] ?? []), fn ($v) => $v !== '' && $v !== null))));
            if ($nilai !== []) {
                $hasil[$k] = $nilai;
            }
        }

        foreach (['line_count_min', 'line_qty_min'] as $k) {
            if (isset($raw[$k]) && $raw[$k] !== '' && $raw[$k] !== null && is_numeric($raw[$k]) && (float) $raw[$k] > 0) {
                $hasil[$k] = $k === 'line_count_min' ? (int) $raw[$k] : round((float) $raw[$k], 4);
            }
        }

        if (isset($raw['from_client']) && $raw['from_client'] !== '' && $raw['from_client'] !== null) {
            $hasil['from_client'] = filter_var($raw['from_client'], FILTER_VALIDATE_BOOLEAN);
        }

        return $hasil;
    }

    /**
     * @param  array<string, mixed>|null  $conditions
     * @return array{matched: bool, checks: array<int, array{key: string, label: string, ok: bool}>}
     */
    public function evaluate(?array $conditions, ApprovalContext $ctx): array
    {
        $conditions = self::normalize($conditions ?? []);
        $mode = ConditionMatch::from($conditions['match']);
        unset($conditions['match']);

        $checks = [];

        foreach ($conditions as $key => $nilai) {
            $checks[] = ['key' => $key, 'label' => self::KEYS[$key] ?? $key, 'ok' => $this->check($key, $nilai, $ctx)];
        }

        if ($checks === []) {
            return ['matched' => true, 'checks' => []];
        }

        $lolos = array_filter($checks, fn (array $c) => $c['ok']);

        $matched = $mode === ConditionMatch::All
            ? count($lolos) === count($checks)
            : $lolos !== [];

        return ['matched' => $matched, 'checks' => $checks];
    }

    private function check(string $key, mixed $nilai, ApprovalContext $ctx): bool
    {
        return match ($key) {
            'warehouse_ids' => array_intersect($nilai, $ctx->warehouseIds) !== [],
            'project_ids' => $ctx->projectId !== null && in_array($ctx->projectId, $nilai, true),
            'category_ids' => array_intersect($nilai, $ctx->categoryIds) !== [],
            'ownership_models' => array_intersect($nilai, $ctx->ownershipModels) !== [],
            'line_count_min' => $ctx->lineCount >= (int) $nilai,
            'line_qty_min' => $ctx->maxLineQty + 0.00005 >= (float) $nilai,
            'from_client' => $ctx->fromClient === (bool) $nilai,
            'vendor_types' => $ctx->vendorType !== null && in_array($ctx->vendorType, $nilai, true),
            'purchase_request_origins' => $ctx->purchaseRequestOrigin !== null && in_array($ctx->purchaseRequestOrigin, $nilai, true),
            'count_types' => $ctx->countType !== null && in_array($ctx->countType, $nilai, true),
            default => false,
        };
    }
}
