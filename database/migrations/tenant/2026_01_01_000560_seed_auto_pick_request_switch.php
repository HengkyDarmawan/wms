<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A-420: saklar `auto_pick_request` (picking otomatis setelah REQ disetujui)
 * untuk company yang sudah ada — menyala, tanpa menimpa pilihan yang sudah ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('feature_settings')) {
            return;
        }

        if (! DB::table('feature_settings')->where('key', 'auto_pick_request')->exists()) {
            DB::table('feature_settings')->insert([
                'key' => 'auto_pick_request',
                'enabled' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Data saklar dibiarkan (P-03); saklar tak dikenal diabaikan katalog.
    }
};
