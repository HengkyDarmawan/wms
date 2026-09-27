<?php

declare(strict_types=1);

namespace App\Http\Controllers\Template;

use App\Domain\Template\Models\SignatureSeal;
use App\Domain\Template\Support\DocumentPrinter;
use App\Domain\Template\Support\SignatureSeals;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Response;
use Throwable;

/**
 * Halaman verifikasi segel tanda tangan (A-264): publik, tanpa login, dibuka
 * dari QR di kotak tanda tangan dokumen cetak. Menjawab "dokumen apa, milik
 * company mana, ditandatangani siapa, kapan" — tanpa baris barang, jumlah,
 * atau harga. Lalu lintas dibatasi `throttle` di rute; token 40 karakter acak.
 */
class SignatureVerifyController extends Controller
{
    public function show(string $token, DocumentPrinter $printer, SignatureSeals $seals): View|Response
    {
        $segel = strlen($token) === 40 ? SignatureSeal::query()->where('token', $token)->first() : null;

        if ($segel === null) {
            return response()->view('template.verify', ['segel' => null], 404);
        }

        $dokumen = $this->dokumen($printer, $segel);

        return view('template.verify', [
            'segel' => $segel,
            'utuh' => $seals->intact($segel),
            'company' => (string) (tenant()?->name ?? config('app.name')),
            'dokumen' => $dokumen,
            'ringkasan' => $dokumen ? $this->ringkasan($dokumen) : [],
            'status' => $dokumen?->status?->label(),
            'batal' => ($dokumen?->status?->value ?? null) === 'cancelled',
            'lain' => SignatureSeal::query()->where('document_type', $segel->document_type->value)
                ->where('document_id', $segel->document_id)->whereKeyNot($segel->id)
                ->orderBy('block_no')->orderBy('id')->get(),
            'tautan' => $dokumen ? $printer->url($segel->document_type, $dokumen) : null,
        ]);
    }

    private function dokumen(DocumentPrinter $printer, SignatureSeal $segel): ?Model
    {
        try {
            // Tanpa pengguna login: cakupan global tidak berlaku, dokumen dicari langsung.
            return $printer->find($segel->document_type, (int) $segel->document_id);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Identitas singkat dokumen: tanggal, gudang, proyek, mitra, jumlah baris.
     *
     * @return array<string, string>
     */
    private function ringkasan(Model $m): array
    {
        $nama = fn (?Model $r, string $kolom = 'name') => $r ? trim(($r->code ?? '').' '.($r->{$kolom} ?? '')) : null;
        $rel = fn (string $r) => method_exists($m, $r) ? $m->{$r} : null;

        $baris = null;
        foreach (['lines', 'inputs'] as $r) {
            if (method_exists($m, $r)) {
                $baris = $m->{$r}()->count();
                break;
            }
        }

        return array_filter([
            __('Tanggal dokumen') => $m->created_at?->lokal()->format('d/m/Y'),
            __('Gudang') => $nama($rel('warehouse')) ?: $nama($rel('fromWarehouse')),
            __('Tujuan') => $nama($rel('destinationProject')) ?: ($nama($rel('destinationWarehouse')) ?: $nama($rel('toWarehouse'))),
            __('Proyek') => $nama($rel('project')),
            __('Vendor') => $nama($rel('vendor')),
            __('Jumlah baris') => $baris !== null ? (string) $baris : null,
        ], fn ($v) => $v !== null && $v !== '');
    }
}
