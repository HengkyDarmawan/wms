<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BR-GRN-05, A-245: kelebihan terima GRN transfer/retur tidak diposting GRN,
 * dicatat di baris (`qty_excess`) dan memicu ADJ `over_receipt` yang merujuk
 * GRN-nya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receipt_lines', function (Blueprint $table) {
            $table->decimal('qty_excess', 18, 4)->default(0)->after('qty_received');
        });

        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->foreignId('goods_receipt_id')->nullable()->after('stock_count_id')->constrained('goods_receipts')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('goods_receipt_id');
        });

        Schema::table('goods_receipt_lines', function (Blueprint $table) {
            $table->dropColumn('qty_excess');
        });
    }
};
