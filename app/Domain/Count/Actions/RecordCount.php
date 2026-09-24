<?php

declare(strict_types=1);

namespace App\Domain\Count\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Count\Enums\CountAssignmentStatus;
use App\Domain\Count\Exceptions\CountRuleException;
use App\Domain\Count\Models\CountAssignment;
use App\Domain\Count\Models\CountLine;
use App\Domain\Count\Models\StockCount;
use App\Domain\Count\Support\CountProgress;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Lot;
use App\Domain\Stock\Enums\StockStatus;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `count.record` — hitung buta satu penugasan (Blueprint §9,
 * Glosarium `blind_count`, A-95, A-100).
 *
 * Hanya penghitung yang ditugaskan yang boleh mengisi; angka sistem tidak
 * pernah dikembalikan ke layarnya. Serial dan potongan dihitung "ada / tidak
 * ada". Barang yang ditemukan tetapi tidak ada di snapshot boleh dicatat
 * untuk item tanpa pelacakan atau ber-lot (lot yang sudah ada).
 *
 * `finish()` menutup penugasan setelah semua baris terisi, lalu sesi dinilai
 * (`CountProgress`): klasifikasi selisih dan hitung ulang otomatis.
 */
class RecordCount
{
    public function __construct(private readonly CountProgress $progress) {}

    /**
     * @param  array<int|string, mixed>  $qtys  count_line_id => jumlah (unit: >0 = ada)
     */
    public function save(CountAssignment $assignment, array $qtys, ?User $actor = null): CountAssignment
    {
        $this->pastikan($assignment, $actor);
        $kolom = $this->kolom($assignment);
        $baris = $assignment->linesQuery()->get()->keyBy('id');

        DB::transaction(function () use ($qtys, $baris, $kolom) {
            foreach ($qtys as $id => $nilai) {
                /** @var CountLine|null $l */
                $l = $baris->get((int) $id);

                if ($l === null) {
                    throw CountRuleException::rule('BR-OPN-05', 'Ada baris yang bukan bagian dari penugasan ini.');
                }

                if ($nilai === null || $nilai === '') {
                    $l->forceFill([$kolom => null])->save();

                    continue;
                }

                if (! is_numeric($nilai) || (float) $nilai < 0) {
                    throw CountRuleException::field('BR-LED-02', 'qty.'.$l->id, 'Jumlah hitung harus angka nol atau lebih.');
                }

                $qty = round((float) $nilai, 4);

                // Serial & potongan: ada (= angka sistem) atau tidak ada (0).
                if ($l->isUnitLine()) {
                    $qty = $qty > 0 ? (float) $l->system_qty : 0.0;
                }

                $l->forceFill([$kolom => $qty])->save();
            }
        });

        activity('count')->performedOn($assignment->stockCount)->causedBy($actor)
            ->withProperties(['bin' => $assignment->bin?->code, 'putaran' => $assignment->round, 'baris' => count($qtys)])
            ->log('Hitungan bin '.$assignment->bin?->code.' disimpan');

        return $assignment->refresh();
    }

    /** Barang ditemukan di bin tetapi tidak ada di snapshot. */
    public function addLine(CountAssignment $assignment, int $itemId, ?string $lotNo, mixed $qty, ?User $actor = null): CountLine
    {
        $this->pastikan($assignment, $actor);

        $item = Item::query()->find($itemId);

        if ($item === null) {
            throw CountRuleException::field('BR-GEN-11', 'item_id', 'Item wajib dipilih.');
        }

        if (! in_array($item->tracking_mode, [TrackingMode::None, TrackingMode::Lot], true)) {
            throw CountRuleException::rule(
                'BR-LED-03',
                'Serial atau potongan yang tidak tercatat dicatat lewat ADJ manual, bukan dari halaman hitung.',
            );
        }

        $lotId = null;

        if ($item->tracking_mode === TrackingMode::Lot) {
            $lotId = Lot::query()->where('item_id', $item->id)
                ->where('lot_no', mb_strtoupper(trim((string) $lotNo)))->value('id');

            if ($lotId === null) {
                throw CountRuleException::field('BR-LED-03', 'lot_no', 'Nomor lot tidak dikenal; lot baru dicatat lewat ADJ manual.');
            }
        }

        if (! is_numeric($qty) || (float) $qty <= 0) {
            throw CountRuleException::field('BR-LED-02', 'qty', 'Jumlah temuan harus lebih dari nol.');
        }

        $sudahAda = CountLine::query()->where('stock_count_id', $assignment->stock_count_id)
            ->where('bin_id', $assignment->bin_id)->where('item_id', $item->id)
            ->when($lotId !== null, fn ($q) => $q->where('lot_id', $lotId), fn ($q) => $q->whereNull('lot_id'))
            ->whereNull('serial_id')->whereNull('piece_id')
            ->where('stock_status', StockStatus::Available->value)
            ->exists();

        if ($sudahAda) {
            throw CountRuleException::rule('BR-OPN-01', 'Item ini sudah ada di daftar bin; isi jumlahnya di baris tersebut.');
        }

        $line = CountLine::create([
            'stock_count_id' => $assignment->stock_count_id,
            'bin_id' => $assignment->bin_id,
            'item_id' => $item->id,
            'lot_id' => $lotId,
            'stock_status' => StockStatus::Available,
            'is_unexpected' => true,
            'is_recount' => $assignment->round === 2,
            'system_qty' => 0,
            $this->kolom($assignment) => round((float) $qty, 4),
        ]);

        activity('count')->performedOn($assignment->stockCount)->causedBy($actor)
            ->withProperties(['bin' => $assignment->bin?->code, 'item' => $item->code])
            ->log('Temuan barang di luar catatan: '.$item->code.' di bin '.$assignment->bin?->code);

        return $line;
    }

    public function finish(CountAssignment $assignment, ?User $actor = null): CountAssignment
    {
        $this->pastikan($assignment, $actor);
        $kolom = $this->kolom($assignment);

        if ($assignment->linesQuery()->whereNull($kolom)->exists()) {
            throw CountRuleException::rule('BR-OPN-05', 'Masih ada baris yang belum diisi. Isi 0 bila barangnya tidak ada.');
        }

        return DB::transaction(function () use ($assignment, $actor) {
            $assignment->forceFill([
                'status' => CountAssignmentStatus::Done,
                'counted_at' => now(),
            ])->save();

            activity('count')->performedOn($assignment->stockCount)->causedBy($actor)
                ->withProperties(['bin' => $assignment->bin?->code, 'putaran' => $assignment->round])
                ->log('Bin '.$assignment->bin?->code.' selesai dihitung (putaran '.$assignment->round.')');

            $this->progress->evaluate(StockCount::withoutGlobalScopes()->findOrFail($assignment->stock_count_id), $actor);

            return $assignment->refresh();
        });
    }

    private function pastikan(CountAssignment $assignment, ?User $actor): void
    {
        $count = StockCount::withoutGlobalScopes()->findOrFail($assignment->stock_count_id);

        if (! $count->status->isCounting()) {
            throw CountRuleException::rule('BR-GEN-01', 'Sesi tidak sedang dalam tahap hitung.');
        }

        if ($assignment->isDone()) {
            throw CountRuleException::rule('BR-OPN-05', 'Bin ini sudah selesai dihitung.');
        }

        if ($actor === null || (int) $assignment->counter_user_id !== (int) $actor->id) {
            throw CountRuleException::rule('BR-OPN-05', 'Bin ini ditugaskan kepada penghitung lain.');
        }
    }

    private function kolom(CountAssignment $assignment): string
    {
        return $assignment->round === 2 ? 'counted_qty_r2' : 'counted_qty_r1';
    }
}
