<?php

declare(strict_types=1);

namespace App\Domain\Template\Livewire;

use App\Domain\Template\Actions\SaveDocumentLayout;
use App\Domain\Template\Enums\DocumentTemplateType;
use App\Domain\Template\Enums\PaperSize;
use App\Domain\Template\Models\DocumentLayout;
use App\Domain\Template\Models\DocumentTemplate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Layar 18 §6 — layout induk cetak: kop, footer, warna, blok tanda tangan,
 * dan kertas per template. Editor template penuh `[F2]` (BR-GEN-10).
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

        foreach (DocumentTemplateType::cases() as $type) {
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

    public function render(): View
    {
        return view('livewire.template.document-layout-form', [
            'layout' => DocumentLayout::current(),
            'dokumen' => DocumentTemplateType::documents(),
            'label' => DocumentTemplateType::labels(),
            'kertasDokumen' => PaperSize::forDocuments(),
            'kertasLabel' => PaperSize::forLabels(),
        ]);
    }
}
