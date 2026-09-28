<?php

declare(strict_types=1);

use App\Domain\Shared\Messaging\PhoneNumber;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A-311–A-316: driver tidak lagi punya akun. SJ dan kendaraan mencatat nama +
 * HP driver sebagai teks (diisi balik dari user lama; `driver_id` dan
 * `default_driver_id` tetap untuk riwayat, P-03). Bukti terima diisi pihak
 * penerima: kanal baru, foto SJ bertanda tangan & cap, No. GR klien (A-313),
 * dan No. PO klien di REQ. Hak role bawaan disesuaikan tertarget — tidak
 * menimpa role yang sudah diubah company selain izin yang disebut di sini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('driver_name', 100)->nullable()->after('driver_id');
            $table->string('driver_phone', 20)->nullable()->after('driver_name');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('default_driver_name', 100)->nullable()->after('default_driver_id');
            $table->string('default_driver_phone', 20)->nullable()->after('default_driver_name');
        });

        Schema::table('proofs_of_delivery', function (Blueprint $table) {
            $table->string('channel', 20)->default('recipient_account')->change();
            $table->string('signed_document_path', 255)->nullable()->after('photo_path');
            $table->string('client_gr_number', 60)->nullable()->after('signed_document_path');
            $table->index('client_gr_number');
        });

        Schema::table('material_requests', function (Blueprint $table) {
            $table->string('client_po_number', 60)->nullable()->after('required_date');
            $table->index('client_po_number');
        });

        $this->isiBalik();
        $this->sesuaikanRole();
    }

    public function down(): void
    {
        Schema::table('material_requests', function (Blueprint $table) {
            $table->dropIndex(['client_po_number']);
            $table->dropColumn('client_po_number');
        });

        Schema::table('proofs_of_delivery', function (Blueprint $table) {
            $table->dropIndex(['client_gr_number']);
            $table->dropColumn(['signed_document_path', 'client_gr_number']);
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn(['default_driver_name', 'default_driver_phone']);
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn(['driver_name', 'driver_phone']);
        });
    }

    /** Data lama tetap terbaca: nama & HP disalin dari user driver (TC-SJ-29). */
    private function isiBalik(): void
    {
        foreach (DB::table('shipments')->whereNotNull('driver_id')->get(['id', 'driver_id']) as $sj) {
            $u = DB::table('users')->where('id', $sj->driver_id)->first(['name', 'phone']);

            if ($u !== null) {
                DB::table('shipments')->where('id', $sj->id)->update(['driver_name' => $u->name, 'driver_phone' => $this->hp($u->phone)]);
            }
        }

        // SJ jemput/antar site (A-247) dulu menyimpan sopir bebas di `carried_by_name`.
        DB::table('shipments')->whereNull('driver_id')->whereNotNull('source_type')->whereNotNull('carried_by_name')
            ->update(['driver_name' => DB::raw('carried_by_name')]);

        foreach (DB::table('vehicles')->whereNotNull('default_driver_id')->get(['id', 'default_driver_id']) as $k) {
            $u = DB::table('users')->where('id', $k->default_driver_id)->first(['name', 'phone']);

            if ($u !== null) {
                DB::table('vehicles')->where('id', $k->id)->update(['default_driver_name' => $u->name, 'default_driver_phone' => $this->hp($u->phone)]);
            }
        }
    }

    /** A-315: HP dibakukan `62…`; nomor yang tidak dikenali disalin apa adanya. */
    private function hp(?string $nomor): ?string
    {
        return $nomor === null ? null : (PhoneNumber::normalize($nomor) ?? mb_substr($nomor, 0, 20));
    }

    /** A-314, A-316: hak role bawaan (company baru mendapatkannya dari ReferenceSeeder). */
    private function sesuaikanRole(): void
    {
        if (! DB::table('roles')->exists()) {
            return;
        }

        DB::table('permissions')->insertOrIgnore([
            'name' => 'shipment.confirm_delivery_signed',
            'guard_name' => 'web',
            'module' => 'shipment',
            'label' => 'Isi bukti terima dari SJ bertanda tangan (cadangan)',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $izin = fn (string $nama) => DB::table('permissions')->where('name', $nama)->value('id');
        $role = fn (string $kode) => DB::table('roles')->where('code', $kode)->value('id');

        $driver = $role('driver');

        if ($driver !== null) {
            DB::table('roles')->where('id', $driver)->update(['name' => 'Driver (lama)']);
            DB::table('role_permissions')->where('role_id', $driver)
                ->whereIn('permission_id', array_filter([$izin('shipment.ship'), $izin('shipment.confirm_delivery')]))
                ->delete();
        }

        $tambah = [
            'shipment.confirm_delivery' => ['client_user', 'warehouse_head', 'warehouse_staff', 'internal_requester'],
            'shipment.confirm_delivery_signed' => ['warehouse_head', 'company_admin'],
        ];

        foreach ($tambah as $nama => $kodeRole) {
            $idIzin = $izin($nama);

            foreach ($kodeRole as $kode) {
                $idRole = $role($kode);

                if ($idIzin !== null && $idRole !== null) {
                    DB::table('role_permissions')->insertOrIgnore(['permission_id' => $idIzin, 'role_id' => $idRole]);
                }
            }
        }
    }
};
