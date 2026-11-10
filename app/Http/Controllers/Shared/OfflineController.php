<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shared;

use App\Domain\Count\Enums\StockCountStatus;
use App\Domain\Count\Models\CountAssignment;
use App\Domain\Count\Models\CountLine;
use App\Domain\Shared\Support\OfflineSync;
use App\Domain\Shared\Support\OfflineTaskPack;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * PWA offline (32-pwa-offline, AD-16, A-438): halaman hitung opname offline,
 * paket data tugas untuk disimpan di perangkat, dan endpoint sinkron idempoten.
 * Hitung buta: angka sistem tidak pernah dikirim ke perangkat.
 */
class OfflineController extends Controller
{
    public function count(Request $request): View
    {
        abort_unless($request->user()->hasPermission('count.record'), 403);

        return view('offline.count');
    }

    public function countData(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('count.record'), 403);

        $tugas = CountAssignment::query()->with('bin:id,code', 'stockCount:id,number,status')
            ->where('counter_user_id', $request->user()->id)->where('status', 'pending')
            ->whereHas('stockCount', fn ($q) => $q->whereIn('status', [StockCountStatus::InProgress->value, StockCountStatus::Recount->value]))
            ->orderBy('id')->limit(200)->get();

        return response()->json([
            'diunduh' => now()->toIso8601String(),
            'tugas' => $tugas->map(fn (CountAssignment $t) => [
                'id' => $t->id,
                'sesi' => $t->stockCount?->number,
                'bin' => $t->bin?->code,
                'putaran' => (int) $t->round,
                'baris' => $t->linesQuery()->with('item:id,code,name,tracking_mode,base_uom_id', 'item.baseUom:id,code', 'lot:id,lot_no', 'serial:id,serial_no', 'piece:id,piece_no')
                    ->orderBy('item_id')->orderBy('id')->get()
                    ->map(fn (CountLine $l) => [
                        'id' => $l->id,
                        'kode' => $l->item?->code,
                        'nama' => $l->item?->name,
                        'satuan' => $l->item?->baseUom?->code,
                        'unit' => $l->serial_id !== null || $l->piece_id !== null,
                        'lacak' => $l->lot?->lot_no ?? $l->serial?->serial_no ?? $l->piece?->piece_no,
                    ])->values(),
            ])->values(),
        ]);
    }

    /** A-439: tugas gudang offline — put-away, picking, penerimaan, konfirmasi muat, bukti terima. */
    public function tasks(Request $request): View
    {
        abort_if($request->user()->isClient(), 403);

        return view('offline.tasks');
    }

    public function tasksData(Request $request, OfflineTaskPack $paket): JsonResponse
    {
        abort_if($request->user()->isClient(), 403);

        return response()->json($paket->untuk($request->user()));
    }

    public function sync(Request $request, OfflineSync $sync): JsonResponse
    {
        $data = $request->validate(['items' => ['required', 'array', 'max:'.OfflineSync::MAKS_PER_KIRIM], 'items.*' => ['array']]);

        // A-44: langganan ditangguhkan → antrean ditahan (EnsureSubscriptionState menolak POST lebih dulu).
        return response()->json(['hasil' => $sync->handle($request->user(), $data['items'])]);
    }
}
