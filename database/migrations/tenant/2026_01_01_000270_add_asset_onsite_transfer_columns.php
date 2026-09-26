<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A-249: transfer aset On-site antar proyek (mengubah A-116, BR-RET-02).
 *
 * - `transfers.asset_onsite` menandai TRF aset yang berpindah bin On-site
 *   proyek asal → bin On-site proyek tujuan lewat SJ antar site (tanpa PCK);
 * - `transfer_lines.serial_id` = aset (satu serial per baris);
 * - `asset_handovers.transfer_id` + rantai `previous_handover_id` /
 *   `next_handover_id`: AST proyek asal ditutup `transferred`, AST proyek
 *   tujuan lahir dan merujuk AST sebelumnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->boolean('asset_onsite')->default(false)->after('origin');
        });

        Schema::table('transfer_lines', function (Blueprint $table) {
            $table->foreignId('serial_id')->nullable()->after('item_id')->constrained('serials')->restrictOnDelete();
        });

        Schema::table('asset_handovers', function (Blueprint $table) {
            $table->foreignId('transfer_id')->nullable()->after('goods_return_line_id')->constrained('transfers')->nullOnDelete();
            $table->foreignId('previous_handover_id')->nullable()->after('transfer_id')->constrained('asset_handovers')->nullOnDelete();
            $table->foreignId('next_handover_id')->nullable()->after('previous_handover_id')->constrained('asset_handovers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('asset_handovers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('next_handover_id');
            $table->dropConstrainedForeignId('previous_handover_id');
            $table->dropConstrainedForeignId('transfer_id');
        });

        Schema::table('transfer_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('serial_id');
        });

        Schema::table('transfers', function (Blueprint $table) {
            $table->dropColumn('asset_onsite');
        });
    }
};
