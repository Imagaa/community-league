<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Dibayar lewat Sumopod sandbox (uang tidak sungguhan) -- tidak dihitung di Keuangan.
            $table->boolean('is_sandbox')->default(false);
        });

        // Booking lama: link pembayaran sandbox Sumopod selalu ber-host *.sandbox.*
        DB::table('bookings')->where('sumopod_payment_link_url', 'like', '%sandbox%')->update(['is_sandbox' => true]);
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('is_sandbox');
        });
    }
};
