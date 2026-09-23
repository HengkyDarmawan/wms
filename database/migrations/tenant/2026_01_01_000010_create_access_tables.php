<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Access — ERD 08a area "User, role, cakupan, struktur organisasi" + kolom
 * implementasi yang dicatat di docs/wms/10-access.md §3.
 *
 * Catatan schema:
 * - `permissions.name` menyimpan kunci permission (`<modul>.<aksi>`) karena
 *   spatie/laravel-permission mewajibkan kolom `name`; kolom `key` di ERD = kolom ini.
 * - `roles.code` adalah kode stabil (warehouse_head, …); `roles.name` label Bahasa Indonesia.
 * - `users.client_id` belum diberi foreign key: tabel `clients` dibuat modul Master.
 * - `role_assignments.scope_id` sengaja tanpa foreign key (menunjuk warehouses atau projects).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('org_units')->nullOnDelete();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('org_unit_id')->constrained('org_units')->cascadeOnDelete();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->unsignedSmallInteger('level')->default(1); // 1 = tertinggi (aturan approval)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('email', 150)->unique();           // unik per company (BR-SUB-06)
            $table->string('phone', 20)->nullable();          // WA approval/OTP (F2)
            $table->string('password')->nullable();           // null bila hanya SSO (F3)
            $table->unsignedBigInteger('client_id')->nullable(); // terisi = user klien (BR-ACC-03)
            $table->foreignId('org_unit_id')->nullable()->constrained('org_units')->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('signature_path', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->dateTime('locked_until')->nullable();     // NFR-04
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->dateTime('two_factor_confirmed_at')->nullable();
            $table->string('sso_sub', 191)->nullable()->unique(); // stub F3
            $table->timestamp('email_verified_at')->nullable();
            $table->dateTime('last_login_at')->nullable();
            $table->dateTime('password_changed_at')->nullable();
            $table->unsignedTinyInteger('failed_login_count')->default(0);
            $table->rememberToken();
            $table->timestamps();
            $table->index('client_id');
            $table->index('is_active');
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);                       // = `key` di ERD: <modul>.<aksi>
            $table->string('guard_name', 30)->default('web'); // wajib spatie/laravel-permission
            $table->string('module', 30);
            $table->string('label', 100)->nullable();
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
            $table->index('module');
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();             // kode stabil
            $table->string('name', 80);                       // label Bahasa Indonesia
            $table->string('guard_name', 30)->default('web'); // wajib spatie/laravel-permission
            $table->boolean('is_builtin')->default(false);    // role bawaan: tidak bisa dihapus
            $table->boolean('is_client_role')->default(false); // BR-ACC-03
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id'], 'role_permissions_permission_id_role_id_primary');
        });

        // BR-GEN-09, A-46: cakupan melekat pada penugasan role, bukan pada user.
        Schema::create('role_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->string('scope_type', 10);                 // all|warehouse|project
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();          // auditor eksternal (F2)
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'role_id', 'scope_type', 'scope_id'], 'role_assignments_unique');
            $table->index(['scope_type', 'scope_id']);
        });

        Schema::create('user_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token', 64)->unique();            // hash token acak
            $table->dateTime('expires_at');
            $table->dateTime('accepted_at')->nullable();
            $table->unsignedTinyInteger('sent_count')->default(1);
            $table->timestamps();
        });

        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('device_uid', 64)->unique();
            $table->string('name', 80)->nullable();
            $table->string('platform', 30)->nullable();
            $table->dateTime('last_seen_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // BR-ACC-06: password baru tidak boleh sama dengan 3 terakhir.
        Schema::create('password_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('password');
            $table->timestamp('created_at')->nullable();
            $table->index(['user_id', 'created_at']);
        });

        // Catatan login untuk laporan & audit (NFR-03).
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('email', 150);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->string('channel', 20)->default('web');    // web|pwa|portal
            $table->string('result', 20);                     // success|invalid|locked|inactive|no_role
            $table->dateTime('attempted_at');
            $table->index(['email', 'attempted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_attempts');
        Schema::dropIfExists('password_histories');
        Schema::dropIfExists('devices');
        Schema::dropIfExists('user_invitations');
        Schema::dropIfExists('role_assignments');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('users');
        Schema::dropIfExists('positions');
        Schema::dropIfExists('org_units');
    }
};
