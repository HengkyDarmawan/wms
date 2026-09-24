<?php

declare(strict_types=1);

namespace App\Http\Controllers\Template;

use App\Domain\Shared\Files\StoreUpload;
use App\Domain\Template\Actions\SaveDocumentLayout;
use App\Domain\Template\Enums\DocumentTemplateType;
use App\Domain\Template\Models\DocumentLayout;
use App\Domain\Template\Models\DocumentTemplate;
use App\Domain\Template\Support\PdfRenderer;
use App\Domain\Template\Support\PrintAssets;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pengaturan layout induk cetak (18 §6). Form isian lewat Livewire; logo
 * lewat form biasa (POST/DELETE) seperti foto item dan tanda tangan profil.
 */
class DocumentLayoutController extends Controller
{
    public function edit(): View
    {
        $this->authorize('document_layout.manage');

        return view('template.layout');
    }

    public function storeLogo(Request $request, SaveDocumentLayout $action): RedirectResponse
    {
        $this->authorize('document_layout.manage');

        $request->validate(['logo' => ['required', 'file']], ['logo.required' => __('Pilih berkas logo.')]);
        $action->storeLogo($request->file('logo'), $request->user());

        return redirect()->route('document-layout.edit')->with('status', __('Logo disimpan.'));
    }

    public function destroyLogo(Request $request, SaveDocumentLayout $action): RedirectResponse
    {
        $this->authorize('document_layout.manage');

        $action->removeLogo($request->user());

        return redirect()->route('document-layout.edit')->with('status', __('Logo dihapus.'));
    }

    /** Pratinjau logo di layar pengaturan; berkas tenant tidak punya URL publik (A-68). */
    public function logo(StoreUpload $files): Response
    {
        $this->authorize('document_layout.manage');

        $path = DocumentLayout::current()->logo_path;
        abort_unless($files->exists($path), 404);

        return $files->stream((string) $path);
    }

    /** Contoh cetak: kop, tabel contoh, dan blok tanda tangan SJ. */
    public function preview(PrintAssets $assets): Response
    {
        $this->authorize('document_layout.manage');

        $layout = DocumentLayout::current();
        $type = DocumentTemplateType::Shipment;

        $html = view('print.preview', [
            'type' => $type,
            'kop' => $assets->kop($layout),
            'nomor' => 'SJ/CONTOH/0001',
            'status' => __('Contoh'),
            'batal' => false,
            'qr' => $assets->qr(route('document-layout.edit')),
            'tandaTangan' => collect($layout->signatureBlocksFor($type))
                ->map(fn (string $label) => ['label' => $label, 'name' => null, 'signature' => null])->all(),
        ])->render();

        return PdfRenderer::make($html, DocumentTemplate::forType($type)->paper)->stream('contoh-layout.pdf');
    }
}
