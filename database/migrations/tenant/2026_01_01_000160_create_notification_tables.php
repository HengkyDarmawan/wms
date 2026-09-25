<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notifikasi in-app & email (Blueprint §10, ERD 08c `notifications`,
 * `notification_preferences`; 27-pendukung-f1 §2). Kolom di luar ERD:
 * `event_key`, `title`, `body`, `url` (A-189).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 60);                    // kunci kejadian, mis. approval.task_assigned
            $table->string('channel', 10)->default('in_app'); // in_app|email|whatsapp
            $table->string('title', 150);
            $table->string('body', 500)->nullable();
            $table->string('url', 255)->nullable();
            $table->json('data')->nullable();
            $table->string('document_type', 30)->nullable();
            $table->unsignedBigInteger('document_id')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'channel', 'read_at']);
            $table->index(['type', 'document_type', 'document_id']);
        });

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('event_key', 60);
            $table->boolean('in_app')->default(true);
            $table->boolean('email')->default(false);
            $table->boolean('whatsapp')->default(false);   // F2 (BR-GEN-10)
            $table->timestamps();

            $table->primary(['user_id', 'event_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notifications');
    }
};
