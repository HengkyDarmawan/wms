<?php

declare(strict_types=1);

namespace App\Domain\Shared\Reports\Definitions;

use App\Domain\Conversion\Enums\ConversionStatus;
use App\Domain\Conversion\Models\Conversion;
use App\Domain\Master\Models\Project;
use App\Domain\Shared\Reports\Report;
use Illuminate\Support\Collection;

/**
 * Laporan *Konversi & waste* (Blueprint §6.9a, BR-CNV-01–05): per proyek dan
 * item input — total input, output, offcut, waste, kerf, dan persentase waste
 * dari CNV selesai (pembalik mengurangi). Cakupan CNV lewat global scope.
 */
class ConversionWasteReport extends Report
{
    public function key(): string
    {
        return 'konversi-waste';
    }

    public function title(): string
    {
        return 'Konversi & waste';
    }

    public function permission(): string
    {
        return 'conversion.view';
    }

    public function description(): string
    {
        return 'Per proyek dan item input: input, output, offcut, waste, kerf, dan persentase waste dari konversi selesai.';
    }

    public function columns(): array
    {
        return [
            'proyek' => 'Proyek', 'kode_item' => 'Item input', 'jumlah_cnv' => 'Jumlah CNV', 'input' => 'Input',
            'output' => 'Output', 'offcut' => 'Offcut', 'waste' => 'Waste', 'kerf' => 'Kerf', 'persen_waste' => '% waste',
        ];
    }

    public function filters(): array
    {
        $ids = auth()->user()?->accessibleProjectIds();

        return [
            'project_id' => ['label' => 'Proyek', 'options' => Project::query()->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))
                ->orderBy('code')->get(['id', 'code', 'name'])->mapWithKeys(fn (Project $p) => [$p->id => $p->code.' — '.$p->name])->all()],
        ];
    }

    public function rows(array $filters): Collection
    {
        $cnv = Conversion::query()
            ->with('project:id,code', 'inputs.item:id,code')
            ->where('status', ConversionStatus::Completed->value)
            ->when((int) ($filters['project_id'] ?? 0) > 0, fn ($q) => $q->where('project_id', (int) $filters['project_id']))
            ->get();

        $data = [];

        foreach ($cnv as $c) {
            // CNV pembalik membatalkan angka CNV asalnya (A-157).
            $tanda = $c->reversal_of_id !== null ? -1 : 1;
            $item = $c->inputs->first()?->item?->code ?? '—';
            $k = ($c->project?->code ?? '—').'|'.$item;
            $data[$k] ??= ['proyek' => $c->project?->code, 'kode_item' => $item, 'jumlah_cnv' => 0, 'input' => 0.0, 'output' => 0.0, 'offcut' => 0.0, 'waste' => 0.0, 'kerf' => 0.0];
            $data[$k]['jumlah_cnv'] += $tanda;

            foreach (['input' => 'total_input', 'output' => 'total_output', 'offcut' => 'total_offcut', 'waste' => 'total_waste', 'kerf' => 'total_kerf'] as $kolom => $sumber) {
                $data[$k][$kolom] += $tanda * (float) $c->{$sumber};
            }
        }

        return collect($data)
            ->filter(fn ($d) => $d['input'] > 0.00005)
            ->map(fn ($d) => array_merge(array_map(fn ($v) => is_float($v) ? round($v, 4) : $v, $d), [
                'persen_waste' => round(($d['waste'] + $d['kerf']) / $d['input'] * 100, 2),
            ]))
            ->sortBy(fn ($d) => $d['proyek'].'|'.$d['kode_item'])
            ->values();
    }
}
