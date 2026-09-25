<?php

declare(strict_types=1);

namespace App\Domain\Notification\Support;

use App\Domain\Access\Models\User;
use App\Domain\Master\Enums\AssetState;
use App\Domain\Master\Models\CompanySetting;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\Serial;
use App\Domain\Request\Livewire\RequestList;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Stock\Livewire\ReservationList;
use App\Domain\Stock\Models\StockReservation;

/**
 * Pengingat harian `notifications:daily` (Blueprint §10, A-189, A-233). Setiap
 * pengingat diulang tiap hari selama keadaannya bertahan: Notifier tidak
 * menggandakan entri yang sama yang belum dibaca, jadi yang sudah dibaca
 * muncul lagi keesokan harinya.
 */
class DailyReminders
{
    public function __construct(
        private readonly Notifier $notifier,
        private readonly DomainNotifications $domain,
    ) {}

    /** Semua pengingat; hasilnya jumlah entri lonceng yang dibuat. */
    public function run(): int
    {
        return $this->reviewOverdue() + $this->staleReservations() + $this->assetsOverdue() + $this->assetsLifeAlert();
    }

    /** REQ melewati SLA tinjau (14 §8, BR-REQ-14): pemegang `request.review` di proyeknya. */
    public function reviewOverdue(): int
    {
        $sla = max(1, (int) CompanySetting::get(RequestList::SLA, 1));
        $jumlah = 0;

        foreach (MaterialRequest::query()->withoutGlobalScopes()->reviewOverdue($sla)->get() as $req) {
            $jumlah += $this->notifier->send(
                $this->notifier->recipients('request.review', null, (int) $req->project_id),
                'request.review_overdue', 'REQ '.$req->number.' melewati SLA tinjau',
                'Menunggu tinjauan sejak '.$req->created_at?->timezone(tenant()?->timezone ?? 'Asia/Jakarta')->format('d/m/Y H:i').'.',
                route('requests.show', $req->id, false), 'material_request', (int) $req->id,
            );
        }

        return $jumlah;
    }

    /**
     * Reservasi lunak menggantung melewati ambang (13 §8, BR-STK-16): Kepala
     * Gudang (pemegang `reservation.release`) di gudangnya dan pemohon REQ.
     * Satu entri per dokumen, bukan per baris reservasi.
     */
    public function staleReservations(): int
    {
        $ambang = max(1, (int) CompanySetting::get(ReservationList::AMBANG, 7));
        $jumlah = 0;

        $grup = StockReservation::query()->stale($ambang)->get()
            ->groupBy(fn (StockReservation $r) => $r->document_type.'#'.$r->document_id);

        foreach ($grup as $baris) {
            /** @var StockReservation $r */
            $r = $baris->first();
            $req = $r->document_type === 'material_request'
                ? MaterialRequest::query()->withoutGlobalScopes()->find($r->document_id)
                : null;
            $judul = 'Reservasi '.($req?->number ?? $r->document_type.' #'.$r->document_id)
                .' menggantung '.$baris->max(fn (StockReservation $x) => $x->ageInDays()).' hari';
            $isi = $baris->count().' baris belum dibuatkan tugas picking.';
            $kepala = $baris->pluck('warehouse_id')->unique()
                ->flatMap(fn ($gudang) => $this->notifier->recipients('reservation.release', (int) $gudang));

            $jumlah += $this->notifier->send($kepala, 'stock.reservation_stale', $judul, $isi,
                route('stock.reservations', [], false), $r->document_type, (int) $r->document_id);

            $pemohon = $req !== null ? User::query()->find($req->requester_id) : null;

            if ($pemohon !== null) {
                $jumlah += $this->notifier->send($pemohon, 'stock.reservation_stale', $judul, $isi,
                    $this->domain->tautanReq($pemohon, $req), $r->document_type, (int) $r->document_id);
            }
        }

        return $jumlah;
    }

    /** Aset lewat jatuh tempo (25 §8, BR-AST-06): pemegang `asset.manage` proyeknya dan PIC proyek. */
    public function assetsOverdue(): int
    {
        $jumlah = 0;

        foreach (Serial::query()->overdue()->with('item:id,code')->get() as $aset) {
            $proyek = $aset->current_project_id !== null ? (int) $aset->current_project_id : null;
            $penerima = $this->notifier->recipients('asset.manage', null, $proyek);
            $pic = $proyek !== null ? Project::query()->whereKey($proyek)->value('pic_user_id') : null;

            if ($pic !== null && ($user = User::query()->find($pic)) !== null) {
                $penerima->push($user);
            }

            $jumlah += $this->notifier->send($penerima,
                'asset.overdue', 'Aset '.$aset->item?->code.' '.$aset->serial_no.' lewat jatuh tempo',
                'Seharusnya kembali '.$aset->due_return_date?->format('d/m/Y').'.', route('assets.show', $aset->id, false),
                'serial', (int) $aset->id,
            );
        }

        return $jumlah;
    }

    /** Sisa umur aset di bawah ambang `asset_life_alert_pct` (25 §8, BR-AST-08, A-169). */
    public function assetsLifeAlert(): int
    {
        $jumlah = 0;

        // Persentase dihitung di PHP (umur kalender dan meter), jadi disaring dulu yang punya umur rencana.
        $aset = Serial::query()->with('item:id,code')
            ->whereNotIn('asset_state', [AssetState::Lost->value, AssetState::WrittenOff->value])
            ->where(fn ($q) => $q->whereNotNull('expected_life_days')->orWhereNotNull('expected_life_hours'))
            ->get()->filter(fn (Serial $s) => $s->isLifeAlert());

        foreach ($aset as $s) {
            $proyek = $s->current_project_id !== null ? (int) $s->current_project_id : null;
            $jumlah += $this->notifier->send(
                $this->notifier->recipients('asset.manage', null, $proyek),
                'asset.life_alert', 'Sisa umur aset '.$s->item?->code.' '.$s->serial_no.' '.$s->remainingLifePercent().' %',
                'Di bawah ambang '.Serial::lifeAlertPercent().' %; rencanakan perawatan atau penggantian.',
                route('assets.show', $s->id, false), 'serial', (int) $s->id,
            );
        }

        return $jumlah;
    }
}
