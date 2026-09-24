<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Domain\Template\Models\DocumentLayout;
use App\Domain\Template\Models\DocumentTemplate;
use Illuminate\Database\Seeder;

/**
 * Layout induk dan template bawaan setiap company baru (Blueprint §3
 * "template dokumen standar", 18-template-dokumen-label §3). Aman diulang.
 */
class TemplateReferenceSeeder extends Seeder
{
    public function run(): void
    {
        DocumentLayout::current();
        DocumentTemplate::ensureDefaults();
    }
}
