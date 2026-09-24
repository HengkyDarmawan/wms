<?php

declare(strict_types=1);

namespace App\Domain\Approval\Livewire;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Actions\SimulateApproval;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Livewire\Concerns\HandlesApprovalRules;
use App\Domain\Approval\Support\ApprovalContext;
use App\Domain\Approval\Support\ApprovalRegistry;
use App\Domain\Master\Enums\OwnershipModel;
use App\Domain\Master\Enums\VendorType;
use App\Domain\Master\Models\ItemCategory;
use App\Domain\Master\Models\Project;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Layar 20-approval §6.5 — simulasi "siapa yang akan menyetujui dokumen ini?"
 * atas aturan tersimpan (BR-APR-11): dari dokumen nyata (nomor) atau dari
 * data rekaan (gudang, proyek, kategori, jumlah, pemohon). Tidak menulis apa pun.
 */
class ApprovalSimulation extends Component
{
    use HandlesApprovalRules;

    public string $documentType = '';

    /** 'dokumen' | 'manual' */
    public string $source = 'dokumen';

    public string $number = '';

    /** @var array<string, mixed> */
    public array $manual = [
        'warehouse_ids' => [],
        'project_id' => '',
        'category_ids' => [],
        'ownership_models' => [],
        'line_count' => '1',
        'max_line_qty' => '1',
        'from_client' => false,
        'vendor_type' => '',
        'requester_id' => '',
    ];

    /** @var array<string, mixed>|null */
    public ?array $hasil = null;

    public function mount(): void
    {
        $this->authorize('approval.simulate');
        $this->documentType = array_key_first(app(ApprovalRegistry::class)->typeOptions()) ?? '';
    }

    public function render(): View
    {
        return view('livewire.approval.simulation', [
            'types' => app(ApprovalRegistry::class)->typeOptions(),
            'warehouses' => Warehouse::withoutGlobalScopes()->orderBy('code')->get(['id', 'code', 'name']),
            'projects' => Project::query()->orderBy('code')->get(['id', 'code', 'name']),
            'categories' => ItemCategory::query()->orderBy('code')->get(['id', 'code', 'name']),
            'ownerships' => OwnershipModel::options(),
            'vendorTypes' => VendorType::options(),
            'users' => User::query()->active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function simulasikan(SimulateApproval $action): void
    {
        $this->authorize('approval.simulate');
        $this->hasil = null;

        $jenis = ApprovalDocumentType::tryFrom($this->documentType);

        if ($jenis === null) {
            return;
        }

        $this->jalankan(function () use ($action, $jenis) {
            $ctx = $this->source === 'dokumen'
                ? $action->contextForDocument($jenis, $this->number)
                : $this->konteksManual($jenis);

            $this->hasil = $action->run($ctx);
            $this->hasil['document'] = $ctx->documentNumber;
        }, 'sim');
    }

    private function konteksManual(ApprovalDocumentType $jenis): ApprovalContext
    {
        $pemohon = (int) ($this->manual['requester_id'] ?? 0) ?: null;

        return new ApprovalContext(
            documentType: $jenis,
            warehouseIds: array_map('intval', (array) $this->manual['warehouse_ids']),
            projectId: ($p = (int) ($this->manual['project_id'] ?? 0)) > 0 ? $p : null,
            categoryIds: ApprovalContext::withAncestors(array_map('intval', (array) $this->manual['category_ids'])),
            ownershipModels: array_values((array) $this->manual['ownership_models']),
            lineCount: max(0, (int) $this->manual['line_count']),
            maxLineQty: max(0.0, (float) $this->manual['max_line_qty']),
            fromClient: (bool) $this->manual['from_client'],
            vendorType: ($this->manual['vendor_type'] ?? '') ?: null,
            requesterId: $pemohon,
            requesterIds: $pemohon !== null ? [$pemohon] : [],
        );
    }
}
