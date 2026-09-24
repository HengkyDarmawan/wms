<?php

declare(strict_types=1);

namespace App\Domain\Transfer\Enums;

use App\Domain\Warehouse\Models\Warehouse;

/**
 * Jenis transfer untuk tampilan — **turunan**, bukan kolom (Glosarium: TRF antar
 * gudang, antar proyek, atau antar Gudang Site dalam satu proyek, A-50).
 */
enum TransferKind: string
{
    case BetweenWarehouses = 'between_warehouses';
    case BetweenProjects = 'between_projects';
    case WithinProject = 'within_project';

    public function label(): string
    {
        return match ($this) {
            self::BetweenWarehouses => 'Antar gudang',
            self::BetweenProjects => 'Antar proyek',
            self::WithinProject => 'Dalam proyek (antar titik)',
        };
    }

    public static function between(?int $fromProjectId, ?int $toProjectId): self
    {
        if ($fromProjectId !== null && $fromProjectId === $toProjectId) {
            return self::WithinProject;
        }

        if ($fromProjectId !== null || $toProjectId !== null) {
            return self::BetweenProjects;
        }

        return self::BetweenWarehouses;
    }

    public static function forWarehouses(Warehouse $from, Warehouse $to): self
    {
        return self::between(
            $from->project_id !== null ? (int) $from->project_id : null,
            $to->project_id !== null ? (int) $to->project_id : null,
        );
    }
}
