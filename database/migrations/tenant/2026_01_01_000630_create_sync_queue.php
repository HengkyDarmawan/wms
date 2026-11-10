<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A-438 (32-pwa-offline, AD-16): antrean transaksi dari perangkat offline.
 * Satu baris per transaksi; `uuid` dari perangkat menjamin kirim ulang tidak
 * memposting dua kali. Status `sync_status`: received · posted · review · rejected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_queue', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('kind', 30);                          // mis. count_record
            $table->json('payload');
            $table->dateTime('recorded_at')->nullable();          // waktu di perangkat (UTC)
            $table->string('status', 12)->default('received');   // sync_status
            $table->string('message', 255)->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_queue');
    }
};
