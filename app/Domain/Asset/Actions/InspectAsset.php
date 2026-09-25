<?php

declare(strict_types=1);

namespace App\Domain\Asset\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Asset\Enums\AssetHandoverStatus;
use App\Domain\Asset\Enums\ConditionGrade;
use App\Domain\Asset\Exceptions\AssetRuleException;
use App\Domain\Asset\Models\AssetHandover;
use App\Domain\Asset\Models\AssetInspection;
use App\Domain\Master\Enums\MeterUnit;
use App\Domain\Shared\Files\StoreUpload;
use App\Domain\Stock\Enums\StockEventType;
use App\Domain\Stock\Support\StockLedger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Permission: `asset.inspect` — AST `returned → inspected` (Katalog §2.11).
 *
 * Guard: grade kondisi A–D **dan** skor 0–100 % + minimal satu catatan
 * komponen (BR-AST-08) + foto (Katalog "grade kondisi + foto", BR-AST-03);
 * aset bermeter wajib meter kembali ≥ meter keluar, kecuali meter diganti
 * dengan alasan. Efek: riwayat pemeriksaan, pemakaian jam/km, akumulasi meter,
 * grade & skor terakhir di serial, state aset = hasil grade (A/B `available`,
 * C `maintenance`, D `damaged`, A-164); grade C/D menerbitkan
 * `asset_lost_or_damaged` tanpa pergerakan (matriks §14). Kejadian
 * `asset_returned` terbit saat aset dipilah dari bin Retur.
 */
class InspectAsset
{
    public function __construct(
        private readonly StoreUpload $files,
        private readonly StockLedger $ledger,
    ) {}

    /**
     * @param  array{condition_grade?: mixed, condition_score?: mixed, component_notes?: mixed, meter_in?: mixed, meter_reset_reason?: mixed, notes?: mixed}  $data
     */
    public function handle(AssetHandover $ast, array $data, ?UploadedFile $photo, ?User $actor = null): AssetInspection
    {
        if ($ast->status !== AssetHandoverStatus::Returned) {
            throw AssetRuleException::rule('BR-GEN-01', 'Hanya aset yang sudah kembali (Dikembalikan) yang bisa diperiksa.');
        }

        $ast->loadMissing('serial', 'project');
        $grade = ConditionGrade::tryFrom(strtoupper(trim((string) ($data['condition_grade'] ?? ''))))
            ?? throw AssetRuleException::field('BR-AST-03', 'condition_grade', 'Grade kondisi A–D wajib dipilih.');

        $skor = $data['condition_score'] ?? null;

        if (! is_numeric($skor) || (int) $skor < 0 || (int) $skor > 100 || (float) $skor != (int) $skor) {
            throw AssetRuleException::field('BR-AST-08', 'condition_score', 'Skor kondisi wajib diisi 0–100 %.');
        }

        $komponen = $this->komponen($data['component_notes'] ?? null);

        if ($komponen === []) {
            throw AssetRuleException::field('BR-AST-08', 'component_notes', 'Isi minimal satu catatan komponen (mis. "Mesin: normal").');
        }

        if ($photo === null) {
            throw AssetRuleException::field('BR-AST-03', 'photo', 'Foto pemeriksaan wajib diunggah.');
        }

        [$meterIn, $pakai, $alasanMeter] = $this->meter($ast, $data);

        $path = null;

        try {
            $path = $this->files->handle($photo, 'asset-inspections', $ast->id.'-'.now()->format('YmdHis'));
        } catch (RuntimeException $e) {
            throw AssetRuleException::field('BR-AST-03', 'photo', $e->getMessage());
        }

        try {
            return DB::transaction(function () use ($ast, $grade, $skor, $komponen, $meterIn, $pakai, $alasanMeter, $data, $path, $actor) {
                $ast = AssetHandover::query()->withoutGlobalScopes()->with('serial', 'project')->lockForUpdate()->findOrFail($ast->id);

                if ($ast->status !== AssetHandoverStatus::Returned) {
                    throw AssetRuleException::rule('BR-GEN-01', 'AST ini sudah diperiksa.');
                }

                $serial = $ast->serial;
                $hasil = $grade->resultingState();

                $periksa = AssetInspection::create([
                    'asset_handover_id' => $ast->id,
                    'serial_id' => $serial->id,
                    'inspected_by' => $actor?->id,
                    'inspected_at' => now(),
                    'condition_grade' => $grade,
                    'condition_score' => (int) $skor,
                    'component_notes' => $komponen,
                    'photo_path' => $path,
                    'meter_in' => $meterIn,
                    'meter_reset_reason' => $alasanMeter,
                    'notes' => $this->teks($data['notes'] ?? null),
                    'resulting_state' => $hasil,
                ]);

                $ast->forceFill([
                    'status' => AssetHandoverStatus::Inspected,
                    'meter_in' => $meterIn,
                    'usage_hours' => $serial->meter_unit === MeterUnit::Hour ? $pakai : null,
                    'usage_km' => $serial->meter_unit === MeterUnit::Km ? $pakai : null,
                    'updated_by' => $actor?->id,
                ])->save();

                $serial->forceFill([
                    'condition_grade' => $grade->value,
                    'condition_score' => (int) $skor,
                    'meter_total' => round((float) $serial->meter_total + (float) ($pakai ?? 0), 1),
                    'asset_state' => $hasil,
                    'current_project_id' => null,
                    'due_return_date' => null,
                ])->save();

                // BR-AST-03 / matriks §14: kerusakan diteruskan ke Akuntansi tanpa pergerakan.
                if ($grade->isDamage()) {
                    $this->ledger->emitEvent(StockEventType::AssetLostOrDamaged, [
                        'kind' => 'damaged',
                        'serial_id' => (int) $serial->id,
                        'serial_no' => $serial->serial_no,
                        'item_id' => (int) $serial->item_id,
                        'handover_number' => $ast->number,
                        'condition_grade' => $grade->value,
                        'condition_score' => (int) $skor,
                        'resulting_state' => $hasil->value,
                    ], 'asset_handover', (int) $ast->id, $ast->number, (int) $ast->project_id);
                }

                activity('asset')->performedOn($ast)->causedBy($actor)
                    ->withProperties(['grade' => $grade->value, 'skor' => (int) $skor, 'hasil' => $hasil->value])
                    ->log('Aset diperiksa: grade '.$grade->value.', skor '.(int) $skor.' % → '.$hasil->label());

                return $periksa;
            });
        } catch (Throwable $e) {
            $this->files->delete($path);

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: ?float, 1: ?float, 2: ?string} meter kembali, pemakaian, alasan ganti meter
     */
    private function meter(AssetHandover $ast, array $data): array
    {
        if ($ast->serial->meter_unit === null || $ast->serial->meter_unit === MeterUnit::None) {
            return [null, null, null];
        }

        $isi = $data['meter_in'] ?? null;

        if (! is_numeric($isi) || (float) $isi < 0) {
            throw AssetRuleException::field('BR-AST-08', 'meter_in', 'Meter kembali wajib diisi (angka ≥ 0).');
        }

        $masuk = round((float) $isi, 1);
        $keluar = $ast->meter_out !== null ? (float) $ast->meter_out : null;
        $alasan = $this->teks($data['meter_reset_reason'] ?? null);

        if ($keluar !== null && $masuk < $keluar) {
            if ($alasan === null) {
                throw AssetRuleException::field('BR-AST-08', 'meter_in', 'Meter kembali '.$masuk.' lebih kecil dari meter keluar '.$keluar.'; isi alasan bila meter diganti.');
            }

            // Meter diganti: pemakaian dihitung dari nol meter baru (A-166).
            return [$masuk, $masuk, $alasan];
        }

        return [$masuk, $keluar === null ? null : round($masuk - $keluar, 1), $alasan];
    }

    /**
     * Catatan komponen: array [komponen => catatan] / [{component, note}] atau
     * teks satu baris per komponen "Komponen: catatan".
     *
     * @return array<int, array{component: string, note: string}>
     */
    private function komponen(mixed $isi): array
    {
        $baris = [];

        if (is_string($isi)) {
            foreach (preg_split('/\r\n|\r|\n/', $isi) ?: [] as $l) {
                $l = trim($l);

                if ($l === '') {
                    continue;
                }

                [$k, $c] = array_pad(array_map('trim', explode(':', $l, 2)), 2, '');
                $baris[] = ['component' => mb_substr($k, 0, 60), 'note' => mb_substr($c, 0, 200)];
            }

            return $baris;
        }

        if (is_array($isi)) {
            foreach ($isi as $k => $v) {
                $komponen = is_array($v) ? trim((string) ($v['component'] ?? '')) : trim((string) $k);
                $catatan = is_array($v) ? trim((string) ($v['note'] ?? '')) : trim((string) $v);

                if ($komponen !== '') {
                    $baris[] = ['component' => mb_substr($komponen, 0, 60), 'note' => mb_substr($catatan, 0, 200)];
                }
            }
        }

        return $baris;
    }

    private function teks(mixed $nilai): ?string
    {
        $isi = is_scalar($nilai) ? trim((string) $nilai) : '';

        return $isi === '' ? null : mb_substr($isi, 0, 255);
    }
}
