<?php

declare(strict_types=1);

namespace App\Domain\Asset\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Asset\Enums\MaintenanceSource;
use App\Domain\Asset\Enums\MaintenanceStatus;
use App\Domain\Asset\Exceptions\AssetRuleException;
use App\Domain\Asset\Models\MaintenanceSchedule;
use App\Domain\Master\Enums\AssetState;
use App\Domain\Master\Models\Serial;
use App\Domain\Stock\Exceptions\LedgerException;
use App\Domain\Stock\Models\StockBalance;
use App\Domain\Stock\Models\StockReservation;
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Warehouse\Enums\BinStatus;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `asset.maintain` — jadwal servis aset (BR-AST-07, A-423,
 * 33-pendukung-f2 §4).
 *
 * - plan: jadwal `planned` baru, atau ubah tanggal/interval/catatan jadwal `planned`.
 * - start: `planned → in_progress`; aset dipindah lewat kartu stok dari bin
 *   penyimpanannya ke bin Karantina gudang yang sama. State `maintenance`
 *   ditulis AssetStateSync (BR-AST-01), bukan di sini; aset di Karantina bukan
 *   stok tersedia sehingga tidak bisa dicadangkan.
 * - finish: `in_progress → done`; aset dipindah ke bin penyimpanan pilihan
 *   (bawaan bin asal). Jadwal berkala melahirkan jadwal berikutnya.
 */
class MaintainAsset
{
    /** A-427: skor pemeriksaan di bawah ini membuat jadwal servis otomatis. */
    public const SKOR_AMBANG = 60;

    public function __construct(private readonly StockLedger $ledger) {}

    /**
     * @param  array{scheduled_at?: mixed, interval_days?: mixed, notes?: mixed}  $data
     */
    public function plan(Serial $serial, array $data, User $actor, ?MaintenanceSchedule $jadwal = null): MaintenanceSchedule
    {
        $this->izin($actor, $this->gudangAset($serial));
        $this->pastikanAset($serial);

        $tanggal = $this->tanggal($data['scheduled_at'] ?? null);
        $interval = $this->interval($data['interval_days'] ?? null);
        $catatan = $this->teks($data['notes'] ?? null);

        if ($jadwal !== null) {
            if ((int) $jadwal->serial_id !== (int) $serial->id || $jadwal->status !== MaintenanceStatus::Planned) {
                throw AssetRuleException::rule('BR-GEN-01', 'Hanya jadwal yang masih direncanakan yang bisa diubah.');
            }

            $jadwal->update(['scheduled_at' => $tanggal, 'interval_days' => $interval, 'notes' => $catatan]);
            $this->log($jadwal, $actor, 'Jadwal servis diubah ke '.$tanggal);

            return $jadwal->refresh();
        }

        return DB::transaction(function () use ($serial, $tanggal, $interval, $catatan, $actor) {
            Serial::query()->whereKey($serial->id)->lockForUpdate()->first();

            if (MaintenanceSchedule::query()->where('serial_id', $serial->id)->open()->exists()) {
                throw AssetRuleException::rule('BR-AST-07', 'Aset ini sudah punya jadwal servis yang belum selesai.');
            }

            $jadwal = MaintenanceSchedule::create([
                'serial_id' => $serial->id,
                'scheduled_at' => $tanggal,
                'interval_days' => $interval,
                'status' => MaintenanceStatus::Planned,
                'source' => MaintenanceSource::Manual,
                'notes' => $catatan,
                'created_by' => $actor->id,
            ]);
            $this->log($jadwal, $actor, 'Servis dijadwalkan '.$tanggal);

            return $jadwal;
        });
    }

    public function start(MaintenanceSchedule $jadwal, User $actor, ?string $catatan = null): MaintenanceSchedule
    {
        return DB::transaction(function () use ($jadwal, $actor, $catatan) {
            $jadwal = MaintenanceSchedule::query()->lockForUpdate()->findOrFail($jadwal->id);
            $serial = Serial::query()->with('item')->findOrFail($jadwal->serial_id);

            if ($jadwal->status !== MaintenanceStatus::Planned) {
                throw AssetRuleException::rule('BR-GEN-01', 'Servis ini sudah dimulai atau selesai.');
            }

            $saldo = $this->saldoAset($serial);

            if ($saldo === null || $saldo->bin->bin_type !== BinType::Storage || $serial->asset_state !== AssetState::Available) {
                throw AssetRuleException::rule('BR-AST-07', 'Servis hanya bisa dimulai saat aset tersedia di bin penyimpanan gudang (sekarang: '.($serial->asset_state?->label() ?? '-').').');
            }

            $this->izin($actor, (int) $saldo->bin->warehouse_id);

            if (StockReservation::query()->active()->where('serial_id', $serial->id)->exists()) {
                throw AssetRuleException::rule('BR-AST-07', 'Aset ini sedang dicadangkan. Lepaskan reservasinya dulu.');
            }

            $karantina = Bin::query()->withoutGlobalScopes()
                ->where('warehouse_id', $saldo->bin->warehouse_id)
                ->where('bin_type', BinType::Quarantine->value)
                ->where('bin_status', BinStatus::Active->value)
                ->orderBy('id')->first()
                ?? throw AssetRuleException::rule('BR-AST-07', 'Gudang ini belum punya bin Karantina aktif.');

            $gerak = $this->pindah($saldo, $karantina, $actor, $jadwal, 'Mulai servis');

            $jadwal->update([
                'status' => MaintenanceStatus::InProgress,
                'origin_bin_id' => $saldo->bin_id,
                'started_movement_id' => $gerak->id,
                'notes' => $catatan !== null && trim($catatan) !== '' ? $this->teks($catatan) : $jadwal->notes,
            ]);
            $this->log($jadwal, $actor, 'Servis dimulai: '.$saldo->bin->code.' → '.$karantina->code);

            return $jadwal->refresh();
        });
    }

    public function finish(MaintenanceSchedule $jadwal, int $binTujuanId, User $actor, ?string $catatan = null): MaintenanceSchedule
    {
        return DB::transaction(function () use ($jadwal, $binTujuanId, $actor, $catatan) {
            $jadwal = MaintenanceSchedule::query()->lockForUpdate()->findOrFail($jadwal->id);
            $serial = Serial::query()->with('item')->findOrFail($jadwal->serial_id);

            if ($jadwal->status !== MaintenanceStatus::InProgress) {
                throw AssetRuleException::rule('BR-GEN-01', 'Servis ini belum dimulai atau sudah selesai.');
            }

            $saldo = $this->saldoAset($serial);

            if ($saldo === null || $saldo->bin->bin_type !== BinType::Quarantine) {
                throw AssetRuleException::rule('BR-AST-07', 'Aset tidak lagi berada di bin Karantina; periksa kartu stoknya.');
            }

            $this->izin($actor, (int) $saldo->bin->warehouse_id);

            $tujuan = Bin::query()->withoutGlobalScopes()->where('warehouse_id', $saldo->bin->warehouse_id)->find($binTujuanId);

            if ($tujuan === null || $tujuan->bin_type !== BinType::Storage || $tujuan->bin_status !== BinStatus::Active || $tujuan->occupied_by_bin_id !== null) {
                throw AssetRuleException::field('BR-STK-02', 'to_bin_id', 'Pilih bin penyimpanan aktif di gudang yang sama.');
            }

            $gerak = $this->pindah($saldo, $tujuan, $actor, $jadwal, 'Selesai servis');

            $jadwal->update([
                'status' => MaintenanceStatus::Done,
                'performed_at' => now()->toDateString(),
                'finished_movement_id' => $gerak->id,
                'notes' => $catatan !== null && trim($catatan) !== '' ? $this->teks($catatan) : $jadwal->notes,
            ]);
            $this->log($jadwal, $actor, 'Servis selesai → '.$tujuan->code);

            if ($jadwal->interval_days !== null) {
                MaintenanceSchedule::create([
                    'serial_id' => $serial->id,
                    'scheduled_at' => now()->addDays((int) $jadwal->interval_days)->toDateString(),
                    'interval_days' => $jadwal->interval_days,
                    'status' => MaintenanceStatus::Planned,
                    'source' => MaintenanceSource::Interval,
                    'created_by' => $actor->id,
                ]);
            }

            return $jadwal->refresh();
        });
    }

    /**
     * BR-AST-07: pemeriksaan dengan skor di bawah ambang menjadwalkan servis hari
     * itu, kecuali sudah ada jadwal terbuka. Dipanggil dari InspectAsset di dalam
     * transaksinya; tanpa pemeriksaan izin (pemeriksa sudah lolos `asset.inspect`).
     */
    public static function dariPemeriksaan(Serial $serial, int $skor, ?User $actor): ?MaintenanceSchedule
    {
        if ($skor >= self::SKOR_AMBANG || MaintenanceSchedule::query()->where('serial_id', $serial->id)->open()->exists()) {
            return null;
        }

        return MaintenanceSchedule::create([
            'serial_id' => $serial->id,
            'scheduled_at' => now()->toDateString(),
            'status' => MaintenanceStatus::Planned,
            'source' => MaintenanceSource::Inspection,
            'notes' => mb_substr('Skor pemeriksaan '.$skor.' % (di bawah '.self::SKOR_AMBANG.' %)', 0, 255),
            'created_by' => $actor?->id,
        ]);
    }

    private function pindah(StockBalance $saldo, Bin $tujuan, User $actor, MaintenanceSchedule $jadwal, string $judul)
    {
        try {
            return $this->ledger->post(new MovementRequest(
                item: $saldo->item,
                qtyBase: (float) $saldo->qty_base,
                fromBinId: (int) $saldo->bin_id,
                toBinId: (int) $tujuan->id,
                stockStatus: $saldo->stock_status,
                serialId: $saldo->serial_id,
                documentType: 'maintenance',
                documentId: (int) $jadwal->id,
                performedBy: $actor,
                notes: $judul,
            ));
        } catch (LedgerException $e) {
            throw AssetRuleException::rule($e->rule, $e->getMessage());
        }
    }

    private function saldoAset(Serial $serial): ?StockBalance
    {
        return StockBalance::query()->withoutGlobalScopes()->with('bin', 'item')
            ->where('serial_id', $serial->id)->where('qty_base', '>', 0)->first();
    }

    /** Gudang tempat aset berada sekarang; null bila di luar gudang (dipinjam proyek). */
    private function gudangAset(Serial $serial): ?int
    {
        $bin = $this->saldoAset($serial)?->bin;

        return $bin === null || $bin->is_virtual ? null : (int) $bin->warehouse_id;
    }

    private function izin(User $actor, ?int $gudangId): void
    {
        if (! $actor->hasPermission('asset.maintain') || ($gudangId !== null && ! $actor->canAccessWarehouse($gudangId))) {
            throw AssetRuleException::rule('BR-GEN-09', 'Anda tidak berwenang mengatur servis aset ini.');
        }
    }

    private function pastikanAset(Serial $serial): void
    {
        $serial->loadMissing('item');

        if ($serial->item === null || ! $serial->item->isAsset()) {
            throw AssetRuleException::rule('BR-AST-07', 'Jadwal servis hanya untuk aset.');
        }

        if (in_array($serial->asset_state, [AssetState::Lost, AssetState::WrittenOff], true)) {
            throw AssetRuleException::rule('BR-AST-07', 'Aset hilang atau sudah dihapuskan tidak bisa dijadwalkan servis.');
        }
    }

    private function tanggal(mixed $nilai): string
    {
        $isi = is_scalar($nilai) ? trim((string) $nilai) : '';

        try {
            return $isi === '' ? throw new \InvalidArgumentException : Carbon::parse($isi)->toDateString();
        } catch (\Throwable) {
            throw AssetRuleException::field('BR-GEN-11', 'scheduled_at', 'Tanggal servis wajib diisi dengan tanggal yang benar.');
        }
    }

    private function interval(mixed $nilai): ?int
    {
        if ($nilai === null || trim((string) $nilai) === '') {
            return null;
        }

        if (! ctype_digit(trim((string) $nilai)) || (int) $nilai < 1 || (int) $nilai > 3650) {
            throw AssetRuleException::field('BR-GEN-11', 'interval_days', 'Ulangi tiap diisi 1–3650 hari, atau kosongkan.');
        }

        return (int) $nilai;
    }

    private function teks(mixed $nilai): ?string
    {
        $isi = is_scalar($nilai) ? trim((string) $nilai) : '';

        return $isi === '' ? null : mb_substr($isi, 0, 255);
    }

    private function log(MaintenanceSchedule $jadwal, User $actor, string $pesan): void
    {
        activity('asset')->performedOn($jadwal)->causedBy($actor)
            ->withProperties(['serial_id' => (int) $jadwal->serial_id, 'status' => $jadwal->status?->value])
            ->log($pesan);
    }
}
