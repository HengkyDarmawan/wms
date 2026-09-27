<?php

declare(strict_types=1);

namespace App\Domain\Template\Support;

use App\Domain\Access\Models\User;
use App\Domain\Template\Enums\DocumentTemplateType;
use App\Domain\Template\Models\PrintLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Riwayat cetak dokumen (A-263). Setiap unduhan PDF lewat `/print/{type}/{id}`
 * dicatat dengan nomor cetakan per dokumen × jenis cetak. Cetakan kedua dan
 * seterusnya dari dokumen yang bisa disalahgunakan bila berlipat (SJ, Bukti
 * Terima, PO) diberi tanda "CETAK ULANG ke-n"; dokumen lain cukup "Cetakan ke-n"
 * di kaki halaman. Mencatat bukan transisi status (P-04 tetap terjaga).
 */
class PrintHistory
{
    /** Jenis cetak yang diberi tanda besar "CETAK ULANG ke-n". */
    public const DITANDAI = [
        DocumentTemplateType::Shipment,
        DocumentTemplateType::ProofOfDelivery,
        DocumentTemplateType::PurchaseOrder,
    ];

    public function record(DocumentTemplateType $type, Model $model, string $number, ?User $actor, ?string $ip = null): PrintLog
    {
        return DB::transaction(function () use ($type, $model, $number, $actor, $ip) {
            $terakhir = (int) PrintLog::query()
                ->where('document_type', $type->value)->where('document_id', $model->getKey())
                ->lockForUpdate()->max('copy_no');

            return PrintLog::query()->create([
                'document_type' => $type,
                'document_id' => $model->getKey(),
                'document_number' => mb_substr($number, 0, 60),
                'copy_no' => $terakhir + 1,
                'printed_by' => $actor?->id,
                'printed_at' => now(),
                'ip' => $ip,
            ]);
        });
    }

    public static function marked(DocumentTemplateType $type): bool
    {
        return in_array($type, self::DITANDAI, true);
    }

    /**
     * Riwayat cetak untuk layar detail: semua jenis cetak yang lahir dari model ini.
     *
     * @return Collection<int, PrintLog>
     */
    public function forModel(Model $model): Collection
    {
        $jenis = self::typesFor($model);

        if ($jenis === []) {
            return collect();
        }

        return PrintLog::query()->with('printer:id,name')
            ->whereIn('document_type', array_map(fn (DocumentTemplateType $t) => $t->value, $jenis))
            ->where('document_id', $model->getKey())
            ->orderByDesc('id')->limit(50)->get();
    }

    /** @return array<int, DocumentTemplateType> */
    public static function typesFor(Model $model): array
    {
        $kelas = class_basename($model);

        return match ($kelas) {
            'Shipment' => [DocumentTemplateType::Shipment, DocumentTemplateType::ProofOfDelivery],
            'PickTask' => [DocumentTemplateType::PickTask],
            'DeliveryDiscrepancy' => [DocumentTemplateType::DeliveryDiscrepancy],
            'VendorReturn' => [DocumentTemplateType::VendorReturn],
            'StockAdjustment' => [DocumentTemplateType::StockAdjustment],
            'MaterialIssue' => [DocumentTemplateType::MaterialIssue],
            'Conversion' => [DocumentTemplateType::Conversion],
            'WasteDisposal' => [DocumentTemplateType::WasteDisposal],
            'AssetHandover' => [DocumentTemplateType::AssetHandover],
            'PurchaseOrder' => [DocumentTemplateType::PurchaseOrder],
            'Transfer' => [DocumentTemplateType::Transfer],
            'GoodsReturn' => [DocumentTemplateType::GoodsReturn],
            'PurchaseRequest' => [DocumentTemplateType::PurchaseRequest],
            'GoodsReceipt' => [DocumentTemplateType::GoodsReceipt],
            default => [],
        };
    }
}
