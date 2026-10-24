<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_settings', function (Blueprint $table) {
            // Biaya admin pembeli = persen x harga + flat, dibulatkan ke atas ke Rp100.
            // Default menutup biaya Sumopod (0,7% + Rp300) plus biaya penarikan dana.
            $table->decimal('admin_fee_percent', 5, 2)->default(1);
            $table->unsignedInteger('admin_fee_flat')->default(300);
        });

        Schema::table('bookings', function (Blueprint $table) {
            // Disimpan saat booking dibuat -- perubahan setelan tidak mengubah tagihan yang sudah ada.
            $table->unsignedInteger('admin_fee')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('payment_settings', function (Blueprint $table) {
            $table->dropColumn(['admin_fee_percent', 'admin_fee_flat']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('admin_fee');
        });
    }
};
