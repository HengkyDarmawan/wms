<?php

declare(strict_types=1);

namespace App\Domain\Shared\Reports\Concerns;

use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Models\ApprovalSnapshot;

/**
 * Cakupan laporan approval (BR-ACC-05).
 *
 * Tabel approval tidak bergudang sendiri, jadi tidak punya global scope.
 * Gudang dokumen dibaca dari `approval_snapshots.context.warehouse_ids`
 * (gudang sumber/asal yang dicocokkan aturan, BR-APR-07): snapshot terlihat
 * bila salah satu gudangnya ada di cakupan user — atau di gudang yang dipilih
 * pada penyaring. User bercakupan semua gudang melihat semuanya.
 */
trait ApprovalScope
{
    use PeriodFilter;

    /** @return array{label: string, options: array<string, string>} */
    protected function penyaringJenisDokumen(): array
    {
        return ['label' => 'Jenis dokumen', 'options' => collect(ApprovalDocumentType::cases())
            ->mapWithKeys(fn (ApprovalDocumentType $t) => [$t->value => $t->longLabel()])->all()];
    }

    /** @param  array<string, mixed>  $filters */
    protected function jenisDipilih(array $filters): ?ApprovalDocumentType
    {
        return ApprovalDocumentType::tryFrom((string) ($filters['document_type'] ?? ''));
    }

    /** @param  array<int, int>|null  $gudang  hasil `gudangDipilih()` */
    protected function snapshotTerlihat(?ApprovalSnapshot $snapshot, ?array $gudang): bool
    {
        if ($snapshot === null) {
            return false;
        }

        if ($gudang === null) {
            return true;
        }

        $milik = array_map('intval', (array) ($snapshot->context['warehouse_ids'] ?? []));

        return array_intersect($milik, $gudang) !== [];
    }

    protected function labelJenis(?ApprovalSnapshot $snapshot): string
    {
        return $snapshot?->document_type?->longLabel() ?? '—';
    }
}
