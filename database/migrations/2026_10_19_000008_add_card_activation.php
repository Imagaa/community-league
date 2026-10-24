<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            // UID chip NFC yang dipasangkan ke kartu fisik (dikosongkan saat kartu diganti).
            $table->string('nfc_uid', 64)->nullable()->unique();
            // Kartu fisik aktif <=> sama dengan expires_at; perpanjangan otomatis membuatnya "belum aktif".
            $table->timestamp('card_activated_until')->nullable();
        });

        Schema::table('bookings', function (Blueprint $table) {
            // Hak poin check-in ditentukan saat tiket dibeli (null = booking lama, pakai status saat check-in).
            $table->boolean('member_at_purchase')->nullable();
        });

        Schema::table('membership_purchases', function (Blueprint $table) {
            // Dibuat dari scanner ("Perpanjang sekarang"): kartu yang sedang dipegang langsung aktif saat lunas.
            $table->boolean('activate_card')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('memberships', fn (Blueprint $table) => $table->dropColumn(['nfc_uid', 'card_activated_until']));
        Schema::table('bookings', fn (Blueprint $table) => $table->dropColumn('member_at_purchase'));
        Schema::table('membership_purchases', fn (Blueprint $table) => $table->dropColumn('activate_card'));
    }
};
