<?php

declare(strict_types=1);

namespace App\Domain\Request\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Project;
use App\Domain\Request\Enums\LineOwnership;
use App\Domain\Request\Enums\MaterialRequestStatus;
use App\Domain\Request\Enums\RequesterType;
use App\Domain\Request\Exceptions\RequestRuleException;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Request\Models\MaterialRequestLine;
use App\Domain\Request\Support\RequestNumber;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `request.create` — membuat dan mengubah REQ selama masih bisa
 * diubah (14-request §4).
 *
 * Nomor diterbitkan saat REQ dibuat, bukan saat diajukan: pemohon menyebut
 * nomor itu saat bertanya, dan draf tanpa nomor tidak bisa dirujuk.
 */
class SaveRequest
{
    public function __construct(private readonly RequestNumber $nomor) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function handle(?MaterialRequest $request, array $data, array $lines, ?User $actor = null): MaterialRequest
    {
        $baru = $request === null;

        if (! $baru && ! $request->status->isEditable()) {
            throw RequestRuleException::rule(
                'BR-REQ-02',
                'REQ berstatus '.$request->status->label().' tidak bisa diubah lagi.',
            );
        }

        $proyek = $this->proyek($request, $data);
        $tanggal = $this->tanggal($data, $request);

        return DB::transaction(function () use ($request, $baru, $proyek, $tanggal, $data, $lines, $actor) {
            $req = $request ?? new MaterialRequest;

            if ($baru) {
                $req->number = $this->nomor->next($proyek);
                $req->project_id = $proyek->id;
                $req->requester_id = $actor?->id;
                $req->requester_type = $this->tipePemohon($actor);
                $req->status = MaterialRequestStatus::Draft;
            }

            $req->required_date = $tanggal;
            $req->notes = $this->teks($data['notes'] ?? null);
            $req->save();

            $this->simpanBaris($req, $lines);

            if (! $baru) {
                activity('request')->performedOn($req)->causedBy($actor)->log('REQ diubah');
            }

            return $req->refresh();
        });
    }

    /**
     * Baris disimpan menyeluruh: yang hilang dari kiriman dibatalkan, bukan
     * dihapus (P-03), supaya yang pernah diminta tetap terbaca.
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function simpanBaris(MaterialRequest $req, array $lines): void
    {
        $dikirim = [];

        foreach ($lines as $baris) {
            $dikirim[] = $this->simpanSatuBaris($req, $baris)->id;
        }

        $req->lines()
            ->whereNotIn('id', $dikirim === [] ? [0] : $dikirim)
            ->open()
            ->update(['status' => 'cancelled']);
    }

    /** @param  array<string, mixed>  $data */
    private function simpanSatuBaris(MaterialRequest $req, array $data): MaterialRequestLine
    {
        $itemId = isset($data['item_id']) && $data['item_id'] !== '' ? (int) $data['item_id'] : null;
        $teks = $this->teks($data['non_catalog_text'] ?? null);

        // BR-REQ-03: baris harus menyebut sesuatu — item katalog atau teks bebas.
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

        $kepemilikan = LineOwnership::tryFrom((string) ($data['line_ownership'] ?? 'buy')) ?? LineOwnership::Buy;

        if ($itemId !== null) {
            $this->pastikanKepemilikanSah($itemId, $kepemilikan);
        }

        $baris = isset($data['id']) && $data['id'] !== ''
            ? $req->lines()->findOrFail((int) $data['id'])
            : new MaterialRequestLine(['material_request_id' => $req->id]);

        $baris->fill([
            'material_request_id' => $req->id,
            'item_id' => $itemId,
            'non_catalog_text' => $teks,
            'line_ownership' => $kepemilikan,
            'qty_base' => $qty,
            'nominal_length' => isset($data['nominal_length']) && $data['nominal_length'] !== ''
                ? (float) $data['nominal_length']
                : null,
            'required_date' => $this->teks($data['required_date'] ?? null) ?? $req->required_date,
            'notes' => $this->teks($data['notes'] ?? null),
        ]);

        $baris->save();

        return $baris;
    }

    /** BR-REQ-06: hanya barang berserial yang bisa dipinjamkan lalu ditagih balik. */
    private function pastikanKepemilikanSah(int $itemId, LineOwnership $kepemilikan): void
    {
        if (! $kepemilikan->requiresSerial()) {
            return;
        }

        $item = Item::query()->findOrFail($itemId);

        if ($item->tracking_mode !== TrackingMode::Serial) {
            throw RequestRuleException::field(
                'BR-REQ-06',
                'line_ownership',
                'Hanya barang berserial yang bisa dipinjamkan; '.$item->code.' tidak berserial.',
            );
        }
    }

    /** @param  array<string, mixed>  $data */
    private function proyek(?MaterialRequest $request, array $data): Project
    {
        if ($request !== null) {
            return $request->project;
        }

        $id = (int) ($data['project_id'] ?? 0);

        if ($id === 0) {
            throw RequestRuleException::field('BR-REQ-01', 'project_id', 'Proyek wajib dipilih.');
        }

        $proyek = Project::query()->findOrFail($id);

        // BR-PRJ-01: proyek yang sudah ditutup tidak menerima dokumen baru.
        if (! $proyek->acceptsDocuments()) {
            throw RequestRuleException::field(
                'BR-PRJ-01',
                'project_id',
                'Proyek '.$proyek->code.' berstatus '.$proyek->status->label().' dan tidak menerima permintaan baru.',
            );
        }

        return $proyek;
    }

    /** @param  array<string, mixed>  $data */
    private function tanggal(array $data, ?MaterialRequest $request): string
    {
        $tanggal = $this->teks($data['required_date'] ?? null)
            ?? $request?->required_date?->toDateString();

        if ($tanggal === null) {
            throw RequestRuleException::field('BR-REQ-01', 'required_date', 'Tanggal dibutuhkan wajib diisi.');
        }

        return $tanggal;
    }

    /** REQ dari akun klien selalu ditandai `client`, apa pun layar yang dipakai. */
    private function tipePemohon(?User $actor): RequesterType
    {
        return $actor?->client_id !== null ? RequesterType::Client : RequesterType::Internal;
    }

    private function teks(mixed $nilai): ?string
    {
        $teks = is_string($nilai) ? trim($nilai) : '';

        return $teks === '' ? null : $teks;
    }
}
