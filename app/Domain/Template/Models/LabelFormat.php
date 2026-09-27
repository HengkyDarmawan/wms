<?php

declare(strict_types=1);

namespace App\Domain\Template\Models;

use App\Domain\Template\Enums\DocumentTemplateType;
use App\Domain\Template\Enums\LabelMedia;
use App\Domain\Template\Enums\PaperSize;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Ukuran label per company (A-261, 18 §3.3). Gulungan = satu label per
 * halaman seukuran label; lembar = kolom × baris di atas halaman dengan
 * margin atas/kiri dan jarak antar label. Semua ukuran dalam milimeter.
 *
 * @property LabelMedia $media
 */
class LabelFormat extends Model
{
    public const MIN_LABEL = 10.0;

    public const MAKS_LABEL = 300.0;

    protected $table = 'label_formats';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'media' => LabelMedia::class,
            'width_mm' => 'float',
            'height_mm' => 'float',
            'page_width_mm' => 'float',
            'page_height_mm' => 'float',
            'columns' => 'integer',
            'rows' => 'integer',
            'margin_top_mm' => 'float',
            'margin_left_mm' => 'float',
            'gap_x_mm' => 'float',
            'gap_y_mm' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isRoll(): bool
    {
        return $this->media === LabelMedia::Roll;
    }

    public function perPage(): int
    {
        return $this->isRoll() ? 1 : max(1, $this->columns * $this->rows);
    }

    /** @return array{0: float, 1: float} lebar × tinggi halaman (mm) */
    public function pageSize(): array
    {
        return $this->isRoll()
            ? [$this->width_mm, $this->height_mm]
            : [(float) $this->page_width_mm, (float) $this->page_height_mm];
    }

    /**
     * Posisi kiri-atas setiap label di satu halaman, urut baris lalu kolom.
     *
     * @return array<int, array{0: float, 1: float}>
     */
    public function positions(): array
    {
        if ($this->isRoll()) {
            return [[0.0, 0.0]];
        }

        $posisi = [];
        for ($r = 0; $r < $this->rows; $r++) {
            for ($c = 0; $c < $this->columns; $c++) {
                $posisi[] = [
                    round($this->margin_left_mm + $c * ($this->width_mm + $this->gap_x_mm), 2),
                    round($this->margin_top_mm + $r * ($this->height_mm + $this->gap_y_mm), 2),
                ];
            }
        }

        return $posisi;
    }

    public function summary(): string
    {
        $ukuran = self::mm($this->width_mm).'×'.self::mm($this->height_mm).' mm';

        return $this->isRoll()
            ? $ukuran.' · '.__('gulungan')
            : $ukuran.' · '.$this->columns.'×'.$this->rows.' '.__('per lembar').' '.self::mm((float) $this->page_width_mm).'×'.self::mm((float) $this->page_height_mm);
    }

    public static function mm(float $nilai): string
    {
        return rtrim(rtrim(number_format($nilai, 2, ',', ''), '0'), ',');
    }

    /** Format bawaan jenis label: pilihan template, lalu preset lama, lalu format aktif pertama. */
    public static function defaultFor(DocumentTemplateType $type): ?self
    {
        $template = DocumentTemplate::forType($type);

        if ($template->label_format_id !== null) {
            $format = static::query()->active()->find($template->label_format_id);
            if ($format !== null) {
                return $format;
            }
        }

        return static::fromLegacyPaper($type->defaultPaper()) ?? static::query()->active()->orderBy('id')->first();
    }

    /** Parameter `paper` lama (A-120) tetap diterima: dipetakan ke preset. */
    public static function fromLegacyPaper(?PaperSize $paper): ?self
    {
        $kode = match ($paper) {
            PaperSize::Label50x30 => 'THERMAL-50X30',
            PaperSize::LabelA4Grid => 'A4-3X8',
            default => null,
        };

        return $kode === null ? null : static::query()->active()->where('code', $kode)->first();
    }
}
