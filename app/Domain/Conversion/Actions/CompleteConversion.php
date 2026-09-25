<?php

declare(strict_types=1);

namespace App\Domain\Conversion\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Conversion\Enums\ConversionStatus;
use App\Domain\Conversion\Exceptions\ConversionRuleException;
use App\Domain\Conversion\Models\Conversion;
use App\Domain\Conversion\Support\ConversionApprovalRoute;
use App\Domain\Conversion\Support\ConversionCompletion;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `conversion.complete` — `draft → completed` (Katalog §2.10)
 * untuk CNV **tanpa** aturan approval yang cocok (A-153). Input keluar, hasil
 * masuk, kejadian `material_converted`; CNV pembalik membalik CNV asalnya.
 */
class CompleteConversion
{
    public function __construct(
        private readonly ConversionApprovalRoute $route,
        private readonly ConversionCompletion $selesai,
    ) {}

    public function handle(Conversion $conversion, ?User $actor = null): Conversion
    {
        return DB::transaction(function () use ($conversion, $actor) {
            $cnv = Conversion::withoutGlobalScopes()->lockForUpdate()->findOrFail($conversion->id);

            if ($cnv->status !== ConversionStatus::Draft) {
                throw ConversionRuleException::rule('BR-GEN-01', 'Hanya CNV berstatus Draf yang bisa diselesaikan langsung.');
            }

            if ($actor !== null && ! $actor->canAccessWarehouse((int) $cnv->warehouse_id)) {
                throw ConversionRuleException::rule('BR-ACC-05', 'Gudang CNV ini di luar cakupan Anda.');
            }

            if ($cnv->inputs()->doesntExist()) {
                throw ConversionRuleException::rule('BR-CNV-02', 'CNV tanpa input tidak bisa diselesaikan.');
            }

            if (($aturan = $this->route->rule($cnv)) !== null) {
                throw ConversionRuleException::rule('BR-APR-01', 'CNV ini terkena aturan approval "'.$aturan->name.'"; ajukan ke approval.');
            }

            return $this->selesai->handle($cnv, $actor);
        });
    }
}
