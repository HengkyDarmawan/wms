<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Platform penuh (17-platform-login v0.2). Kolom di luar ERD 08a dicatat
 * di A-184; tabel `audit_logs` pusat memakai skema yang sama dengan tenant
 * (spatie/activitylog) untuk jejak tindakan Super Admin (NFR-03).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedSmallInteger('trial_days')->default(14)->after('monthly_price'); // A-11
        });

        Schema::table('platform_users', function (Blueprint $table) {
            // NFR-04: penguncian sama dengan login tenant (config/access.php).
            $table->unsignedSmallInteger('failed_login_count')->default(0)->after('remember_token');
            $table->dateTime('locked_until')->nullable()->after('failed_login_count');
        });

        Schema::table('subscription_invoices', function (Blueprint $table) {
            $table->dateTime('paid_at')->nullable()->after('status');
            $table->index(['status', 'due_date']);
        });

        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->string('uploaded_by_name', 100)->nullable()->after('uploaded_by'); // nama user tenant saat unggah
            $table->string('notes', 255)->nullable()->after('status');
            $table->string('reject_reason', 255)->nullable()->after('notes');
        });

        Schema::create('platform_login_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('email', 150);
            $table->foreignId('platform_user_id')->nullable()->constrained('platform_users')->nullOnDelete();
            $table->string('result', 20);                     // login_result (KS §3)
            $table->string('ip_address', 45)->nullable();
            $table->dateTime('attempted_at');
            $table->index(['email', 'attempted_at']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('log_name')->nullable();
            $table->text('description');
            $table->nullableMorphs('subject', 'subject');
            $table->string('event')->nullable();
            $table->nullableMorphs('causer', 'causer');
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->timestamps();
            $table->index('log_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('platform_login_attempts');

        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->dropColumn(['uploaded_by_name', 'notes', 'reject_reason']);
        });

        Schema::table('subscription_invoices', function (Blueprint $table) {
            $table->dropIndex(['status', 'due_date']);
            $table->dropColumn('paid_at');
        });

        Schema::table('platform_users', function (Blueprint $table) {
            $table->dropColumn(['failed_login_count', 'locked_until']);
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('trial_days');
        });
    }
};
