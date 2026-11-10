<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A-433: klien boleh mengunggah rencana material proyeknya lewat portal —
 * izin `project.plan` DITAMBAHKAN ke role Klien bawaan (tanpa sync).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $izin = DB::table('permissions')->where('name', 'project.plan')->where('guard_name', 'web')->value('id');
        $role = DB::table('roles')->where('code', 'client_user')->value('id');

        if ($izin !== null && $role !== null) {
            DB::table('role_permissions')->insertOrIgnore(['permission_id' => $izin, 'role_id' => $role]);
        }
    }

    public function down(): void
    {
        // Dibiarkan (P-03).
    }
};
