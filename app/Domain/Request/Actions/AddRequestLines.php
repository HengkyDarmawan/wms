<?php

declare(strict_types=1);

namespace App\Domain\Request\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Request\Enums\MaterialRequestStatus;
use App\Domain\Request\Enums\RequestOrigin;
use App\Domain\Request\Exceptions\RequestRuleException;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Request\Models\MaterialRequestLine;
use App\Domain\Request\Support\RequestNumber;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `request.add_lines` — klien menambah baris (A-54, BR-REQ-12).
 *
 * Klien boleh **menambah**, tidak pernah mengurangi atau menghapus: yang sudah
 * diminta hanya bisa dibatalkan lewat jalur pembatalan yang dikonfirmasi staf
 * (BR-REQ-15).
 *
 * Ke mana baris baru itu pergi bergantung sejauh mana REQ sudah berjalan:
 * - masih bisa diubah → langsung menempel ke REQ itu;
 * - menunggu approval → menempel, tetapi REQ mundur ke `under_review` dan
 *   snapshot approval dibuang, karena yang disetujui bukan lagi yang diajukan;
 * - sudah disetujui → lahir **REQ Tambahan** dengan nomor dan approval sendiri,
 *   supaya yang sudah dikerjakan gudang tidak berubah di belakang.
 */
class AddRequestLines
{
    public function __construct(private readonly RequestNumber $nomor) {}

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return MaterialRequest  REQ yang menampung baris baru
     */
    public function handle(MaterialRequest $request, array $lines, ?User $actor = null): MaterialRequest
    {
        if ($lines === []) {
            throw RequestRuleException::rule('BR-REQ-12', 'Tidak ada baris yang ditambahkan.');
        }

        return match (true) {
            $request->status->isEditable() => $this->tempel($request, $lines, $actor),
            $request->status === MaterialRequestStatus::PendingApproval => $this->tempelDanMundur($request, $lines, $actor),
            $request->status->hasReservations() => $this->buatTambahan($request, $lines, $actor),
            default => throw RequestRuleException::rule(
                'BR-REQ-12',
                'REQ berstatus '.$request->status->label().' tidak bisa ditambah baris.',
            ),
        };
    }

    /** @param  array<int, array<string, mixed>>  $lines */
    private function tempel(MaterialRequest $request, array $lines, ?User $actor): MaterialRequest
    {
        return DB::transaction(function () use ($request, $lines, $actor) {
            foreach ($lines as $baris) {
                $this->buatBaris($request, $baris);
            }

            activity('request')
                ->performedOn($request)
                ->causedBy($actor)
                ->withProperties(['jumlah' => count($lines)])
                ->log('Klien menambah baris REQ');

            return $request->refresh();
        });
    }

    /** @param  array<int, array<string, mixed>>  $lines */
    private function tempelDanMundur(MaterialRequest $request, array $lines, ?User $actor): MaterialRequest
    {
        return DB::transaction(function () use ($request, $lines, $actor) {
            foreach ($lines as $baris) {
                $this->buatBaris($request, $baris);
            }

            // Snapshot approval dibuang: yang akan disetujui bukan lagi dokumen
            // yang dulu diajukan.
            $request->forceFill([
                'status' => MaterialRequestStatus::UnderReview,
                'approval_snapshot_id' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ])->save();

            activity('request')
                ->performedOn($request)
                ->causedBy($actor)
                ->withProperties(['jumlah' => count($lines)])
                ->log('Klien menambah baris; REQ kembali ditinjau');

            return $request->refresh();
        });
    }

    /** @param  array<int, array<string, mixed>>  $lines */
    private function buatTambahan(MaterialRequest $request, array $lines, ?User $actor): MaterialRequest
    {
        return DB::transaction(function () use ($request, $lines, $actor) {
            $tambahan = MaterialRequest::create([
                'number' => $this->nomor->next($request->project),
                'project_id' => $request->project_id,
                'requester_id' => $actor?->id ?? $request->requester_id,
                'requester_type' => $request->requester_type,
                'status' => MaterialRequestStatus::UnderReview,
                'required_date' => $request->required_date,
                'origin' => RequestOrigin::Supplement,
                'parent_request_id' => $request->id,
            ]);

            foreach ($lines as $baris) {
                $this->buatBaris($tambahan, $baris);
            }

            activity('request')
                ->performedOn($request)
                ->causedBy($actor)
                ->withProperties(['tambahan' => $tambahan->number])
                ->log('REQ Tambahan dibuat dari permintaan klien');

            return $tambahan->refresh();
        });
    }

    /**
     * Baris baru selalu lahir bersih: tanpa gudang sumber, tanpa cara
     * pemenuhan, dan tanpa tanggal janji — ketiganya pekerjaan staf saat tinjau.
     *
     * @param  array<string, mixed>  $data
     */
    private function buatBaris(MaterialRequest $request, array $data): MaterialRequestLine
    {
        $itemId = isset($data['item_id']) && $data['item_id'] !== '' ? (int) $data['item_id'] : null;
        $teks = isset($data['non_catalog_text']) && trim((string) $data['non_catalog_text']) !== ''
            ? trim((string) $data['non_catalog_text'])
            : null;

        if ($itemId === null && $teks === null) {
            throw RequestRuleException::field(
                'BR-REQ-03',
                'item_id',
                'Pilih item dari katalog atau tuliskan barang yang diminta.',
            );
        }

        $qty = (float) ($data['qty_base'] ?? 0);

        if ($qty <= 0) {
            throw RequestRuleException::field('BR-REQ-01', 'qty_base', 'Jumlah harus lebih dari nol.');
        }

        return MaterialRequestLine::create([
            'material_request_id' => $request->id,
            'item_id' => $itemId,
            'non_catalog_text' => $teks,
            'qty_base' => $qty,
            'required_date' => $data['required_date'] ?? $request->required_date,
            'notes' => $data['notes'] ?? null,
        ]);
    }
}
