<?php

declare(strict_types=1);

namespace App\Domain\Issue\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Issue\Enums\MaterialIssueStatus;
use App\Domain\Issue\Exceptions\IssueRuleException;
use App\Domain\Issue\Models\MaterialIssue;
use App\Domain\Issue\Models\MaterialIssueLine;
use App\Domain\Issue\Support\IssuableStock;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Stock\Support\DocumentNumber;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `issue.create` — ISU baru `draft` (Katalog §2.9).
 *
 * Guard: proyek aktif (BR-PRJ-01), gudang = Gudang Site aktif milik proyek
 * itu, barang = stok Tersedia habis pakai di bin penyimpanannya
 * (BR-PRJ-08, A-117). Draf belum memegang stok; jumlah diperiksa lagi saat
 * dikonfirmasi.
 *
 * `update()` mengubah draf (pembuat atau pemegang `issue.confirm`, A-119).
 * `reverse()` membuat **ISU pembalik** untuk ISU `confirmed`: baris terpilih
 * dengan jumlah negatif, Alasan `*`, menunjuk baris asal; pembalik diajukan ke
 * approval lewat `issue.confirm` (BR-GEN-04, A-150).
 */
class CreateMaterialIssue
{
    public function __construct(
        private readonly IssuableStock $stock,
        private readonly DocumentNumber $nomor,
    ) {}

    /**
     * @param  array{project_id?: mixed, warehouse_id?: mixed, notes?: ?string}  $header
     * @param  array<int, array<string, mixed>>  $lines  key, qty_base, work_note
     */
    public function handle(array $header, array $lines, ?User $actor = null): MaterialIssue
    {
        [$proyek, $site] = $this->tujuan($header, $actor);
        $baris = $this->stock->normalize($site, $lines);

        return DB::transaction(function () use ($proyek, $site, $baris, $header, $actor) {
            $isu = MaterialIssue::create([
                'number' => $this->nomor->next('ISU', (string) $site->code),
                'project_id' => $proyek->id,
                'warehouse_id' => $site->id,
                'status' => MaterialIssueStatus::Draft,
                'issued_by' => $actor?->id,
                'notes' => $this->teks($header['notes'] ?? null),
            ]);

            foreach ($baris as $b) {
                MaterialIssueLine::create($b + ['material_issue_id' => $isu->id]);
            }

            activity('issue')->performedOn($isu)->causedBy($actor)
                ->withProperties(['baris' => count($baris)])
                ->log('ISU dibuat (draf)');

            return $isu->refresh();
        });
    }

    /**
     * Mengubah baris draf ISU biasa. Draf belum menggerakkan stok, jadi baris
     * lamanya diganti (P-03 hanya melindungi data yang sudah dipakai).
     *
     * @param  array<string, mixed>  $header
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function update(MaterialIssue $issue, array $header, array $lines, ?User $actor = null): MaterialIssue
    {
        if ($issue->status !== MaterialIssueStatus::Draft || $issue->isReversal()) {
            throw IssueRuleException::rule('BR-GEN-01', 'Hanya draf ISU biasa yang bisa diubah.');
        }

        $issue->loadMissing('warehouse');

        if ($actor !== null && ! $actor->canAccessWarehouse((int) $issue->warehouse_id)) {
            throw IssueRuleException::rule('BR-ACC-05', 'Gudang Site ISU ini di luar cakupan Anda.');
        }

        $baris = $this->stock->normalize($issue->warehouse, $lines);

        return DB::transaction(function () use ($issue, $baris, $header, $actor) {
            $issue->lines()->delete();

            foreach ($baris as $b) {
                MaterialIssueLine::create($b + ['material_issue_id' => $issue->id]);
            }

            $issue->forceFill(['notes' => $this->teks($header['notes'] ?? null)])->save();

            activity('issue')->performedOn($issue)->causedBy($actor)
                ->withProperties(['baris' => count($baris)])
                ->log('Draf ISU diubah');

            return $issue->refresh();
        });
    }

    /**
     * ISU pembalik (BR-GEN-03, BR-GEN-04): baris yang dipilih (kosong = semua
     * yang belum dibalik) dengan jumlah berlawanan. Satu baris hanya boleh
     * dibalik sekali (BR-LED-05).
     *
     * @param  array<int, int|string>  $lineIds
     */
    public function reverse(MaterialIssue $original, array $lineIds, mixed $reasonCodeId, ?string $notes = null, ?User $actor = null): MaterialIssue
    {
        if ($original->status !== MaterialIssueStatus::Confirmed) {
            throw IssueRuleException::rule('BR-GEN-03', 'Hanya ISU yang sudah dikonfirmasi yang bisa dibalik; draf cukup dibatalkan.');
        }

        if ($original->isReversal()) {
            throw IssueRuleException::rule('BR-LED-05', 'ISU pembalik tidak bisa dibalik lagi.');
        }

        if ($actor !== null && ! $actor->canAccessWarehouse((int) $original->warehouse_id)) {
            throw IssueRuleException::rule('BR-ACC-05', 'Gudang Site ISU ini di luar cakupan Anda.');
        }

        $original->loadMissing('warehouse');
        $proyek = Project::query()->find($original->project_id);

        if (! $proyek?->acceptsDocuments()) {
            throw IssueRuleException::rule('BR-PRJ-01', 'Proyek '.$proyek?->code.' tidak aktif; dokumen baru ditolak.');
        }

        $alasan = is_numeric($reasonCodeId) ? (int) $reasonCodeId : 0;
        $sah = $alasan > 0 && ReasonCode::query()->whereKey($alasan)->where('context', ReasonContext::Cancel->value)->exists();

        if (! $sah) {
            throw IssueRuleException::field('BR-GEN-11', 'reason', 'Alasan pembalikan wajib dipilih.');
        }

        $sudah = $original->reversedLineIds();
        $layak = $original->lines()->whereNotNull('movement_id')->whereNotIn('id', $sudah ?: [0])->orderBy('id')->get();
        $pilih = array_values(array_unique(array_map('intval', $lineIds)));

        if ($pilih !== []) {
            $asing = array_diff($pilih, $layak->pluck('id')->map(fn ($id) => (int) $id)->all());

            if ($asing !== []) {
                throw IssueRuleException::rule('BR-LED-05', 'Ada baris yang sudah dibalik atau bukan milik ISU ini; pembalikan hanya sekali per baris.');
            }

            $layak = $layak->whereIn('id', $pilih)->values();
        }

        if ($layak->isEmpty()) {
            throw IssueRuleException::rule('BR-LED-05', 'Semua baris ISU ini sudah dibalik atau sedang diajukan untuk dibalik.');
        }

        return DB::transaction(function () use ($original, $layak, $alasan, $notes, $actor) {
            $isu = MaterialIssue::create([
                'number' => $this->nomor->next('ISU', (string) $original->warehouse->code),
                'project_id' => $original->project_id,
                'warehouse_id' => $original->warehouse_id,
                'status' => MaterialIssueStatus::Draft,
                'reversal_of_id' => $original->id,
                'reason_code_id' => $alasan,
                'issued_by' => $actor?->id,
                'notes' => $this->teks($notes) ?? 'Pembalik '.$original->number,
            ]);

            foreach ($layak as $l) {
                MaterialIssueLine::create([
                    'material_issue_id' => $isu->id,
                    'item_id' => $l->item_id,
                    'bin_id' => $l->bin_id,
                    'lot_id' => $l->lot_id,
                    'serial_id' => $l->serial_id,
                    'piece_id' => $l->piece_id,
                    'qty_base' => -1 * (float) $l->qty_base,
                    'work_note' => $l->work_note,
                    'reversal_of_line_id' => $l->id,
                ]);
            }

            activity('issue')->performedOn($isu)->causedBy($actor)
                ->withProperties(['pembalik_dari' => $original->number, 'baris' => $layak->count()])
                ->log('ISU pembalik dibuat (draf)');

            return $isu->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $header
     * @return array{0: Project, 1: Warehouse}
     */
    private function tujuan(array $header, ?User $actor): array
    {
        $proyek = Project::query()->find(is_numeric($header['project_id'] ?? null) ? (int) $header['project_id'] : 0);

        if ($proyek === null) {
            throw IssueRuleException::field('BR-PRJ-08', 'project_id', 'Proyek wajib dipilih.');
        }

        if ($actor !== null && ! $actor->canAccessProject((int) $proyek->id)) {
            throw IssueRuleException::field('BR-ACC-05', 'project_id', 'Proyek '.$proyek->code.' di luar cakupan Anda.');
        }

        if (! $proyek->acceptsDocuments()) {
            throw IssueRuleException::field('BR-PRJ-01', 'project_id', 'Proyek '.$proyek->code.' tidak aktif; dokumen baru ditolak.');
        }

        $site = Warehouse::query()->withoutGlobalScopes()->with('type')
            ->find(is_numeric($header['warehouse_id'] ?? null) ? (int) $header['warehouse_id'] : 0);

        if ($site === null) {
            throw IssueRuleException::field('BR-PRJ-08', 'warehouse_id', 'Gudang Site wajib dipilih.');
        }

        if (! $site->isSite() || (int) $site->project_id !== (int) $proyek->id) {
            throw IssueRuleException::field('BR-PRJ-08', 'warehouse_id', 'Pemakaian hanya dari Gudang Site milik proyek '.$proyek->code.'.');
        }

        if (! $site->is_active) {
            throw IssueRuleException::field('BR-WH-07', 'warehouse_id', 'Gudang Site '.$site->code.' nonaktif.');
        }

        if ($actor !== null && ! $actor->canAccessWarehouse((int) $site->id)) {
            throw IssueRuleException::field('BR-ACC-05', 'warehouse_id', 'Gudang Site '.$site->code.' di luar cakupan Anda.');
        }

        return [$proyek, $site];
    }

    private function teks(mixed $nilai): ?string
    {
        $isi = is_scalar($nilai) ? trim((string) $nilai) : '';

        return $isi === '' ? null : mb_substr($isi, 0, 255);
    }
}
