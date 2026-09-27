<?php

declare(strict_types=1);

namespace App\Domain\Template\Livewire;

use App\Domain\Template\Actions\SaveLabelFormat;
use App\Domain\Template\Actions\SetLabelFormatActive;
use App\Domain\Template\Enums\DocumentTemplateType;
use App\Domain\Template\Enums\LabelMedia;
use App\Domain\Template\Models\LabelDesign;
use App\Domain\Template\Models\LabelFormat;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Layar 18 §6 — master ukuran label (A-261): daftar, tambah/ubah dengan
 * pratinjau susunan lembar, nonaktifkan/aktifkan. Permission
 * `document_layout.manage`.
 */
class LabelFormatManager extends Component
{
    public ?int $editingId = null;

    public bool $showForm = false;

    /** @var array<string, string> */
    public array $form = [];

    public function mount(): void
    {
        $this->authorize('document_layout.manage');
        $this->form = $this->kosong();
    }

    public function baru(): void
    {
        $this->authorize('document_layout.manage');
        $this->resetErrorBag();
        $this->editingId = null;
        $this->form = $this->kosong();
        $this->showForm = true;
    }

    public function ubah(int $id): void
    {
        $this->authorize('document_layout.manage');
        $f = LabelFormat::query()->findOrFail($id);
        $this->resetErrorBag();
        $this->editingId = $f->id;
        $this->form = [
            'code' => $f->code, 'name' => $f->name, 'media' => $f->media->value,
            'width_mm' => LabelFormat::mm($f->width_mm), 'height_mm' => LabelFormat::mm($f->height_mm),
            'page_width_mm' => LabelFormat::mm((float) ($f->page_width_mm ?? 210)), 'page_height_mm' => LabelFormat::mm((float) ($f->page_height_mm ?? 297)),
            'columns' => (string) $f->columns, 'rows' => (string) $f->rows,
            'margin_top_mm' => LabelFormat::mm($f->margin_top_mm), 'margin_left_mm' => LabelFormat::mm($f->margin_left_mm),
            'gap_x_mm' => LabelFormat::mm($f->gap_x_mm), 'gap_y_mm' => LabelFormat::mm($f->gap_y_mm),
        ];
        $this->showForm = true;
    }

    /** Ukuran halaman umum untuk lembar label. */
    public function halaman(string $kertas): void
    {
        [$this->form['page_width_mm'], $this->form['page_height_mm']] = match ($kertas) {
            'letter' => ['215,9', '279,4'],
            'f4' => ['215', '330'],
            default => ['210', '297'],
        };
    }

    /** Hitung margin supaya susunan label berada di tengah halaman. */
    public function tengahkan(): void
    {
        $f = $this->pratinjau();
        if ($f === null || $f->isRoll()) {
            return;
        }

        [$pw, $ph] = $f->pageSize();
        $this->form['margin_left_mm'] = LabelFormat::mm(max(0, round(($pw - ($f->columns * $f->width_mm + ($f->columns - 1) * $f->gap_x_mm)) / 2, 2)));
        $this->form['margin_top_mm'] = LabelFormat::mm(max(0, round(($ph - ($f->rows * $f->height_mm + ($f->rows - 1) * $f->gap_y_mm)) / 2, 2)));
    }

    public function simpan(SaveLabelFormat $action): void
    {
        $this->authorize('document_layout.manage');

        try {
            $format = $action->handle($this->form, auth()->user(), $this->editingId ? LabelFormat::query()->findOrFail($this->editingId) : null);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        $this->resetErrorBag();
        $this->showForm = false;
        $this->editingId = null;
        $this->dispatch('pesan', teks: __('Ukuran label :kode disimpan.', ['kode' => $format->code]));
    }

    public function aktifkan(int $id, bool $aktif, SetLabelFormatActive $action): void
    {
        $this->authorize('document_layout.manage');

        try {
            $action->handle(LabelFormat::query()->findOrFail($id), $aktif, auth()->user());
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        $this->resetErrorBag();
        $this->dispatch('pesan', teks: $aktif ? __('Ukuran label diaktifkan.') : __('Ukuran label dinonaktifkan.'));
    }

    public function batal(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->resetErrorBag();
    }

    public function render(): View
    {
        $bawaan = [];
        foreach (DocumentTemplateType::labels() as $t) {
            $id = LabelFormat::defaultFor($t)?->id;
            if ($id !== null) {
                $bawaan[$id][] = $t->label();
            }
        }

        return view('livewire.template.label-format-manager', [
            'formats' => LabelFormat::query()->orderByDesc('is_active')->orderBy('media')->orderBy('code')->get(),
            'bawaan' => $bawaan,
            'desain' => LabelDesign::query()->selectRaw('label_format_id, count(*) as jumlah')->groupBy('label_format_id')->pluck('jumlah', 'label_format_id'),
            'media' => LabelMedia::options(),
            'pratinjau' => $this->showForm ? $this->pratinjau() : null,
        ]);
    }

    /** Format sementara dari isian (belum disimpan) untuk gambar susunan; null bila angka belum sah. */
    private function pratinjau(): ?LabelFormat
    {
        $angka = fn (string $k) => is_numeric(str_replace(',', '.', (string) ($this->form[$k] ?? ''))) ? (float) str_replace(',', '.', (string) $this->form[$k]) : null;
        $w = $angka('width_mm');
        $h = $angka('height_mm');
        $media = LabelMedia::tryFrom((string) ($this->form['media'] ?? ''));

        if ($media === null || $w === null || $h === null || $w < 5 || $h < 5 || $w > 500 || $h > 500) {
            return null;
        }

        $f = new LabelFormat(['media' => $media, 'width_mm' => $w, 'height_mm' => $h]);

        if ($media === LabelMedia::Sheet) {
            $pw = $angka('page_width_mm');
            $ph = $angka('page_height_mm');
            $kol = (int) ($this->form['columns'] ?? 0);
            $bar = (int) ($this->form['rows'] ?? 0);
            if ($pw === null || $ph === null || $pw < 20 || $ph < 20 || $pw > 600 || $ph > 600 || $kol < 1 || $kol > 10 || $bar < 1 || $bar > 40) {
                return null;
            }
            $f->fill([
                'page_width_mm' => $pw, 'page_height_mm' => $ph, 'columns' => $kol, 'rows' => $bar,
                'margin_top_mm' => $angka('margin_top_mm') ?? 0, 'margin_left_mm' => $angka('margin_left_mm') ?? 0,
                'gap_x_mm' => $angka('gap_x_mm') ?? 0, 'gap_y_mm' => $angka('gap_y_mm') ?? 0,
            ]);
        }

        return $f;
    }

    /** @return array<string, string> */
    private function kosong(): array
    {
        return [
            'code' => '', 'name' => '', 'media' => LabelMedia::Roll->value,
            'width_mm' => '50', 'height_mm' => '30',
            'page_width_mm' => '210', 'page_height_mm' => '297', 'columns' => '3', 'rows' => '8',
            'margin_top_mm' => '0', 'margin_left_mm' => '0', 'gap_x_mm' => '0', 'gap_y_mm' => '0',
        ];
    }
}
