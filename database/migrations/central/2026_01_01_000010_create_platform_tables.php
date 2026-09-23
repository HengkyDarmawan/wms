<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Database pusat (platform) — ERD 08a area "Database pusat".
 * Tidak menyimpan data operasional company (BR-SUB-04, A-27).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 80);
            // Harga hanya ada di pusat (langganan platform), bukan di data WMS (D-07).
            $table->decimal('monthly_price', 14, 2)->default(0);
            $table->integer('wa_quota')->nullable();          // O-04
            $table->integer('storage_quota_mb')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();             // segmen nomor dokumen (BR-GEN-06)
            $table->string('name', 150);
            $table->string('subdomain', 63)->unique();        // A-01
            $table->string('db_name', 64)->unique();          // database tenant (D-03)
            $table->string('timezone', 40)->default('Asia/Jakarta'); // BR-GEN-07
            $table->string('status', 20)->default('provisioning'); // provisioning|active|suspended|terminated
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            // Kolom implementasi: dipakai stancl/tenancy (VirtualColumn) untuk atribut tambahan.
            $table->json('data')->nullable();
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->string('status', 20)->default('trial');   // subscription_status (KS §3)
            $table->dateTime('trial_ends_at')->nullable();    // A-11
            $table->date('current_period_start')->nullable();
            $table->date('current_period_end')->nullable();
            $table->dateTime('grace_ends_at')->nullable();    // A-12
            $table->dateTime('suspended_at')->nullable();
            $table->dateTime('terminated_at')->nullable();
            $table->date('purge_after')->nullable();          // +90 hari
            $table->timestamps();
            $table->index(['company_id', 'status']);
        });

        Schema::create('subscription_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->string('number', 40)->unique();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('amount', 14, 2);
            $table->date('due_date');
            $table->string('status', 20)->default('open');    // open|paid|overdue|void
            $table->timestamps();
        });

        Schema::create('platform_users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('email', 150)->unique();
            $table->string('password');
            $table->text('two_factor_secret')->nullable();
            $table->rememberToken();
            $table->dateTime('last_login_at')->nullable();
            $table->timestamps();
        });

        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('subscription_invoices')->cascadeOnDelete();
            $table->unsignedBigInteger('uploaded_by')->nullable(); // user tenant (id di DB company)
            $table->string('proof_path', 255)->nullable();
            $table->decimal('amount', 14, 2);
            $table->date('paid_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('platform_users')->nullOnDelete();
            $table->dateTime('verified_at')->nullable();
            $table->string('status', 20)->default('pending'); // pending|verified|rejected
            $table->timestamps();
        });

        // Stub Fase 3 (BR-GEN-10): identitas SSO NXTG, A-28 & A-48.
        Schema::create('sso_identities', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 30)->default('nxtg');
            $table->string('sub', 191);
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->unsignedBigInteger('tenant_user_id');
            $table->dateTime('last_login_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'sub', 'company_id']);
        });

        Schema::create('feature_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('key', 60);                        // whatsapp|offline_sync|rfid|…
            $table->boolean('enabled')->default(false);
            $table->json('config')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'key']);
        });

        // A-27, BR-SUB-04: Super Admin hanya bisa membuka data company dengan izin berperiode.
        Schema::create('support_accesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('platform_user_id')->constrained('platform_users')->cascadeOnDelete();
            $table->unsignedBigInteger('granted_by_tenant_user_id');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('reason', 255);
            $table->dateTime('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'starts_at', 'ends_at']);
        });

        Schema::create('tenant_migration_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('batch', 40);
            $table->string('migration', 191);
            $table->string('status', 10);                     // ok|failed
            $table->text('error')->nullable();
            $table->dateTime('ran_at');
        });

        // Stub Fase 2a (BR-GEN-10): log pesan WhatsApp satu nomor platform (A-24, NFR-12).
        Schema::create('wa_message_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('direction', 3);                   // out|in
            $table->string('category', 20);                   // utility_template|service|inbound
            $table->string('wa_message_id', 120)->unique();
            $table->string('to_number', 20)->nullable();
            $table->string('template', 80)->nullable();
            $table->json('payload')->nullable();
            $table->string('status', 20)->default('queued');
            $table->decimal('cost_units', 10, 4)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_message_logs');
        Schema::dropIfExists('tenant_migration_runs');
        Schema::dropIfExists('support_accesses');
        Schema::dropIfExists('feature_flags');
        Schema::dropIfExists('sso_identities');
        Schema::dropIfExists('subscription_payments');
        Schema::dropIfExists('platform_users');
        Schema::dropIfExists('subscription_invoices');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('companies');
        Schema::dropIfExists('plans');
    }
};
