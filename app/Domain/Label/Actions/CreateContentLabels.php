<?php

declare(strict_types=1);

namespace App\Domain\Label\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Label\Enums\PackageLabelStatus;
use App\Domain\Label\Exceptions\LabelRuleException;
use App\Domain\Label\Models\PackageLabel;
use App\Domain\Label\Support\LabelCode;
use App\Domain\Label\Support\PackageLabelLedger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `label.print` — "Cetak label isi" (A-296): isi label induk
 * dipecah ke n label **isi** (`KODEINDUK-0001` …). Isi berpindah dari induk ke
 * label isi; induk tetap Di gudang sebagai wadahnya. Jumlah per label isi =
 * sisa isi induk ÷ n, label terakhir menampung selisih pembulatan.
 */
class CreateContentLabels
{
    public const MAKS = 1000;

    private const EPS = 0.00005;

    public function __construct(private readonly PackageLabelLedger $ledger) {}

    /** @return Collection<int, PackageLabel> */
    public function handle(PackageLabel $induk, int $n, ?User $actor = null): Collection
    {
        if ($n < 1 || $n > self::MAKS) {
            throw LabelRuleException::field('BR-LBL-02', 'n', 'Jumlah label isi 1–'.self::MAKS.'.');
        }

        return DB::transaction(function () use ($induk, $n, $actor) {
            $induk = PackageLabel::query()->lockForUpdate()->findOrFail($induk->id);

            if (! $induk->isParent() || $induk->status !== PackageLabelStatus::InStock || (float) $induk->qty_remaining <= self::EPS) {
                throw LabelRuleException::rule('BR-LBL-02', 'Label isi hanya dari label induk Di gudang yang masih berisi.');
            }

            $isi = (float) $induk->qty_remaining;
            $per = round($isi / $n, 4);
            $mulai = (int) PackageLabel::query()->where('parent_id', $induk->id)->max('sequence');
            $dibuat = collect();

            $induk->forceFill(['qty_remaining' => 0])->save();
            $this->ledger->catat($induk, -$isi, ['type' => null, 'id' => null, 'line_id' => null, 'number' => null], $actor,
                ['warehouse_id' => $induk->warehouse_id, 'notes' => $n.' label isi']);

            for ($k = 1; $k <= $n; $k++) {
                $qty = $k === $n ? round($isi - $per * ($n - 1), 4) : $per;

                $anak = PackageLabel::create([
                    'code' => LabelCode::child($induk->code, $mulai + $k),
                    'parent_id' => $induk->id,
                    'sequence' => $mulai + $k,
                    'item_id' => $induk->item_id,
                    'lot_id' => $induk->lot_id,
                    'goods_receipt_id' => $induk->goods_receipt_id,
                    'goods_receipt_line_id' => $induk->goods_receipt_line_id,
                    'qty' => $qty,
                    'qty_remaining' => $qty,
                    'status' => PackageLabelStatus::InStock,
                    'warehouse_id' => $induk->warehouse_id,
                    'bin_id' => $induk->bin_id,
                    'created_by' => $actor?->id,
                ]);

                $this->ledger->catat($anak, $qty, ['type' => null, 'id' => null, 'line_id' => null, 'number' => $induk->code], $actor,
                    ['warehouse_id' => $induk->warehouse_id, 'to_bin_id' => $induk->bin_id]);
                $dibuat->push($anak);
            }

            return $dibuat;
        });
    }

    /**
     * Usulan jumlah label isi: isi induk ÷ isi kemasan item yang lebih kecil dari
     * induk (mis. DUS isi 12 BOX → 12), atau jumlah satuan dasar bila ≤ 200.
     */
    public function suggest(PackageLabel $induk): int
    {
        $isi = (float) $induk->qty_remaining;
        $kemasan = $induk->item?->activeConversions
            ->first(fn ($k) => ! $k->is_nominal_piece && (float) $k->qty_base > 1 && (float) $k->qty_base < $isi - self::EPS);

        if ($kemasan !== null) {
            return max(1, (int) ceil($isi / (float) $kemasan->qty_base - 1e-6));
        }

        return $isi <= 200 && abs($isi - round($isi)) < self::EPS ? max(1, (int) round($isi)) : 1;
    }
}
