<?php

declare(strict_types=1);

namespace Tests\Feature\Approval\Concerns;

use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\User;
use App\Domain\Approval\Actions\SaveApprovalRule;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Enums\ApproverType;
use App\Domain\Approval\Enums\DecisionMode;
use App\Domain\Approval\Models\ApprovalRule;

/**
 * Bahan uji aturan approval. Aturan dibuat lewat aksi sungguhan
 * (`SaveApprovalRule`) supaya validasinya ikut teruji.
 */
trait ApprovalFixtures
{
    /**
     * @param  array<int, array<string, mixed>>  $steps
     * @param  array<string, mixed>  $conditions
     */
    protected function aturan(
        ApprovalDocumentType $type,
        array $steps,
        array $conditions = [],
        int $priority = 100,
        string $name = 'Aturan uji',
    ): ApprovalRule {
        return app(SaveApprovalRule::class)->handle(null, [
            'document_type' => $type->value,
            'name' => $name,
            'priority' => $priority,
            'is_active' => true,
            'conditions' => $conditions,
        ], $steps);
    }

    /** @return array<string, mixed> */
    protected function lapisUser(User $user, DecisionMode $mode = DecisionMode::Any, array $extra = []): array
    {
        return $extra + [
            'approver_type' => ApproverType::User->value,
            'approver_ref_id' => $user->id,
            'decision_mode' => $mode->value,
            'timeout_hours' => 24,
        ];
    }

    /** @return array<string, mixed> */
    protected function lapisRole(string $roleCode, DecisionMode $mode = DecisionMode::Any, array $extra = []): array
    {
        return $extra + [
            'approver_type' => ApproverType::Role->value,
            'approver_ref_id' => Role::findByCode($roleCode)->id,
            'decision_mode' => $mode->value,
            'timeout_hours' => 24,
        ];
    }

    /** @return array<string, mixed> */
    protected function lapis(ApproverType $type, DecisionMode $mode = DecisionMode::Any, array $extra = []): array
    {
        return $extra + [
            'approver_type' => $type->value,
            'approver_ref_id' => null,
            'decision_mode' => $mode->value,
            'timeout_hours' => 24,
        ];
    }
}
