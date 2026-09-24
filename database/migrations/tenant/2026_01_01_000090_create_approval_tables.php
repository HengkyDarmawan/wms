<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Approval — mesin approval bersama (20-approval §3, ERD 08c area
 * "Approval engine", D-17, D-28).
 *
 * Aturan hidup (`approval_rules`, `approval_steps`) di-snapshot ke
 * `approval_snapshots` saat dokumen diajukan (BR-APR-01); tugas per lapis dan
 * keputusan dicatat terpisah supaya delegasi, eskalasi, dan kanal WA bisa
 * diaudit. Tabel token WA sudah ada sebagai stub Fase 2a (BR-GEN-10).
 *
 * Kolom waktu snapshot memakai presisi mikrodetik: kunci unik ERD
 * (document_type, document_id, submitted_at) harus tetap unik walau dokumen
 * yang sama diajukan ulang dalam detik yang sama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_rules', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 30);                  // approval_document_type
            $table->string('name', 100);
            $table->integer('priority')->default(100);            // kecil = diperiksa lebih dulu
            $table->json('conditions')->nullable();               // tanpa nilai uang (BR-APR-07)
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['document_type', 'is_active', 'priority']);
        });

        Schema::create('approval_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_rule_id')->constrained('approval_rules')->restrictOnDelete();
            $table->unsignedSmallInteger('step_no');
            $table->string('approver_type', 20);                  // approver_type
            $table->unsignedBigInteger('approver_ref_id')->nullable();
            $table->string('decision_mode', 12)->default('any');  // decision_mode
            $table->string('backup_approver_type', 20)->nullable();
            $table->unsignedBigInteger('backup_ref_id')->nullable();
            $table->unsignedSmallInteger('timeout_hours')->default(24); // A-18
            $table->string('channel', 10)->default('web');        // web|whatsapp|both — F2 stub
            $table->boolean('require_pin')->default(false);       // F2 stub
            $table->timestamps();

            $table->unique(['approval_rule_id', 'step_no']);
        });

        Schema::create('approval_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 30);
            $table->unsignedBigInteger('document_id');
            $table->string('document_number', 40)->nullable();
            $table->foreignId('rule_id')->nullable()->constrained('approval_rules')->nullOnDelete();
            $table->string('rule_name', 100)->nullable();         // nama aturan saat diajukan
            $table->json('context')->nullable();                  // data dokumen yang dicocokkan
            $table->json('steps');                                 // lapis setelah SoD & resolusi
            $table->string('status', 12)->default('pending');     // approval_snapshot_status
            $table->unsignedSmallInteger('current_step')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('submitted_at', 6);
            $table->dateTime('decided_at', 6)->nullable();
            $table->timestamps(6);

            $table->unique(['document_type', 'document_id', 'submitted_at'], 'approval_snapshots_doc_submitted_unique');
            $table->index(['document_type', 'document_id', 'status']);
        });

        Schema::create('approval_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_snapshot_id')->constrained('approval_snapshots')->restrictOnDelete();
            $table->unsignedSmallInteger('step_no');
            $table->foreignId('approver_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('delegated_from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('escalated_from_task_id')->nullable()->constrained('approval_tasks')->nullOnDelete();
            $table->dateTime('due_at')->nullable();
            $table->string('status', 12)->default('open');        // approval_task_status
            $table->timestamps();

            $table->index(['approver_user_id', 'status']);
            $table->index(['approval_snapshot_id', 'step_no']);
            $table->index(['status', 'due_at']);
        });

        Schema::create('approval_tokens', function (Blueprint $table) {
            // Stub Fase 2a (BR-APR-10): token sekali pakai di pesan WhatsApp.
            $table->id();
            $table->foreignId('approval_task_id')->constrained('approval_tasks')->restrictOnDelete();
            $table->string('token', 64)->unique();
            $table->dateTime('expires_at');
            $table->dateTime('used_at')->nullable();
            $table->string('wa_message_id', 120)->nullable();
            $table->timestamps();
        });

        Schema::create('approval_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_task_id')->constrained('approval_tasks')->restrictOnDelete();
            $table->string('decision', 12);                       // approval_decision
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('decided_at');
            $table->string('channel', 10)->default('web');        // approval_channel
            $table->foreignId('reason_code_id')->nullable()->constrained('reason_codes')->nullOnDelete();
            $table->string('comment', 255)->nullable();
            $table->string('wa_from_number', 20)->nullable();     // F2 (BR-APR-10)
            $table->string('wa_message_id', 120)->nullable();
            $table->foreignId('approval_token_id')->nullable()->constrained('approval_tokens')->nullOnDelete();
            $table->timestamps();

            $table->index(['approval_task_id', 'decision']);
        });

        Schema::create('approval_delegations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('to_user_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->json('document_types')->nullable();            // null = semua jenis
            $table->boolean('is_active')->default(true);
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['from_user_id', 'is_active']);
            $table->index(['to_user_id', 'is_active']);
        });

        // Kolom stub modul Request kini menunjuk tabelnya (14-request §3.1).
        Schema::table('material_requests', function (Blueprint $table) {
            $table->foreign('approval_snapshot_id')->references('id')->on('approval_snapshots')->nullOnDelete();
        });

        // RTV kini diputus lewat mesin approval (A-80 → 20-approval §13).
        Schema::table('vendor_returns', function (Blueprint $table) {
            $table->foreignId('approval_snapshot_id')->nullable()->after('goods_receipt_id')
                ->constrained('approval_snapshots')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vendor_returns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approval_snapshot_id');
        });

        Schema::table('material_requests', function (Blueprint $table) {
            $table->dropForeign(['approval_snapshot_id']);
        });

        Schema::dropIfExists('approval_delegations');
        Schema::dropIfExists('approval_decisions');
        Schema::dropIfExists('approval_tokens');
        Schema::dropIfExists('approval_tasks');
        Schema::dropIfExists('approval_snapshots');
        Schema::dropIfExists('approval_steps');
        Schema::dropIfExists('approval_rules');
    }
};
