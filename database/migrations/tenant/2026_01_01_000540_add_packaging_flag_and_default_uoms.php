<?php

declare(strict_types=1);

use App\Domain\Master\Support\DefaultUoms;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Satuan sederhana (A-411): tanda **kemasan** pada satuan (isi ditentukan per
 * barang, A-294) supaya layar bisa menulis "1 M = 100 CM" untuk satuan biasa
 * dan "isi per barang" untuk DUS/BOX/… . Company lama dilengkapi satuan
 * bawaan yang **belum ada** (yang sudah ada tidak ditimpa); database kosong
 * (company baru) diisi seeder saat provisioning.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('uoms', 'is_packaging')) {
            Schema::table('uoms', function (Blueprint $table) {
                $table->boolean('is_packaging')->default(false)->after('factor_to_reference');
            });
        }

        if (DB::table('uom_categories')->exists()) {
            DefaultUoms::pasang(timpa: false);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('uoms', 'is_packaging')) {
            Schema::table('uoms', function (Blueprint $table) {
                $table->dropColumn('is_packaging');
            });
        }
    }
};
