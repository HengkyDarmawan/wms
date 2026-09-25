<?php

declare(strict_types=1);

namespace App\Domain\Count\Support;

use App\Domain\Access\Models\User;
use App\Domain\Count\Models\CountLine;
use App\Domain\Count\Models\StockCount;
use App\Domain\Shared\Attachments\Enums\AttachmentKind;
use App\Domain\Shared\Attachments\Models\Attachment;
use App\Domain\Shared\Attachments\Support\AttachmentStore;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;

/**
 * Laporan PDF sesi opname (Katalog §2.13 "Laporan PDF terbit", A-101, A-238).
 * Dibuat saat diminta selama sesi `approved`; saat sesi `closed` laporan akhir
 * diarsipkan sekali sebagai lampiran `report` dan `report_attachment_id`
 * menunjuknya, sehingga unduhan berikutnya tidak berubah walau data acuan
 * (nama item, bin) diubah belakangan.
 */
class CountReportArchive
{
    public function __construct(private readonly AttachmentStore $store) {}

    public function render(StockCount $count): string
    {
        $count->load('warehouses:id,code,name', 'creator:id,name', 'submitter:id,name', 'approver:id,name');

        return Pdf::loadView('count.report', [
            'count' => $count,
            'lines' => CountLine::query()->with('bin:id,code', 'item:id,code,name', 'lot', 'serial', 'piece')
                ->where('stock_count_id', $count->id)->orderBy('bin_id')->orderBy('id')->get(),
            'adjustments' => $count->adjustments()->orderBy('id')->get(),
        ])->setPaper('a4', 'landscape')->output();
    }

    public function fileName(StockCount $count): string
    {
        return str_replace('/', '-', $count->number).'.pdf';
    }

    /** Arsipkan laporan akhir; galat hanya dicatat supaya penutupan sesi tidak gagal. */
    public function archive(StockCount $count, ?User $actor = null): ?Attachment
    {
        try {
            $lampiran = $this->store->content($count, AttachmentKind::Report, $this->render($count), 'pdf', 'application/pdf', $this->fileName($count), $actor);
            $count->forceFill(['report_attachment_id' => $lampiran->id])->save();

            return $lampiran;
        } catch (\Throwable $e) {
            Log::warning('Arsip laporan opname gagal: '.$e->getMessage(), ['stock_count' => $count->id]);

            return null;
        }
    }

    public function archived(StockCount $count): ?Attachment
    {
        return $count->report_attachment_id === null ? null : Attachment::query()->find($count->report_attachment_id);
    }
}
