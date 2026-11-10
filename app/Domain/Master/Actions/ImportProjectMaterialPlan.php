<?php

declare(strict_types=1);

namespace App\Domain\Master\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Master\Exceptions\MasterRuleException;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\ProjectMaterialPlan;
use App\Domain\Master\Support\ExcelRows;
use App\Domain\Master\Support\ImportBatch;
use Illuminate\Http\UploadedFile;

/**
 * Permission: `project.plan` — unggah rencana material proyek dari Excel
 * (BR-PRJ-09, A-428, 34-rencana-material §4). Satu unggahan = satu versi baru
 * seluruh proyek: semua baris valid atau tidak ada yang disimpan; versi lama
 * tetap tersimpan tetapi tidak lagi berlaku (P-03). Jumlah dalam satuan dasar
 * item, tanpa nilai uang (D-07).
 */
class ImportProjectMaterialPlan
{
    public const COLUMNS = [
        'kode_item' => 'Kode item *',
        'jumlah' => 'Jumlah rencana (satuan dasar) *',
    ];

    public const MAX_ROWS = 2000;

    /** @return int jumlah item di versi baru */
    public function handle(Project $project, UploadedFile $file, User $actor): int
    {
        if (! $actor->hasPermission('project.plan') || ! $actor->canAccessProject((int) $project->id)) {
            throw MasterRuleException::rule('BR-GEN-09', 'Anda tidak berwenang mengubah rencana material proyek ini.');
        }

        $baris = ExcelRows::read($file, self::COLUMNS, self::MAX_ROWS);
        $versi = (int) ProjectMaterialPlan::query()->where('project_id', $project->id)->max('version') + 1;
        $item = Item::query()->whereIn('code', array_map(fn (array $r) => mb_strtoupper(trim((string) ($r['kode_item'] ?? ''))), $baris))
            ->get(['id', 'code'])->keyBy(fn (Item $i) => mb_strtoupper($i->code));
        $sudah = [];

        // Versi lama dimatikan di dalam transaksi yang sama: bila ada baris salah, ikut dibatalkan.
        $berlakuLama = true;

        $jumlah = ImportBatch::run($baris, function (array $r, int $nomor) use ($project, $versi, $item, &$sudah, &$berlakuLama, $actor, $file) {
            if ($berlakuLama) {
                ProjectMaterialPlan::query()->where('project_id', $project->id)->where('is_current', true)->update(['is_current' => false]);
                $berlakuLama = false;
            }

            $kode = mb_strtoupper(trim((string) ($r['kode_item'] ?? '')));
            $barang = $item->get($kode)
                ?? throw MasterRuleException::rule('BR-GEN-11', $kode === '' ? 'kode item wajib diisi.' : 'kode item "'.$kode.'" tidak dikenal.');

            if (isset($sudah[$barang->id])) {
                throw MasterRuleException::rule('BR-GEN-11', 'kode item "'.$kode.'" sudah ada di baris '.$sudah[$barang->id].'.');
            }

            $isi = str_replace(',', '.', trim((string) ($r['jumlah'] ?? '')));

            if (! is_numeric($isi) || (float) $isi <= 0) {
                throw MasterRuleException::rule('BR-GEN-11', 'jumlah rencana harus angka lebih dari 0.');
            }

            $sudah[$barang->id] = $nomor;

            ProjectMaterialPlan::create([
                'project_id' => $project->id,
                'item_id' => $barang->id,
                'version' => $versi,
                'planned_qty_base' => round((float) $isi, 4),
                'is_current' => true,
                'created_by' => $actor->id,
                'source_file' => mb_substr($file->getClientOriginalName(), 0, 150),
            ]);
        }, 'rencana');

        activity('master')->performedOn($project)->causedBy($actor)
            ->withProperties(['versi' => $versi, 'item' => $jumlah, 'berkas' => $file->getClientOriginalName()])
            ->log('Rencana material versi '.$versi.' diunggah: '.$jumlah.' item');

        return $jumlah;
    }
}
