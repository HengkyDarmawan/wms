<?php

declare(strict_types=1);

namespace App\Domain\Template\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Template\Enums\DocumentTemplateType;
use App\Domain\Template\Models\DocumentTemplate;
use App\Domain\Template\Support\TemplateText;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Permission: `document_layout.manage` — simpan teks template dokumen
 * (editor `[F2]`, A-436). Teks disaring ke HTML aman ({@see TemplateText});
 * variabel tak dikenal ditolak; kosong = kembali ke bawaan. Versi naik setiap
 * simpan supaya riwayat cetak bisa dirunut.
 */
class SaveDocumentTemplate
{
    public function handle(DocumentTemplateType $type, ?string $body, User $actor): DocumentTemplate
    {
        if (! $actor->hasPermission('document_layout.manage')) {
            throw new AuthorizationException('Anda tidak berwenang mengubah template dokumen.');
        }

        if ($type->isLabel() || $type->isStub()) {
            throw ValidationException::withMessages(['body' => 'Jenis ini tidak memakai editor teks.']);
        }

        $body = (string) $body;

        if (mb_strlen($body) > TemplateText::MAKS) {
            throw ValidationException::withMessages(['body' => 'Teks template maksimal '.TemplateText::MAKS.' karakter.']);
        }

        if (($asing = TemplateText::variabelAsing($body)) !== []) {
            throw ValidationException::withMessages(['body' => 'Variabel tidak dikenal: {'.implode('}, {', $asing).'}.']);
        }

        if (substr_count($body, '{'.TemplateText::ISI_BAWAAN.'}') > 1) {
            throw ValidationException::withMessages(['body' => '{'.TemplateText::ISI_BAWAAN.'} hanya boleh sekali.']);
        }

        $bersih = TemplateText::sanitize($body);
        $template = DocumentTemplate::forType($type);
        $template->update(['body_html' => $bersih !== '' ? $bersih : null, 'version' => (int) $template->version + 1]);

        activity('template')->performedOn($template)->causedBy($actor)
            ->withProperties(['jenis' => $type->value, 'versi' => $template->version, 'kosong' => $bersih === ''])
            ->log($bersih === '' ? 'Template '.$type->label().' kembali ke bawaan' : 'Template '.$type->label().' diubah');

        return $template->refresh();
    }
}
