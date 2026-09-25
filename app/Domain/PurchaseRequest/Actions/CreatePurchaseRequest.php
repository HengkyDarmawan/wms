<?php

declare(strict_types=1);

namespace App\Domain\PurchaseRequest\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Master\Models\Project;
use App\Domain\PurchaseRequest\Enums\PurchaseRequestOrigin;
use App\Domain\PurchaseRequest\Enums\PurchaseRequestStatus;
use App\Domain\PurchaseRequest\Exceptions\PurchaseRequestRuleException;
use App\Domain\PurchaseRequest\Models\PurchaseRequest;
use App\Domain\PurchaseRequest\Models\PurchaseRequestLine;
use App\Domain\PurchaseRequest\Support\PurchaseLines;
use App\Domain\PurchaseRequest\Support\PurchaseRequestFlow;
use App\Domain\Stock\Support\DocumentNumber;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `pr.create` — PRQ manual langsung `submitted` (Katalog §2.15
 * "— → submitted otomatis / pr.create"), lalu `pending_approval`/`approved`.
 *
 * Guard: gudang tujuan aktif dalam cakupan; proyek opsional (bila diisi harus
 * aktif & dalam cakupan); item terdefinisi (A-51).
 *
 * `update()` (`pr.submit`) meninjau **draf** titik pesan ulang sebelum
 * diajukan (BR-REQ-11): jumlah, tanggal, dan keterangan baris boleh diubah.
 */
class CreatePurchaseRequest
{
    public function __construct(
        private readonly PurchaseLines $lines,
        private readonly PurchaseRequestFlow $flow,
        private readonly DocumentNumber $nomor,
    ) {}

    /**
     * @param  array{warehouse_id?: mixed, project_id?: mixed, notes?: mixed}  $header
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function handle(array $header, array $lines, ?User $actor = null): PurchaseRequest
    {
        $gudang = Warehouse::query()->find(is_numeric($header['warehouse_id'] ?? null) ? (int) $header['warehouse_id'] : 0);

        if ($gudang === null) {
            throw PurchaseRequestRuleException::field('BR-ACC-05', 'warehouse_id', 'Gudang tujuan wajib dipilih dari gudang dalam cakupan Anda.');
        }

        if (! $gudang->is_active) {
            throw PurchaseRequestRuleException::field('BR-WH-07', 'warehouse_id', 'Gudang '.$gudang->code.' nonaktif.');
        }

        $proyek = $this->proyek($header['project_id'] ?? null, $actor);
        $baris = $this->lines->normalize($lines);

        return DB::transaction(function () use ($gudang, $proyek, $baris, $header, $actor) {
            $prq = PurchaseRequest::create([
                'number' => $this->nomor->next('PRQ', 'ALL'),
                'warehouse_id' => $gudang->id,
                'project_id' => $proyek?->id,
                'origin' => PurchaseRequestOrigin::Manual,
                'status' => PurchaseRequestStatus::Draft,
                'created_by' => $actor?->id,
                'notes' => $this->teks($header['notes'] ?? null),
            ]);

            foreach ($baris as $b) {
                PurchaseRequestLine::create($b + ['purchase_request_id' => $prq->id]);
            }

            activity('purchase_request')->performedOn($prq)->causedBy($actor)
                ->withProperties(['baris' => count($baris)])->log('PRQ manual dibuat');

            return $this->flow->submit($prq, $actor);
        });
    }

    /**
     * Tinjau draf titik pesan ulang (BR-REQ-11).
     *
     * @param  array<string, mixed>  $header
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function update(PurchaseRequest $prq, array $header, array $lines, ?User $actor = null): PurchaseRequest
    {
        if ($prq->status !== PurchaseRequestStatus::Draft) {
            throw PurchaseRequestRuleException::rule('BR-GEN-01', 'Hanya PRQ berstatus Draf yang bisa diubah.');
        }

        if ($actor !== null && ! $actor->canAccessWarehouse((int) $prq->warehouse_id)) {
            throw PurchaseRequestRuleException::rule('BR-ACC-05', 'Gudang PRQ ini di luar cakupan Anda.');
        }

        $baris = $this->lines->normalize($lines);

        return DB::transaction(function () use ($prq, $baris, $header, $actor) {
            $prq->lines()->delete();

            foreach ($baris as $b) {
                PurchaseRequestLine::create($b + ['purchase_request_id' => $prq->id]);
            }

            $prq->forceFill(['notes' => $this->teks($header['notes'] ?? null) ?? $prq->notes])->save();

            activity('purchase_request')->performedOn($prq)->causedBy($actor)
                ->withProperties(['baris' => count($baris)])->log('Draf PRQ ditinjau');

            return $prq->refresh();
        });
    }

    private function proyek(mixed $id, ?User $actor): ?Project
    {
        if (! is_numeric($id) || (int) $id <= 0) {
            return null;
        }

        $proyek = Project::query()->find((int) $id)
            ?? throw PurchaseRequestRuleException::field('BR-ACC-05', 'project_id', 'Proyek tidak ditemukan dalam cakupan Anda.');

        if ($actor !== null && ! $actor->canAccessProject((int) $proyek->id)) {
            throw PurchaseRequestRuleException::field('BR-ACC-05', 'project_id', 'Proyek '.$proyek->code.' di luar cakupan Anda.');
        }

        if (! $proyek->acceptsDocuments()) {
            throw PurchaseRequestRuleException::field('BR-PRJ-01', 'project_id', 'Proyek '.$proyek->code.' tidak aktif; dokumen baru ditolak.');
        }

        return $proyek;
    }

    private function teks(mixed $nilai): ?string
    {
        $isi = is_scalar($nilai) ? trim((string) $nilai) : '';

        return $isi === '' ? null : mb_substr($isi, 0, 255);
    }
}
