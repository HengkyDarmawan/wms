<?php

declare(strict_types=1);

namespace App\Domain\Request\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Support\ApprovalEngine;
use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Models\CompanySetting;
use App\Domain\Master\Models\Item;
use App\Domain\Notification\Support\DomainNotifications;
use App\Domain\Request\Enums\FulfillmentSource;
use App\Domain\Request\Enums\MaterialRequestStatus;
use App\Domain\Request\Exceptions\RequestRuleException;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Request\Models\MaterialRequestLine;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `request.review` — peninjauan staf atas REQ (BR-REQ-02, BR-REQ-03,
 * BR-REQ-04, BR-REQ-14).
 *
 * Tiga pekerjaan yang harus selesai sebelum REQ boleh naik ke approval:
 * memetakan baris non-katalog ke item, menetapkan gudang sumber dan cara
 * pemenuhan, lalu mengisi tanggal janji. Ketiganya dikerjakan lewat kelas ini
 * supaya setiap perubahan tercatat di satu tempat dan bisa diadu belakangan.
 */
class ReviewRequest
{
    /** Ambang keberatan penggantian item, dalam hari (BR-REQ-13). */
    public const AMBANG_KEBERATAN = 'substitution_objection_days';

    public function __construct(private readonly ApprovalEngine $approval) {}

    /**
     * Memetakan baris non-katalog ke item yang ada.
     *
     * Bila item yang dipilih berbeda dari yang diminta klien, ini adalah
     * **penggantian** dan klien diberi tenggat untuk keberatan (BR-REQ-13).
     */
    public function mapLine(MaterialRequestLine $line, int $itemId, ?User $actor = null): MaterialRequestLine
    {
        $this->pastikanBisaDitinjau($line->request);

        $item = Item::query()->findOrFail($itemId);
        $itemLama = $line->item_id;

        $diganti = $this->adalahPenggantian($line, $itemLama, (int) $item->id);

        $hasil = DB::transaction(function () use ($line, $item, $itemLama, $diganti, $actor) {
            $atribut = [
                'item_id' => $item->id,
                'mapped_by' => $actor?->id,
                'mapped_at' => now(),
            ];

            // Penggantian hanya berlaku untuk REQ klien: pemohon internal yang
            // salah pilih item cukup mengubahnya sendiri.
            if ($diganti) {
                $atribut += [
                    'original_item_text' => $this->teksAsli($line),
                    'substituted_at' => now(),
                    'substitution_deadline_at' => now()->addDays($this->ambangKeberatan()),
                    'substitution_response' => null,
                ];
            }

            $line->forceFill($atribut)->save();

            activity('request')
                ->performedOn($line->request)
                ->causedBy($actor)
                ->withProperties(['baris' => $line->id, 'dari' => $itemLama, 'ke' => $item->id])
                ->log('Baris REQ dipetakan ke '.$item->code);

            return $line->refresh();
        });

        if ($diganti) {
            app(DomainNotifications::class)->lineSubstituted($hasil, $actor);
        }

        return $hasil;
    }

    /**
     * Memetakan baris non-katalog ke item **baru** berstatus sementara.
     *
     * BR-REQ-03: item `provisional` boleh dipakai agar REQ tidak tertahan,
     * tetapi harus dilengkapi Admin sebelum GRN pertama.
     */
    public function mapToProvisionalItem(
        MaterialRequestLine $line,
        string $code,
        string $name,
        int $baseUomId,
        ?User $actor = null,
    ): MaterialRequestLine {
        $this->pastikanBisaDitinjau($line->request);

        if (trim($code) === '' || trim($name) === '') {
            throw RequestRuleException::rule('BR-REQ-03', 'Kode dan nama item sementara wajib diisi.');
        }

        $item = DB::transaction(fn () => Item::create([
            'code' => mb_strtoupper(trim($code)),
            'name' => trim($name),
            'status' => ItemStatus::Provisional,
            'base_uom_id' => $baseUomId,
        ]));

        $hasil = $this->mapLine($line, (int) $item->id, $actor);
        app(DomainNotifications::class)->provisionalItemCreated($item, $hasil->request, $actor);

        return $hasil;
    }

    /**
     * Menetapkan gudang sumber, cara pemenuhan, dan tanggal janji satu baris
     * (BR-REQ-04, BR-REQ-14).
     */
    public function setSource(
        MaterialRequestLine $line,
        ?int $warehouseId,
        ?string $fulfillmentSource,
        ?string $promisedDate = null,
        ?User $actor = null,
    ): MaterialRequestLine {
        $this->pastikanBisaDitinjau($line->request);

        $sumber = $fulfillmentSource === null || $fulfillmentSource === ''
            ? null
            : FulfillmentSource::tryFrom($fulfillmentSource);

        if ($fulfillmentSource !== null && $fulfillmentSource !== '' && $sumber === null) {
            throw RequestRuleException::field('BR-REQ-05', 'fulfillment_source', 'Cara pemenuhan tidak dikenal.');
        }

        if ($warehouseId !== null) {
            // Gudang di luar cakupan peninjau tidak akan ditemukan; itu disengaja.
            Warehouse::query()->findOrFail($warehouseId);
        }

        $janjiLama = $line->promised_date?->toDateString();

        $hasil = DB::transaction(function () use ($line, $warehouseId, $sumber, $promisedDate, $janjiLama, $actor) {
            $line->forceFill([
                'source_warehouse_id' => $warehouseId,
                'fulfillment_source' => $sumber,
                'promised_date' => $promisedDate === '' ? null : $promisedDate,
            ])->save();

            $janjiBaru = $line->refresh()->promised_date?->toDateString();

            // BR-REQ-14: perubahan tanggal janji tercatat dan diberitahukan ke klien.
            if ($janjiLama !== $janjiBaru) {
                activity('request')
                    ->performedOn($line->request)
                    ->causedBy($actor)
                    ->withProperties(['baris' => $line->id, 'dari' => $janjiLama, 'ke' => $janjiBaru])
                    ->log('Tanggal janji baris REQ diubah');
            }

            return $line;
        });

        if ($janjiLama !== $hasil->promised_date?->toDateString()) {
            app(DomainNotifications::class)->promiseChanged($hasil, $actor);
        }

        return $hasil;
    }

    /** `under_review` → `pending_approval` (BR-REQ-03, BR-REQ-04). */
    public function submitToApproval(MaterialRequest $request, ?User $actor = null): MaterialRequest
    {
        $this->pastikanBisaDitinjau($request);

        if ($request->lines()->unmapped()->exists()) {
            throw RequestRuleException::rule(
                'BR-REQ-03',
                'Masih ada baris non-katalog yang belum dipetakan ke item.',
            );
        }

        if ($request->lines()->withoutSource()->exists()) {
            throw RequestRuleException::rule(
                'BR-REQ-04',
                'Setiap baris harus punya gudang sumber dan cara pemenuhan sebelum naik ke persetujuan.',
            );
        }

        return DB::transaction(function () use ($request, $actor) {
            $request->forceFill([
                'status' => MaterialRequestStatus::PendingApproval,
                'reviewed_by' => $actor?->id,
                'reviewed_at' => now(),
            ])->save();

            activity('request')->performedOn($request)->causedBy($actor)->log('REQ selesai ditinjau');

            // Snapshot aturan approval; tanpa aturan langsung approved (A-08).
            $this->approval->submit(ApprovalDocumentType::MaterialRequest, $request, $actor);

            return $request->refresh();
        });
    }

    /** `under_review` → `rejected` (BR-GEN-11: alasan wajib). */
    public function reject(MaterialRequest $request, ?int $reasonCodeId, ?string $notes = null, ?User $actor = null): MaterialRequest
    {
        $this->pastikanBisaDitinjau($request);

        if ($reasonCodeId === null) {
            throw RequestRuleException::field('BR-GEN-11', 'reasonCode', 'Alasan penolakan wajib dipilih.');
        }

        $hasil = DB::transaction(function () use ($request, $reasonCodeId, $notes, $actor) {
            $request->forceFill([
                'status' => MaterialRequestStatus::Rejected,
                'cancel_reason_id' => $reasonCodeId,
                'reviewed_by' => $actor?->id,
                'reviewed_at' => now(),
            ])->save();

            activity('request')
                ->performedOn($request)
                ->causedBy($actor)
                ->withProperties(['reason_code_id' => $reasonCodeId, 'notes' => $notes])
                ->log('REQ ditolak saat tinjau');

            return $request->refresh();
        });

        app(DomainNotifications::class)->requestDecided($hasil, $actor);

        return $hasil;
    }

    public function ambangKeberatan(): int
    {
        $nilai = (int) CompanySetting::get(self::AMBANG_KEBERATAN, 1);

        return $nilai > 0 ? $nilai : 1;
    }

    private function pastikanBisaDitinjau(MaterialRequest $request): void
    {
        if ($request->status !== MaterialRequestStatus::UnderReview) {
            throw RequestRuleException::rule(
                'BR-REQ-02',
                'Hanya REQ berstatus Ditinjau yang bisa dikerjakan di layar tinjau.',
            );
        }
    }

    /** Penggantian hanya bermakna bila klien yang meminta dan itemnya berubah. */
    private function adalahPenggantian(MaterialRequestLine $line, ?int $itemLama, int $itemBaru): bool
    {
        if (! $line->request->isFromClient()) {
            return false;
        }

        return $itemLama !== null && $itemLama !== $itemBaru;
    }

    /** Yang disimpan adalah apa yang dilihat klien sebelum diganti. */
    private function teksAsli(MaterialRequestLine $line): string
    {
        if ($line->original_item_text !== null) {
            return $line->original_item_text;
        }

        $line->loadMissing('item');

        return $line->displayName();
    }
}
