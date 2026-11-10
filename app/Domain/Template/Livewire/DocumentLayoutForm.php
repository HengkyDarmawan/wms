<?php

declare(strict_types=1);

namespace App\Domain\Template\Livewire;

use App\Domain\Master\Support\StockFeatures;
use App\Domain\Template\Actions\SaveDocumentLayout;
use App\Domain\Template\Actions\SaveDocumentTemplate;
use App\Domain\Template\Enums\DocumentTemplateType;
use App\Domain\Template\Enums\PaperSize;
use App\Domain\Template\Models\DocumentLayout;
use App\Domain\Template\Models\DocumentTemplate;
use App\Domain\Template\Support\TemplateText;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Layar 18 §6 — layout induk cetak: kop, footer, warna, blok tanda tangan,
 * dan kertas per template. Editor teks template per jenis dokumen (A-436).
 */
class DocumentLayoutForm extends Component
{
    public string $name = '';

    public string $header = '';

    public string $footer = '';

    public string $accent = '';

    /** @var array<string, string> jenis dokumen => label blok, satu per baris */
    public array $blocks = [];

    /** @var array<string, string> jenis => paper_size */
    public array $papers = [];

    /** A-436: jenis dokumen yang teksnya sedang diedit ('' = editor tertutup). */
    public string $editType = '';

    public string $editBody = '';

    public function mount(): void
    {
        $this->authorize('document_layout.manage');

        DocumentTemplate::ensureDefaults();
        $layout = DocumentLayout::current();

        $this->name = (string) $layout->name;
        $this->header = (string) $layout->header_html;
        $this->footer = (string) $layout->footer_html;
        $this->accent = $layout->accent();

        foreach (DocumentTemplateType::documents() as $type) {
            $this->blocks[$type->value] = implode("\n", $layout->signatureBlocksFor($type));
        }

        foreach (DocumentTemplateType::documents() as $type) {
            $this->papers[$type->value] = DocumentTemplate::forType($type)->paper->value;
        }
    }

    public function save(SaveDocumentLayout $action): void
    {
        $this->authorize('document_layout.manage');

        try {
            $action->handle(
                ['name' => $this->name, 'header' => $this->header, 'footer' => $this->footer, 'accent' => $this->accent],
                $this->blocks,
                $this->papers,
                auth()->user(),
            );
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        $this->resetErrorBag();
        $this->dispatch('pesan', teks: __('Layout dokumen disimpan.'));
    }

    /** A-436: buka editor teks template satu jenis dokumen. */
    public function aturTeks(string $type): void
    {
        $this->authorize('document_layout.manage');
        $jenis = DocumentTemplateType::from($type);
        $this->editType = $jenis->value;
        $this->editBody = (string) DocumentTemplate::forType($jenis)->body_html;
        $this->resetErrorBag();
    }

    public function tutupTeks(): void
    {
        $this->editType = '';
        $this->editBody = '';
        $this->resetErrorBag();
    }

    public function simpanTeks(SaveDocumentTemplate $action, bool $bawaan = false): void
    {
        $this->authorize('document_layout.manage');

        try {
            $t = $action->handle(DocumentTemplateType::from($this->editType), $bawaan ? '' : $this->editBody, auth()->user());
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        $this->editBody = (string) $t->body_html;
        $this->resetErrorBag();
        $this->dispatch('pesan', teks: $bawaan ? __('Template kembali ke bawaan.') : __('Teks template disimpan (versi :v).', ['v' => $t->version]));
    }

    public function render(): View
    {
        return view('livewire.template.document-layout-form', [
            'variabel' => TemplateText::VARIABEL,
            'berteks' => DocumentTemplate::query()->whereNotNull('body_html')->where('is_default', true)->pluck('document_type')->map(fn ($t) => $t instanceof DocumentTemplateType ? $t->value : (string) $t)->all(),
            'layout' => DocumentLayout::current(),
            'dokumen' => DocumentTemplateType::documents(),
            'label' => DocumentTemplateType::labels(StockFeatures::piece()),
            'kertasDokumen' => PaperSize::forDocuments(),
            'bisaDesainLabel' => true,
        ]);
    }
}
