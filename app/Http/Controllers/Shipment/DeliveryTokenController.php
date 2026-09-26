<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shipment;

use App\Domain\Shared\Files\StoreUpload;
use App\Domain\Shipment\Actions\ConfirmDelivery;
use App\Domain\Shipment\Actions\IssueDeliveryToken;
use App\Domain\Shipment\Enums\PodUnitCondition;
use App\Domain\Shipment\Enums\ProofChannel;
use App\Domain\Shipment\Exceptions\ShipmentRuleException;
use App\Domain\Shipment\Models\DeliveryToken;
use App\Domain\Shipment\Support\ProofFiles;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Halaman penerima bertoken (A-41, A-231, BR-SJ-05): penerima tanpa akun
 * membuka tautan, memasukkan OTP yang disampaikan driver, lalu mengisi bukti
 * terima per baris (baik/rusak/kurang, foto wajib bila rusak, tanda tangan).
 *
 * Tanpa `auth`: identitasnya adalah token + OTP. Sesi OTP berlaku 30 menit dan
 * hanya untuk token itu; percobaan OTP dibatasi di `IssueDeliveryToken::verify`
 * (NFR-04) dan lalu lintasnya dibatasi `throttle` di rute.
 */
class DeliveryTokenController extends Controller
{
    private const SESI_MENIT = 30;

    public function show(Request $request, string $token): View|Response
    {
        $baris = $this->token($token);

        if ($baris === null) {
            return response()->view('shipment.terima.mati', [], 410);
        }

        $sj = $baris->shipment()->with('warehouse:id,code,name', 'destinationProject:id,code,name', 'lines.item:id,code,name,base_uom_id', 'lines.item.baseUom:id,code', 'lines.serial:id,serial_no', 'lines.piece:id,piece_no')->first();

        if ($baris->used_at !== null && $sj?->proof()->exists()) {
            return view('shipment.terima.selesai', ['sj' => $sj, 'bukti' => $sj->proof()->with('lines')->first()]);
        }

        if (! $baris->isUsable()) {
            return response()->view('shipment.terima.mati', [], 410);
        }

        if (! $this->terverifikasi($request, $token)) {
            return view('shipment.terima.otp', ['sj' => $sj, 'token' => $token, 'terkunci' => $baris->isLockedOut()]);
        }

        return view('shipment.terima.form', ['sj' => $sj, 'token' => $token]);
    }

    public function otp(Request $request, string $token, IssueDeliveryToken $action): RedirectResponse
    {
        $request->validate(['otp' => ['required', 'digits:6']], attributes: ['otp' => __('Kode OTP')]);

        try {
            $action->verify($token, (string) $request->input('otp'));
        } catch (ShipmentRuleException $e) {
            return back()->withErrors(['otp' => $e->getMessage()]);
        }

        $request->session()->put($this->kunciSesi($token), now()->timestamp);

        return redirect()->route('terima.show', $token);
    }

    public function store(Request $request, string $token, ConfirmDelivery $action, IssueDeliveryToken $tokens, ProofFiles $berkas): RedirectResponse
    {
        $baris = $this->token($token);

        abort_if($baris === null || ! $baris->isUsable(), 410);
        abort_unless($this->terverifikasi($request, $token), 403);

        $request->validate([
            'received_by_name' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array'],
            'lines.*.qty_good' => ['nullable', 'numeric', 'min:0'],
            'lines.*.qty_damaged' => ['nullable', 'numeric', 'min:0'],
            'lines.*.qty_missing' => ['nullable', 'numeric', 'min:0'],
            'lines.*.condition' => ['nullable', 'in:good,damaged,missing'],
            'photo' => ['nullable', ...StoreUpload::ATURAN_FOTO],
            'photos.*' => ['nullable', ...StoreUpload::ATURAN_FOTO],
            'signature' => ['nullable', 'string', 'max:2000000'],
        ], attributes: ['received_by_name' => __('Nama penerima'), 'photo' => __('Foto serah terima'), 'photos.*' => __('Foto kerusakan')]);

        $sj = $baris->shipment;
        $disimpan = $berkas->simpan($sj, $request->file('photo'), $request->input('signature'), (array) ($request->file('photos') ?? []));

        $isian = [];
        $dikirim = $sj->lines()->pluck('qty_shipped', 'id');

        foreach ((array) $request->input('lines', []) as $lineId => $nilai) {
            // A-244: unit serial/potongan memilih satu kondisi untuk seluruh jumlahnya.
            $kondisi = PodUnitCondition::tryFrom((string) ($nilai['condition'] ?? ''));

            if ($kondisi !== null) {
                foreach (PodUnitCondition::cases() as $k) {
                    $nilai['qty_'.$k->value] = $k === $kondisi ? (float) ($dikirim[(int) $lineId] ?? 0) : 0;
                }
            }

            $isian[] = [
                'shipment_line_id' => (int) $lineId,
                'qty_good' => (float) ($nilai['qty_good'] ?? 0),
                'qty_damaged' => (float) ($nilai['qty_damaged'] ?? 0),
                'qty_missing' => (float) ($nilai['qty_missing'] ?? 0),
                'damage_photo_path' => $disimpan['lines'][(int) $lineId] ?? null,
                'notes' => isset($nilai['notes']) ? (string) $nilai['notes'] : null,
            ];
        }

        try {
            $action->handle($sj, [
                'received_by_name' => $request->input('received_by_name'),
                'notes' => $request->input('notes'),
                'channel' => ProofChannel::TokenLink->value,
                'photo_path' => $disimpan['photo_path'],
                'signature_path' => $disimpan['signature_path'],
            ], $isian, null);
        } catch (ShipmentRuleException $e) {
            $berkas->hapus($disimpan);

            return back()->withInput()->withErrors($e->fieldErrors ?: ['terima' => $e->getMessage()]);
        }

        $tokens->consume($baris);
        $request->session()->forget($this->kunciSesi($token));

        return redirect()->route('terima.show', $token);
    }

    private function token(string $token): ?DeliveryToken
    {
        return strlen($token) === 64 ? DeliveryToken::query()->where('token', $token)->first() : null;
    }

    private function terverifikasi(Request $request, string $token): bool
    {
        $sejak = (int) $request->session()->get($this->kunciSesi($token), 0);

        return $sejak > 0 && now()->timestamp - $sejak <= self::SESI_MENIT * 60;
    }

    private function kunciSesi(string $token): string
    {
        return 'terima.'.substr($token, 0, 16);
    }
}
