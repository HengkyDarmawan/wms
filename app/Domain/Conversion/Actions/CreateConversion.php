<?php

declare(strict_types=1);

namespace App\Domain\Conversion\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Conversion\Enums\ConversionStatus;
use App\Domain\Conversion\Enums\ConversionType;
use App\Domain\Conversion\Exceptions\ConversionRuleException;
use App\Domain\Conversion\Models\Conversion;
use App\Domain\Conversion\Models\ConversionInput;
use App\Domain\Conversion\Models\ConversionOutput;
use App\Domain\Conversion\Support\ConversionLines;
use App\Domain\Conversion\Support\ConversionReversibility;
use App\Domain\Conversion\Support\ConvertibleStock;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Stock\Support\DocumentNumber;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `conversion.create` — CNV baru `draft` (Katalog §2.10).
 *
 * Guard: proyek aktif dalam cakupan (BR-CNV-01, D-10; Proyek Internal untuk
 * persiapan stok, A-06), satu gudang aktif dalam cakupan — Gudang Site hanya
 * untuk proyek pemiliknya — input dari stok Tersedia bin penyimpanan, dan
 * baris hasil yang seimbang (BR-CNV-02/03, A-154, A-156). Draf belum memegang
 * stok; semuanya diperiksa lagi saat CNV selesai.
 *
 * `update()` mengubah draf (pembuat atau pemegang `conversion.complete`, A-158).
 * `reverse()` membuat **CNV pembalik** untuk CNV `completed` bila semua hasil
 * masih utuh di bin (BR-CNV-05, BR-GEN-03/04, A-157).
 */
class CreateConversion
{
    public function __construct(
        private readonly ConvertibleStock $stock,
        private readonly ConversionLines $lines,
        private readonly ConversionReversibility $reversibility,
        private readonly DocumentNumber $nomor,
    ) {}

    /**
     * @param  array{project_id?: mixed, warehouse_id?: mixed, conversion_type?: mixed, notes?: ?string}  $header
     * @param  array<int, array<string, mixed>>  $inputs  key, qty_base
     * @param  array<int, array<string, mixed>>  $outputs  kind, item_id, parent, qty_base, count, lot_no, bin_id, reason_code_id
     */
    public function handle(array $header, array $inputs, array $outputs, ?User $actor = null): Conversion
    {
        [$proyek, $gudang] = $this->tujuan($header, $actor);
        $jenis = $this->jenis($header['conversion_type'] ?? null);
        $masuk = $this->stock->normalize($gudang, $inputs);
        $hasil = $this->lines->normalize($gudang, $jenis, $masuk, $outputs);

        return DB::transaction(function () use ($proyek, $gudang, $jenis, $masuk, $hasil, $header, $actor) {
            $cnv = Conversion::create([
                'number' => $this->nomor->next('CNV', (string) $gudang->code),
                'project_id' => $proyek->id,
                'warehouse_id' => $gudang->id,
                'conversion_type' => $jenis,
                'status' => ConversionStatus::Draft,
                'prepared_by' => $actor?->id,
                'notes' => $this->teks($header['notes'] ?? null),
            ] + $this->lines->totals($masuk, $hasil));

            $this->simpanBaris($cnv, $masuk, $hasil);

            activity('conversion')->performedOn($cnv)->causedBy($actor)
                ->withProperties(['input' => count($masuk), 'hasil' => count($hasil)])
                ->log('CNV dibuat (draf)');

            return $cnv->refresh();
        });
    }

    /**
     * Mengubah draf CNV biasa. Draf belum menggerakkan stok, jadi barisnya
     * diganti (P-03 hanya melindungi data yang sudah dipakai).
     *
     * @param  array<string, mixed>  $header
     * @param  array<int, array<string, mixed>>  $inputs
     * @param  array<int, array<string, mixed>>  $outputs
     */
    public function update(Conversion $cnv, array $header, array $inputs, array $outputs, ?User $actor = null): Conversion
    {
        if ($cnv->status !== ConversionStatus::Draft || $cnv->isReversal()) {
            throw ConversionRuleException::rule('BR-GEN-01', 'Hanya draf CNV biasa yang bisa diubah.');
        }

        if ($actor !== null && ! $actor->canAccessWarehouse((int) $cnv->warehouse_id)) {
            throw ConversionRuleException::rule('BR-ACC-05', 'Gudang CNV ini di luar cakupan Anda.');
        }

        $cnv->loadMissing('warehouse');
        $jenis = $this->jenis($header['conversion_type'] ?? $cnv->conversion_type->value);
        $masuk = $this->stock->normalize($cnv->warehouse, $inputs);
        $hasil = $this->lines->normalize($cnv->warehouse, $jenis, $masuk, $outputs);

        return DB::transaction(function () use ($cnv, $jenis, $masuk, $hasil, $header, $actor) {
            $cnv->outputs()->delete();
            $cnv->inputs()->delete();

            $this->simpanBaris($cnv, $masuk, $hasil);

            $cnv->forceFill([
                'conversion_type' => $jenis,
                'notes' => $this->teks($header['notes'] ?? null),
            ] + $this->lines->totals($masuk, $hasil))->save();

            activity('conversion')->performedOn($cnv)->causedBy($actor)
                ->withProperties(['input' => count($masuk), 'hasil' => count($hasil)])
                ->log('Draf CNV diubah');

            return $cnv->refresh();
        });
    }

    /** CNV pembalik (A-157): salinan baris CNV asal, Alasan `*`, satu pembalik aktif per CNV. */
    public function reverse(Conversion $original, mixed $reasonCodeId, ?string $notes = null, ?User $actor = null): Conversion
    {
        if ($original->status !== ConversionStatus::Completed) {
            throw ConversionRuleException::rule('BR-GEN-03', 'Hanya CNV yang sudah selesai yang bisa dibalik; draf cukup dibatalkan.');
        }

        if ($original->isReversal()) {
            throw ConversionRuleException::rule('BR-LED-05', 'CNV pembalik tidak bisa dibalik lagi.');
        }

        if ($actor !== null && ! $actor->canAccessWarehouse((int) $original->warehouse_id)) {
            throw ConversionRuleException::rule('BR-ACC-05', 'Gudang CNV ini di luar cakupan Anda.');
        }

        if (($aktif = $original->activeReversal()) !== null) {
            throw ConversionRuleException::rule('BR-LED-05', 'CNV ini sudah punya pembalik '.$aktif->number.' ('.$aktif->status->label().').');
        }

        $original->loadMissing('warehouse', 'project');

        if (! $original->project?->acceptsDocuments()) {
            throw ConversionRuleException::rule('BR-PRJ-01', 'Proyek '.$original->project?->code.' tidak aktif; dokumen baru ditolak.');
        }

        $alasan = is_numeric($reasonCodeId) ? (int) $reasonCodeId : 0;
        $sah = $alasan > 0 && ReasonCode::query()->whereKey($alasan)->where('context', ReasonContext::Cancel->value)->exists();

        if (! $sah) {
            throw ConversionRuleException::field('BR-GEN-11', 'reason', 'Alasan pembalikan wajib dipilih.');
        }

        $this->reversibility->assert($original);

        return DB::transaction(function () use ($original, $alasan, $notes, $actor) {
            $cnv = Conversion::create([
                'number' => $this->nomor->next('CNV', (string) $original->warehouse->code),
                'project_id' => $original->project_id,
                'warehouse_id' => $original->warehouse_id,
                'conversion_type' => $original->conversion_type,
                'status' => ConversionStatus::Draft,
                'reversal_of_id' => $original->id,
                'reason_code_id' => $alasan,
                'prepared_by' => $actor?->id,
                'notes' => $this->teks($notes) ?? 'Pembalik '.$original->number,
                'total_input' => $original->total_input,
                'total_output' => $original->total_output,
                'total_offcut' => $original->total_offcut,
                'total_waste' => $original->total_waste,
                'total_kerf' => $original->total_kerf,
            ]);

            $petaInput = [];

            foreach ($original->inputs()->orderBy('id')->get() as $i) {
                $petaInput[(int) $i->id] = (int) ConversionInput::create([
                    'conversion_id' => $cnv->id,
                    'item_id' => $i->item_id,
                    'bin_id' => $i->bin_id,
                    'lot_id' => $i->lot_id,
                    'piece_id' => $i->piece_id,
                    'qty_base' => $i->qty_base,
                    'reversal_of_line_id' => $i->id,
                ])->id;
            }

            foreach ($original->outputs()->orderBy('id')->get() as $o) {
                ConversionOutput::create([
                    'conversion_id' => $cnv->id,
                    'output_kind' => $o->output_kind,
                    'item_id' => $o->item_id,
                    'bin_id' => $o->bin_id,
                    'stock_status' => $o->stock_status,
                    'qty_base' => $o->qty_base,
                    'lot_no' => $o->lot_no,
                    'lot_id' => $o->lot_id,
                    'parent_input_id' => $petaInput[(int) $o->parent_input_id] ?? null,
                    'reason_code_id' => $o->reason_code_id,
                    'auto_waste' => $o->auto_waste,
                    'reversal_of_line_id' => $o->id,
                ]);
            }

            activity('conversion')->performedOn($cnv)->causedBy($actor)
                ->withProperties(['pembalik_dari' => $original->number])
                ->log('CNV pembalik dibuat (draf)');

            return $cnv->refresh();
        });
    }

    /**
     * @param  array<string, array<string, mixed>>  $masuk
     * @param  array<int, array<string, mixed>>  $hasil
     */
    private function simpanBaris(Conversion $cnv, array $masuk, array $hasil): void
    {
        $peta = [];

        foreach ($masuk as $kunci => $b) {
            $peta[$kunci] = (int) ConversionInput::create([
                'conversion_id' => $cnv->id,
                'item_id' => $b['item_id'],
                'bin_id' => $b['bin_id'],
                'lot_id' => $b['lot_id'],
                'piece_id' => $b['piece_id'],
                'qty_base' => $b['qty_base'],
            ])->id;
        }

        foreach ($hasil as $h) {
            $kunci = $h['parent_key'];
            unset($h['parent_key']);

            ConversionOutput::create($h + [
                'conversion_id' => $cnv->id,
                'parent_input_id' => $peta[$kunci] ?? null,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $header
     * @return array{0: Project, 1: Warehouse}
     */
    private function tujuan(array $header, ?User $actor): array
    {
        $proyek = Project::query()->find(is_numeric($header['project_id'] ?? null) ? (int) $header['project_id'] : 0);

        if ($proyek === null) {
            throw ConversionRuleException::field('BR-CNV-01', 'project_id', 'Proyek wajib dipilih (Proyek Internal untuk persiapan stok).');
        }

        if ($actor !== null && ! $actor->canAccessProject((int) $proyek->id)) {
            throw ConversionRuleException::field('BR-ACC-05', 'project_id', 'Proyek '.$proyek->code.' di luar cakupan Anda.');
        }

        if (! $proyek->acceptsDocuments()) {
            throw ConversionRuleException::field('BR-PRJ-01', 'project_id', 'Proyek '.$proyek->code.' tidak aktif; dokumen baru ditolak.');
        }

        $gudang = Warehouse::query()->withoutGlobalScopes()->with('type')
            ->find(is_numeric($header['warehouse_id'] ?? null) ? (int) $header['warehouse_id'] : 0);

        if ($gudang === null) {
            throw ConversionRuleException::field('BR-CNV-01', 'warehouse_id', 'Gudang wajib dipilih.');
        }

        if (! $gudang->is_active) {
            throw ConversionRuleException::field('BR-WH-07', 'warehouse_id', 'Gudang '.$gudang->code.' nonaktif.');
        }

        if ($actor !== null && ! $actor->canAccessWarehouse((int) $gudang->id)) {
            throw ConversionRuleException::field('BR-ACC-05', 'warehouse_id', 'Gudang '.$gudang->code.' di luar cakupan Anda.');
        }

        if ($gudang->isSite() && (int) $gudang->project_id !== (int) $proyek->id) {
            throw ConversionRuleException::field('BR-CNV-01', 'warehouse_id', 'Gudang Site '.$gudang->code.' milik proyek lain; konversi di site hanya untuk proyek pemiliknya.');
        }

        return [$proyek, $gudang];
    }

    private function jenis(mixed $nilai): ConversionType
    {
        $nilai = is_scalar($nilai) ? trim((string) $nilai) : '';

        if ($nilai === '') {
            return ConversionType::Cut;
        }

        return ConversionType::tryFrom($nilai)
            ?? throw ConversionRuleException::field('BR-GEN-01', 'conversion_type', 'Jenis konversi tidak dikenal.');
    }

    private function teks(mixed $nilai): ?string
    {
        $isi = is_scalar($nilai) ? trim((string) $nilai) : '';

        return $isi === '' ? null : mb_substr($isi, 0, 255);
    }
}
