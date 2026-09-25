<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Domain\Master\Enums\ProjectStatus;
use App\Domain\Master\Models\Project;
use App\Domain\Request\Enums\MaterialRequestStatus;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Return\Enums\GoodsReturnStatus;
use App\Domain\Return\Enums\ReturnSource;
use App\Domain\Return\Models\GoodsReturn;
use App\Domain\Return\Support\ReturnableStock;
use App\Domain\Shipment\Models\ProofOfDelivery;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Beranda portal klien (BR-PRJ-07): proyek klien, angka yang menuntut
 * tindakan klien, dan Stok On-site per proyek (BR-PRJ-05, sub-tampilan
 * Di Gudang Site & Terkirim ke Klien) dari sumber yang sama dengan hub proyek.
 */
class PortalDashboardController extends Controller
{
    public function __invoke(Request $request, ReturnableStock $stok): View
    {
        $user = $request->user();
        $klienId = (int) $user->client_id;
        $milikKlien = fn (Builder $p) => $p->where('client_id', $klienId);

        $proyek = Project::query()->where('client_id', $klienId)
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [ProjectStatus::Active->value])
            ->orderBy('code')->get();

        $reqFinal = array_map(fn ($s) => $s->value, array_filter(MaterialRequestStatus::cases(), fn ($s) => $s->isFinal()));
        $retFinal = array_map(fn ($s) => $s->value, array_filter(GoodsReturnStatus::cases(), fn ($s) => $s->isFinal()));

        $stokProyek = $proyek->where('status', ProjectStatus::Active)->take(10)->mapWithKeys(fn (Project $p) => [
            $p->id => $stok->forProject($p)
                ->filter(fn (array $c) => in_array($c['source'], [ReturnSource::SiteStock, ReturnSource::DeliveredToClient], true))
                ->groupBy(fn (array $c) => $c['source']->value),
        ]);

        return view('portal.dashboard', [
            'user' => $user,
            'company' => tenant(),
            'proyek' => $proyek,
            'angka' => [
                'req' => MaterialRequest::query()->whereHas('project', $milikKlien)->whereNotIn('status', $reqFinal)->count(),
                'konfirmasi' => ProofOfDelivery::query()->awaitingConfirmation()
                    ->whereHas('shipment.destinationProject', $milikKlien)->count(),
                'retur' => GoodsReturn::query()->whereHas('project', $milikKlien)->whereNotIn('status', $retFinal)->count(),
            ],
            'stokProyek' => $stokProyek,
        ]);
    }
}
