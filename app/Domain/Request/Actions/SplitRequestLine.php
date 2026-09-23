<?php

declare(strict_types=1);

namespace App\Domain\Request\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Request\Exceptions\RequestRuleException;
use App\Domain\Request\Models\MaterialRequestLine;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `request.split_line` — memecah satu baris ke beberapa gudang
 * sumber (A-56, BR-REQ-04).
 *
 * Dipakai ketika tidak ada satu gudang pun yang punya stok cukup. Tiap pecahan
 * mengirim langsung ke tujuan, tanpa dikumpulkan dulu lewat transfer, sehingga
 * barang tidak berjalan dua kali.
 *
 * Total selalu terjaga: baris asal berkurang persis sebanyak pecahannya.
 */
class SplitRequestLine
{
    /**
     * @param  array<int, array{warehouse_id: int|string, qty_base: float|string}>  $parts
     * @return array<int, MaterialRequestLine>
     */
    public function handle(MaterialRequestLine $line, array $parts, ?User $actor = null): array
    {
        if (! $line->request->status->isEditable()) {
            throw RequestRuleException::rule(
                'BR-REQ-04',
                'Baris hanya bisa dipecah selama REQ masih ditinjau.',
            );
        }

        $pecahan = $this->bersihkan($parts);
        $total = array_sum(array_column($pecahan, 'qty_base'));

        if ($total > (float) $line->qty_base) {
            throw RequestRuleException::field(
                'BR-REQ-04',
                'qty_base',
                'Total pecahan ('.$total.') melebihi jumlah baris ('.(float) $line->qty_base.').',
            );
        }

        if ($total >= (float) $line->qty_base) {
            throw RequestRuleException::field(
                'BR-REQ-04',
                'qty_base',
                'Sisa untuk baris asal harus lebih dari nol; kurangi jumlah pecahan.',
            );
        }

        return DB::transaction(function () use ($line, $pecahan, $total, $actor) {
            $hasil = [];

            foreach ($pecahan as $bagian) {
                $hasil[] = $this->buatPecahan($line, $bagian);
            }

            $line->forceFill(['qty_base' => (float) $line->qty_base - $total])->save();

            activity('request')
                ->performedOn($line->request)
                ->causedBy($actor)
                ->withProperties(['baris' => $line->id, 'pecahan' => count($hasil), 'sisa' => (float) $line->qty_base])
                ->log('Baris REQ dipecah antar gudang');

            return $hasil;
        });
    }

    /** @param  array{warehouse_id: int, qty_base: float}  $bagian */
    private function buatPecahan(MaterialRequestLine $line, array $bagian): MaterialRequestLine
    {
        Warehouse::query()->findOrFail($bagian['warehouse_id']);

        return MaterialRequestLine::create([
            'material_request_id' => $line->material_request_id,
            'item_id' => $line->item_id,
            'non_catalog_text' => $line->non_catalog_text,
            'line_ownership' => $line->line_ownership,
            'required_date' => $line->required_date,
            'promised_date' => $line->promised_date,
            'source_warehouse_id' => $bagian['warehouse_id'],
            'fulfillment_source' => $line->fulfillment_source,
            'qty_base' => $bagian['qty_base'],
            'nominal_length' => $line->nominal_length,
            'split_from_line_id' => $line->id,
            'notes' => $line->notes,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $parts
     * @return array<int, array{warehouse_id: int, qty_base: float}>
     */
    private function bersihkan(array $parts): array
    {
        $hasil = [];

        foreach ($parts as $bagian) {
            $gudang = (int) ($bagian['warehouse_id'] ?? 0);
            $qty = (float) ($bagian['qty_base'] ?? 0);

            if ($gudang === 0 || $qty <= 0) {
                continue;
            }

            $hasil[] = ['warehouse_id' => $gudang, 'qty_base' => $qty];
        }

        if ($hasil === []) {
            throw RequestRuleException::rule('BR-REQ-04', 'Isi minimal satu gudang dan jumlah untuk pecahan.');
        }

        return $hasil;
    }
}
