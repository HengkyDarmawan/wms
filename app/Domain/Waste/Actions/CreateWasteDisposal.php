<?php

declare(strict_types=1);

namespace App\Domain\Waste\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Support\ApprovalEngine;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Stock\Support\DocumentNumber;
use App\Domain\Warehouse\Enums\BinStatus;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Waste\Enums\WasteDisposalStatus;
use App\Domain\Waste\Enums\WasteDisposition;
use App\Domain\Waste\Exceptions\WasteRuleException;
use App\Domain\Waste\Models\WasteDisposal;
use App\Domain\Waste\Models\WasteDisposalLine;
use App\Domain\Waste\Support\DisposableWaste;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `waste.create` — WST baru `submitted`, lalu otomatis
 * `pending_approval` (ada aturan) atau `approved` (tanpa aturan, A-08)
 * (Katalog §2.14).
 *
 * Guard: gudang aktif dalam cakupan, proyek wajib (BR §1; Gudang Site hanya
 * untuk proyek pemiliknya), baris dari isi bin Waste gudang itu, disposisi
 * `disposed`/`sold_scrap`/`reused` — dipakai ulang menuntut bin penyimpanan
 * tujuan (A-159). Stok belum bergerak sampai WST ditutup.
 */
class CreateWasteDisposal
{
    public function __construct(
        private readonly DisposableWaste $waste,
        private readonly ApprovalEngine $approval,
        private readonly DocumentNumber $nomor,
    ) {}

    /**
     * @param  array{warehouse_id?: mixed, project_id?: mixed, disposition?: mixed, target_bin_id?: mixed, notes?: ?string}  $header
     * @param  array<int, array<string, mixed>>  $lines  key, qty_base, reason_code_id
     */
    public function handle(array $header, array $lines, ?User $actor = null): WasteDisposal
    {
        [$gudang, $proyek] = $this->tujuan($header, $actor);
        $disposisi = WasteDisposition::tryFrom((string) ($header['disposition'] ?? ''))
            ?? throw WasteRuleException::field('BR-GEN-01', 'disposition', 'Disposisi wajib dipilih: dibuang, dijual scrap, atau dipakai ulang.');
        $binTujuan = $disposisi->returnsToStock() ? $this->binTujuan($gudang, $header['target_bin_id'] ?? null) : null;
        $baris = $this->waste->normalize($gudang, $lines);

        foreach ($baris as $b) {
            if ($b['reason_code_id'] !== null && ! ReasonCode::query()->whereKey($b['reason_code_id'])->where('context', ReasonContext::Waste->value)->exists()) {
                throw WasteRuleException::field('BR-GEN-02', 'lines', 'Alasan baris harus dari master Alasan konteks Waste.');
            }
        }

        return DB::transaction(function () use ($gudang, $proyek, $disposisi, $binTujuan, $baris, $header, $actor) {
            $wst = WasteDisposal::create([
                'number' => $this->nomor->next('WST', (string) $gudang->code),
                'warehouse_id' => $gudang->id,
                'project_id' => $proyek->id,
                'disposition' => $disposisi,
                'target_bin_id' => $binTujuan?->id,
                'status' => WasteDisposalStatus::Submitted,
                'submitted_by' => $actor?->id,
                'notes' => $this->teks($header['notes'] ?? null),
            ]);

            foreach ($baris as $b) {
                WasteDisposalLine::create($b + ['waste_disposal_id' => $wst->id]);
            }

            activity('waste')->performedOn($wst)->causedBy($actor)
                ->withProperties(['baris' => count($baris), 'disposisi' => $disposisi->value])
                ->log('BA waste diajukan');

            // Katalog §2.14: submitted → pending_approval / approved otomatis sesuai aturan.
            $wst->forceFill(['status' => WasteDisposalStatus::PendingApproval])->save();
            $this->approval->submit(ApprovalDocumentType::WasteDisposal, $wst, $actor);

            return $wst->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $header
     * @return array{0: Warehouse, 1: Project}
     */
    private function tujuan(array $header, ?User $actor): array
    {
        $gudang = Warehouse::query()->withoutGlobalScopes()->with('type')
            ->find(is_numeric($header['warehouse_id'] ?? null) ? (int) $header['warehouse_id'] : 0);

        if ($gudang === null) {
            throw WasteRuleException::field('BR-STK-02', 'warehouse_id', 'Gudang wajib dipilih.');
        }

        if (! $gudang->is_active) {
            throw WasteRuleException::field('BR-WH-07', 'warehouse_id', 'Gudang '.$gudang->code.' nonaktif.');
        }

        if ($actor !== null && ! $actor->canAccessWarehouse((int) $gudang->id)) {
            throw WasteRuleException::field('BR-ACC-05', 'warehouse_id', 'Gudang '.$gudang->code.' di luar cakupan Anda.');
        }

        $proyek = Project::query()->find(is_numeric($header['project_id'] ?? null) ? (int) $header['project_id'] : 0);

        if ($proyek === null) {
            throw WasteRuleException::field('BR-CNV-01', 'project_id', 'Proyek wajib dipilih (Proyek Internal untuk waste gudang).');
        }

        if ($actor !== null && ! $actor->canAccessProject((int) $proyek->id)) {
            throw WasteRuleException::field('BR-ACC-05', 'project_id', 'Proyek '.$proyek->code.' di luar cakupan Anda.');
        }

        if (! $proyek->acceptsDocuments()) {
            throw WasteRuleException::field('BR-PRJ-01', 'project_id', 'Proyek '.$proyek->code.' tidak aktif; dokumen baru ditolak.');
        }

        if ($gudang->isSite() && (int) $gudang->project_id !== (int) $proyek->id) {
            throw WasteRuleException::field('BR-CNV-01', 'project_id', 'Gudang Site '.$gudang->code.' milik proyek lain.');
        }

        return [$gudang, $proyek];
    }

    private function binTujuan(Warehouse $gudang, mixed $binId): Bin
    {
        $bin = Bin::query()->withoutGlobalScopes()
            ->whereKey(is_numeric($binId) ? (int) $binId : 0)
            ->where('warehouse_id', $gudang->id)
            ->where('bin_type', BinType::Storage->value)
            ->where('bin_status', BinStatus::Active->value)
            ->first();

        if ($bin === null) {
            throw WasteRuleException::field('BR-STK-02', 'target_bin_id', 'Dipakai ulang: pilih bin penyimpanan aktif di gudang '.$gudang->code.'.');
        }

        return $bin;
    }

    private function teks(mixed $nilai): ?string
    {
        $isi = is_scalar($nilai) ? trim((string) $nilai) : '';

        return $isi === '' ? null : mb_substr($isi, 0, 255);
    }
}
